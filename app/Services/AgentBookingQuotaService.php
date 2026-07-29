<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Agent;
use App\Models\BookingItem;
use App\Models\CabinLock;
use App\Models\ScheduleCabinMapping;

/**
 * Agent cart + per-trip daily booking caps.
 *
 * Defaults: 2 cabins / 4 seats / 2 decks (cart at a time and daily per trip).
 * Admin options: agent_max_{cabin|seat|deck}_booking
 */
class AgentBookingQuotaService
{
    public const DEFAULT_CABIN = 2;
    public const DEFAULT_SEAT = 4;
    public const DEFAULT_DECK = 2;

    /**
     * @return array{cabin:int,seat:int,deck:int}
     */
    public function limits(): array
    {
        return [
            'cabin' => max(0, (int) getOption('agent_max_cabin_booking', self::DEFAULT_CABIN)),
            'seat' => max(0, (int) getOption('agent_max_seat_booking', self::DEFAULT_SEAT)),
            'deck' => max(0, (int) getOption('agent_max_deck_booking', self::DEFAULT_DECK)),
        ];
    }

    /**
     * Quota snapshot for trip layout API (remaining today + cart caps).
     *
     * @return array<string, mixed>
     */
    public function summaryForTrip(Agent $agent, int $tripId): array
    {
        $limits = $this->limits();
        $types = ['cabin', 'seat', 'deck'];
        $booked = [];
        $locked = [];
        $remaining = [];

        foreach ($types as $type) {
            $booked[$type] = $this->bookedTodayCount($agent, $tripId, $type);
            $locked[$type] = $this->lockedCartCount($agent, $tripId, $type);
            $remaining[$type] = max(0, $limits[$type] - $booked[$type]);
        }

        return [
            'limits' => $limits,
            'booked_today' => $booked,
            'locked_now' => $locked,
            'remaining_today' => $remaining,
        ];
    }

    /**
     * @return true|string true when allowed, otherwise error message
     */
    public function assertCanAdd(Agent $agent, ScheduleCabinMapping $mapping): bool|string
    {
        $type = $this->normalizeType($mapping->type ?? '');
        if ($type === '') {
            return true;
        }

        $tripId = (int) $mapping->schedule_id;
        $limit = $this->limits()[$type] ?? 0;
        if ($limit <= 0) {
            return __('Agent booking for :type is not allowed.', ['type' => $type]);
        }

        $locked = $this->lockedCartCount($agent, $tripId, $type);
        if ($locked >= $limit) {
            return $this->cartLimitMessage($type, $limit);
        }

        $booked = $this->bookedTodayCount($agent, $tripId, $type);
        if (($booked + $locked + 1) > $limit) {
            return $this->dailyLimitMessage($type, $limit, $booked);
        }

        return true;
    }

    /**
     * Validate a confirm payload (cart contents) against cart + daily caps.
     *
     * @param  array<int, object|array>  $cartItems
     * @return true|string
     */
    public function assertCanConfirm(Agent $agent, array $cartItems): bool|string
    {
        $byTripType = [];
        foreach ($cartItems as $raw) {
            $item = (array) $raw;
            $type = $this->normalizeType((string) ($item['type'] ?? 'seat'));
            if ($type === '') {
                continue;
            }

            $tripId = (int) ($item['trip_id'] ?? 0);
            if ($tripId <= 0 && ! empty($item['item_id'])) {
                $mapping = ScheduleCabinMapping::query()->find((int) $item['item_id']);
                $tripId = $mapping ? (int) $mapping->schedule_id : 0;
                if ($type === 'seat' || $type === 'cabin') {
                    $mappedType = $this->normalizeType((string) ($mapping->type ?? ''));
                    if ($mappedType !== '') {
                        $type = $mappedType;
                    }
                }
            }
            if ($tripId <= 0) {
                continue;
            }

            $key = $tripId.'|'.$type;
            $byTripType[$key] = ($byTripType[$key] ?? 0) + 1;
        }

        $limits = $this->limits();
        foreach ($byTripType as $key => $cartCount) {
            [$tripId, $type] = explode('|', $key, 2);
            $limit = $limits[$type] ?? 0;
            if ($limit <= 0) {
                return __('Agent booking for :type is not allowed.', ['type' => $type]);
            }
            if ($cartCount > $limit) {
                return $this->cartLimitMessage($type, $limit);
            }
            $booked = $this->bookedTodayCount($agent, (int) $tripId, $type);
            if (($booked + $cartCount) > $limit) {
                return $this->dailyLimitMessage($type, $limit, $booked);
            }
        }

        return true;
    }

    public function bookedTodayCount(Agent $agent, int $tripId, string $type): int
    {
        $type = $this->normalizeType($type);
        if ($type === '' || $tripId <= 0) {
            return 0;
        }

        return (int) BookingItem::query()
            ->where('trip_id', $tripId)
            ->where('booking_type', $type)
            ->where('status', AppConst::BOOKING_ITEM_ACTIVE)
            ->whereDate('booking_date', now()->toDateString())
            ->whereHas('booking', function ($q) use ($agent) {
                $q->where('booked_by_id', $agent->getKey())
                    ->where('booked_by_type', $agent->getMorphClass());
            })
            ->count();
    }

    public function lockedCartCount(Agent $agent, int $tripId, string $type): int
    {
        $type = $this->normalizeType($type);
        if ($type === '' || $tripId <= 0) {
            return 0;
        }

        $token = $this->agentLockToken($agent);
        if ($token === null) {
            return 0;
        }

        return (int) CabinLock::query()
            ->where('customer_token', $token)
            ->where('trip_id', $tripId)
            ->whereHas('mapping', fn ($q) => $q->where('type', $type))
            ->count();
    }

    private function agentLockToken(Agent $agent): ?string
    {
        $key = $agent->email
            ?: ($agent->mobile ?? null)
            ?: ('id:'.$agent->getAuthIdentifier());

        return $key !== null && $key !== '' ? base64_encode((string) $key) : null;
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if (in_array($type, ['cabin', 'seat', 'deck'], true)) {
            return $type;
        }

        return '';
    }

    private function cartLimitMessage(string $type, int $limit): string
    {
        return match ($type) {
            'cabin' => __('You can select at most :max cabins at a time.', ['max' => $limit]),
            'seat' => __('You can select at most :max seats at a time.', ['max' => $limit]),
            'deck' => __('You can select at most :max deck tickets at a time.', ['max' => $limit]),
            default => __('Selection limit reached.'),
        };
    }

    private function dailyLimitMessage(string $type, int $limit, int $booked): string
    {
        $remaining = max(0, $limit - $booked);

        return match ($type) {
            'cabin' => __('Daily cabin quota for this trip is :max (booked today: :booked, remaining: :remaining).', [
                'max' => $limit,
                'booked' => $booked,
                'remaining' => $remaining,
            ]),
            'seat' => __('Daily seat quota for this trip is :max (booked today: :booked, remaining: :remaining).', [
                'max' => $limit,
                'booked' => $booked,
                'remaining' => $remaining,
            ]),
            'deck' => __('Daily deck quota for this trip is :max (booked today: :booked, remaining: :remaining).', [
                'max' => $limit,
                'booked' => $booked,
                'remaining' => $remaining,
            ]),
            default => __('Daily booking quota for this trip has been reached.'),
        };
    }
}
