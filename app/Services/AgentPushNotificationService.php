<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingCancellation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Models\DeviceToken;
use Rajtika\Firebase\Services\Firebase;

class AgentPushNotificationService
{
    public const TYPE_BOOKING = 'booking';
    public const TYPE_CANCELLATION = 'cancellation';
    public const TYPE_REFUND = 'refund';

    public function notifyBooking(Booking $booking): void
    {
        $customer = $booking->customer?->name ?: 'Customer';
        $this->sendToAgents(
            $this->recipientAgentIds($booking),
            self::TYPE_BOOKING,
            (int) $booking->id,
            __('Booking confirmed'),
            __('PNR :pnr confirmed for :customer', [
                'pnr' => $booking->id,
                'customer' => $customer,
            ])
        );
    }

    public function notifyCancellation(BookingCancellation $cancellation, string $statusLabel): void
    {
        $booking = $cancellation->booking;
        if (! $booking) {
            return;
        }

        $this->sendToAgents(
            $this->recipientAgentIds($booking),
            self::TYPE_CANCELLATION,
            (int) $booking->id,
            __('Booking cancellation'),
            __('PNR :pnr cancellation: :status', [
                'pnr' => $booking->id,
                'status' => $statusLabel,
            ])
        );
    }

    public function notifyRefund(BookingCancellation $cancellation): void
    {
        $booking = $cancellation->booking;
        if (! $booking) {
            return;
        }

        $this->sendToAgents(
            $this->recipientAgentIds($booking),
            self::TYPE_REFUND,
            (int) $booking->id,
            __('Refund processed'),
            __('PNR :pnr refund has been completed', [
                'pnr' => $booking->id,
            ])
        );
    }

    /**
     * @return list<int>
     */
    public function recipientAgentIds(Booking $booking): array
    {
        $ids = [];

        if ($booking->booked_by_type === Agent::class && $booking->booked_by_id) {
            $ids[] = (int) $booking->booked_by_id;
        }

        if (! empty($booking->referring_agent_id)) {
            $ids[] = (int) $booking->referring_agent_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  list<int>  $agentIds
     */
    public function sendToAgents(array $agentIds, string $type, int $bookingId, string $title, string $body): void
    {
        foreach ($agentIds as $agentId) {
            foreach ($this->tokensForAgent($agentId) as $token) {
                $this->send($token, $type, $bookingId, $title, $body);
            }
        }
    }

    /**
     * @return Collection<int, string>
     */
    public function tokensForAgent(int $agentId): Collection
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $agentId)
            ->where('platform', 'like', 'agent%')
            ->pluck('token');

        $agent = Agent::query()->find($agentId);
        if ($agent && is_string($agent->device_id) && strlen($agent->device_id) > 30) {
            $tokens->push($agent->device_id);
        }

        return $tokens->filter(fn ($token) => is_string($token) && strlen($token) > 30)->unique()->values();
    }

    public function registerToken(Agent $agent, string $token, string $platform = 'agent_android'): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        DeviceToken::query()
            ->where('user_id', $agent->id)
            ->where('platform', 'like', 'agent%')
            ->delete();
        DeviceToken::query()->where('token', $token)->delete();
        DeviceToken::query()->create([
            'user_id' => $agent->id,
            'token' => $token,
            'platform' => $platform,
        ]);

        // Keep agents.device_id in sync for legacy Firebase helpers.
        $agent->device_id = $token;
        $agent->save();
    }

    private function send(string $token, string $type, int $bookingId, string $title, string $body): void
    {
        if (! class_exists(Firebase::class)) {
            Log::debug('Agent FCM send skipped: Firebase package not installed', [
                'type' => $type,
                'booking_id' => $bookingId,
            ]);

            return;
        }

        try {
            Firebase::to($token)
                ->setType($type)
                ->setID($bookingId)
                ->setTitle($title)
                ->setBody($body)
                ->send('data');
        } catch (\Throwable $e) {
            Log::warning('Agent FCM send failed', [
                'type' => $type,
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
