<?php

namespace App\Services;

use App\Models\Booking;

/**
 * Builds invoice payload for HTML/PDF rendering (transport + hotel bookings).
 */
class InvoiceBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Booking $booking): array
    {
        $booking->loadMissing([
            'customer',
            'payment',
            'cancellations',
            'hotelReservation.roomType',
            'bookingItems.trip.route',
            'bookingItems.trip.launch',
            'bookingItems.trip.startingPoint.ghat',
            'bookingItems.trip.endingPoint.ghat',
            'bookingItems.item.cabinType',
        ]);

        $sealMap = config('constants.seals', []);
        $status = (string) ($booking->status ?? '');
        $payable = (float) ($booking->total_payable
            ?? ((float) $booking->total_amount + (float) $booking->vat_total + (float) $booking->charge_total - (float) $booking->total_discount));

        $invoice = [
            'id' => $booking->id,
            'pnr' => $booking->id,
            'qr' => asset('qrs/'.$booking->id.'.png'),
            'booking_date' => optional($booking->created_at)->format('Y-m-d H:i:s'),
            'booking_date_formated' => optional($booking->created_at)->format('d M, Y h:i A'),
            'payment_status' => $booking->payment->status ?? ($booking->payment_status ?? ''),
            'total_amount' => (float) ($booking->total_amount ?? 0),
            'total_discount' => (float) ($booking->total_discount ?? 0),
            'vat_amount' => (float) ($booking->vat_amount ?? 0),
            'vat_total' => (float) ($booking->vat_total ?? 0),
            'charge_amount' => (float) ($booking->charge_amount ?? 0),
            'charge_total' => (float) ($booking->charge_total ?? 0),
            'total_payable' => number_format($payable, 2, '.', ''),
            'payment' => $booking->payment,
            'customer' => $booking->customer,
            'seal' => $sealMap[$status] ?? strtoupper($status ?: 'PAID'),
            'service_type' => (string) ($booking->service_type ?? 'transport'),
            'items' => [],
            'hotel' => null,
        ];

        if ($booking->hotelReservation) {
            $res = $booking->hotelReservation;
            $invoice['hotel'] = [
                'title' => (string) ($res->roomType->title ?? $res->roomType->name ?? 'Hotel'),
                'check_in' => optional($res->check_in)->toDateString(),
                'check_out' => optional($res->check_out)->toDateString(),
                'adults' => (int) ($res->adults ?? 0),
                'children' => (int) ($res->children ?? 0),
            ];
        }

        $cancellations = [];
        foreach ($booking->cancellations ?? [] as $cancellation) {
            $cancellations = array_merge($cancellations, explode(',', (string) ($cancellation->items ?? '')));
        }

        foreach ($booking->bookingItems as $item) {
            $trip = $item->trip;
            if (! $trip) {
                continue;
            }

            $scheduleDate = date('d F Y', strtotime((string) $item->trip_date));
            $isReverse = ($trip->schedule_type ?? '') === 'reverse';
            $from = $isReverse
                ? (string) data_get($trip, 'endingPoint.ghat.name', '')
                : (string) data_get($trip, 'startingPoint.ghat.name', '');
            $to = $isReverse
                ? (string) data_get($trip, 'startingPoint.ghat.name', '')
                : (string) data_get($trip, 'endingPoint.ghat.name', '');

            $cabinLetter = data_get($item, 'item.cabinType.letter');
            $cabinNo = data_get($item, 'item.cabin_no');
            $row = [
                'id' => $item->id,
                'cabin_no' => ($cabinLetter && $cabinNo) ? ($cabinLetter.'-'.$cabinNo) : '',
                'cabin_type' => (string) $item->booking_type,
                'price' => (float) $item->price,
                'cabin_position' => $item->cabin_position,
                'discount' => (float) ($item->discount ?? 0),
                'is_ac' => data_get($item, 'item.cabinType.is_ac'),
                'vehicle_name' => (string) data_get($trip, 'launch.name', ''),
                'route_name' => $from && $to ? ($from.' - '.$to) : (string) data_get($trip, 'route.route_name', ''),
                'schedule_date' => $scheduleDate,
                'leaving_time' => $trip->leaving_at,
                'leaving_time_formated' => $trip->leaving_at ? date('h:i A', strtotime((string) $trip->leaving_at)) : '',
                'boarding_point' => json_decode((string) ($item->boarding_point ?? ''), true),
                'passenger' => json_decode((string) ($item->passenger ?? ''), true),
                'from' => $from,
                'to' => $to,
                'cancellable' => ((string) $item->trip_date >= date('Y-m-d'))
                    ? (! in_array((string) $item->id, $cancellations, true))
                    : false,
                'status' => $item->status,
            ];

            $invoice['items'][] = $row;
        }

        $grouped = $invoice['items'] ? _my_group_by_old($invoice['items'], 'schedule_date') : [];
        $tickets = [];
        foreach ($grouped as $date => $items) {
            $tickets[] = ['date' => $date, 'tickets' => $items];
        }
        $invoice['items'] = $tickets;

        return $invoice;
    }
}
