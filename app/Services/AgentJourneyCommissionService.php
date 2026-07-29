<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\AccountStatement;
use App\Models\AgentBalance;
use App\Models\AgentCommission;
use App\Models\BookingItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Credit agent commissions only after the journey/operation window ends,
 * so cancelled/refunded seats never generate earnings.
 */
class AgentJourneyCommissionService
{
    public function __construct(
        private readonly CalculationService $calculation,
        private readonly AccountStatementService $statements,
    )
    {
    }

    public function creditDueItems(int $limit = 200): int
    {
        $now = now()->toDateTimeString();
        $credited = 0;

        BookingItem::query()
            ->with([
                'booking.officer',
                'vehicle.partners.incentive',
                'trip',
            ])
            ->where('status', AppConst::BOOKING_ITEM_ACTIVE)
            ->whereNull('commission_settled_at')
            ->whereHas('booking', function ($q) {
                $q->where('status', AppConst::BOOKING_COMPLETE);
            })
            ->whereHas('trip', function ($q) use ($now) {
                $q->where('operation_timeline', '<=', $now);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (BookingItem $item) use (&$credited) {
                $credited += $this->creditItem($item);
            });

        return $credited;
    }

    public function creditItem(BookingItem $item): int
    {
        if ((int) $item->status !== AppConst::BOOKING_ITEM_ACTIVE) {
            return 0;
        }

        if ($item->commission_settled_at) {
            return 0;
        }

        $booking = $item->booking;
        if (! $booking || $booking->status !== AppConst::BOOKING_COMPLETE) {
            return 0;
        }

        $trip = $item->trip;
        if (! $trip || strtotime((string) $trip->operation_timeline) > time()) {
            return 0;
        }

        $count = 0;

        DB::transaction(function () use ($item, $booking, &$count) {
            $count += $this->creditSeller($item, $booking->officer);
            if ($booking->booking_party === AppConst::PARTY_DURPALLA) {
                $count += $this->creditVehicleShare($item);
            }

            // Mark settled even when amount is zero so the item is not retried forever.
            $item->commission_settled_at = now();
            $item->save();
        });

        return $count;
    }

    private function creditSeller(BookingItem $item, mixed $officer): int
    {
        if (! $this->isCommissionableSeller($officer)) {
            return 0;
        }

        $amount = (float) $this->calculation->calculateAgentCommission($item->toArray());
        if ($amount <= 0) {
            return 0;
        }

        return $this->creditOnce(
            userId: (int) $officer->id,
            item: $item,
            amount: $amount,
            totalSale: (float) $item->price - (float) $item->discount,
            purpose: 'commission',
        ) ? 1 : 0;
    }

    private function creditVehicleShare(BookingItem $item): int
    {
        $vehicle = $item->vehicle;
        if (! $vehicle || $vehicle->partners === null || $vehicle->partners->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($vehicle->partners as $partner) {
            $amount = (float) $this->calculation->calculatePartnerCommission($item->price, $partner);
            if ($amount <= 0) {
                continue;
            }
            if ($this->creditOnce(
                userId: (int) $partner->id,
                item: $item,
                amount: $amount,
                totalSale: (float) $item->price,
                purpose: 'commission',
            )) {
                $count++;
            }
        }

        return $count;
    }

    private function creditOnce(
        int $userId,
        BookingItem $item,
        float $amount,
        float $totalSale,
        string $purpose,
    ): bool {
        $exists = AgentCommission::query()
            ->where('user_id', $userId)
            ->where('booking_item_id', $item->id)
            ->where('type', 'credit')
            ->where('purpose', $purpose)
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            return false;
        }

        AgentCommission::query()->create([
            'user_id' => $userId,
            'booking_item_id' => $item->id,
            'type' => 'credit',
            'purpose' => $purpose,
            'commission_date' => now()->toDateString(),
            'total_sale' => $totalSale,
            'amount' => $amount,
        ]);

        $balance = AgentBalance::query()->firstOrNew(['user_id' => $userId]);
        $before = (float) ($balance->balance ?? 0);
        $balance->balance = $before + $amount;
        $balance->save();
        Cache::forget('my_balance_'.$userId);
        $after = (float) $balance->balance;

        $this->statements->record(
            accountType: AccountStatement::ACCOUNT_AGENT,
            accountId: $userId,
            direction: AccountStatement::DIRECTION_CREDIT,
            amount: $amount,
            balanceBefore: $before,
            balanceAfter: $after,
            source: 'commission',
            reference: 'booking_item:'.$item->id,
            description: ucfirst($purpose).' credited for booking item #'.$item->id,
            meta: [
                'booking_item_id' => (int) $item->id,
                'purpose' => $purpose,
            ],
            idempotencyKey: sprintf('agent:commission:%s:item:%d:user:%d', $purpose, $item->id, $userId)
        );

        return true;
    }

    private function isCommissionableSeller(mixed $officer): bool
    {
        if ($officer instanceof Agent) {
            return true;
        }

        return $officer instanceof User
            && method_exists($officer, 'hasAnyRole')
            && $officer->hasAnyRole([AppConst::AGENT_ROLE, 'supervisor']);
    }
}
