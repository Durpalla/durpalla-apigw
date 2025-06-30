<?php


namespace App\Services;


use App\Models\Booking;

class HelperService
{
    public function getMessage(Booking $booking): string
    {
        $message = 'Dear ' . $booking->customer->name . ', %0AYour ticket-' . $booking->id . '%0A';
        $scheduleSms = [];
        if ($booking->bookingItems) {
            foreach ($booking->bookingItems as $item) {
                $scheduleSms[$item->trip_id][] = $item;
            }
        }
        if ($scheduleSms) {
            foreach ($scheduleSms as $key => $items) {
                $firstItem = collect($items)->first();
                $message .= $firstItem->vehicle['name'] . '<>';
                if ($firstItem->trip) {
                    $message .= date('d-m-Y h:iA', strtotime($firstItem->trip['leaving_at']));
                }
                if ($items[0]->customer) {
                    $message .= '<>' . $firstItem->customer['mobile'];
                }
                foreach ($items as $k => $item) {
                    if ($k > 0) {
                        $message .= ',';
                    }
                    $passenger = json_decode($item->passenger);
                    if ($item->booking_type != 'deck') {
                        $message .= '<>' . $item->item['cabinType']['name'] . ' ' . $item->item['type'] . ' (' . $item->item['cabinType']['letter'] . '-' . $item->item['cabin_no'] . ')';
                    } else {
                        $message .= '<>Deck(' . $passenger->person . ')';
                    }
                }
            }
        }
        $message .= '%0ASafe travels!';

        return $message;
    }
}
