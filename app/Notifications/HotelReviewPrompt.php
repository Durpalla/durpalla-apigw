<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Models\Hotel;
use App\Models\HotelReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class HotelReviewPrompt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly HotelReservation $reservation,
        private readonly Hotel $hotel,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'hotel_review_prompt',
            'label' => 'info',
            'hotel_id' => (int) $this->hotel->id,
            'hotel_name' => (string) $this->hotel->name,
            'reservation_id' => (int) $this->reservation->id,
            'check_out' => $this->reservation->check_out?->toDateString(),
            'open_reviews' => true,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toFcm(object $notifiable): ?array
    {
        $token = (string) ($notifiable->device_id ?? '');
        if (strlen($token) <= 30) {
            return null;
        }

        $hotelName = (string) $this->hotel->name;

        return [
            'token' => $token,
            'notification' => [
                'title' => 'How was your stay?',
                'body' => "Share your experience at {$hotelName}.",
            ],
            'data' => [
                'type' => 'hotel_review_prompt',
                'hotel_id' => (string) $this->hotel->id,
                'hotel_name' => $hotelName,
                'open_reviews' => 'true',
            ],
        ];
    }
}
