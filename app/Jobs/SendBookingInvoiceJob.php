<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use PDF;

class SendBookingInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $booking;

    /**
     * Create a new job instance.
     *
     * @param $booking
     */
    public function __construct($booking)
    {
        $this->booking = $booking;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Send data to the view using loadView function of PDF facade
        // $pdf = PDF::loadView('emails.invoice', $customers);
        $responseArr = [];
        if( $booking ) {
            $responseArr['id'] = $booking->id;
            $responseArr['pnr'] = $booking->id;
            $responseArr['qr'] = asset('qrs/' . $booking->id . '.png');
            $responseArr['booking_date'] = date('Y-m-d H:i:s', strtotime( $booking->created_at ) );
            $responseArr['booking_date_formated'] = date('d M, Y h:i A', strtotime( $booking->created_at ) );
            $responseArr['payment_status'] = $booking->payment['status'];
            $responseArr['total_amount'] = $booking->total_amount;
            $responseArr['total_discount'] = $booking->total_discount;
            $responseArr['vat_amount'] = $booking->vat_amount;
            $responseArr['vat_total'] = $booking->vat_total;
            $responseArr['charge_amount'] = $booking->charge_amount;
            $responseArr['charge_total'] = $booking->charge_total;
            $responseArr['total_payable'] = number_format(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount),2);
            $responseArr['payment'] = $booking->payment;
            $responseArr['customer'] = $booking->customer;
            $responseArr['items'] = [];

            $cancellations = [];
            if( $booking->cancellations ) {
                foreach( $booking->cancellations as $cancellation ) {
                    $cancellations = array_merge_recursive( $cancellations, explode(',', $cancellation->items) );
                }
            }

            // $responseArr['status'] = $booking->status;

            foreach( $booking->bookingItems as $item ) {
                $row = [
                    'id' => $item['id'],
                    'cabin_no' => ( $item['item'] ) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                    'cabin_type' => $item['booking_type'],
                    'price' => $item['price'],
                    'cabin_position' => $item['cabin_position'],
                    'discount' => $item['discount'],
                    'is_ac' => ($item['item']) ? $item['item']['cabinType']['is_ac'] : null,
                    'launch_name' => $item['trip']['launch']['name'],
                    'route_name' => $item['trip']['route']['route_name'],
                    'schedule_date' => date('d F Y', strtotime( $item['trip_date'] ) ),
                    'leaving_time' => $item['trip']['leaving_at'],
                    'leaving_time_formated' => date('h:i A', strtotime($item['trip']['leaving_at'])),
                    'boarding_point' => json_decode($item['boarding_point']),
                    'passenger' => json_decode($item['passenger']),
                    'from' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                    'to' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                    'cancellable' => ($item['trip_date'] >= date('Y-m-d')) ? (( in_array($item['id'], $cancellations) ) ? false : true) : false,
                    'status' => $item['status']
                ];
                if($item['booking_type'] == 'deck' && $item['deck']) {
                    $row['from'] = ($item['trip']['schedule_type'] == 'reverse' ) ? $item['deck']['departureTo']['ghat']['name'] : $item['deck']['departureFrom']['ghat']['name'];
                    $row['to'] = ($item['trip']['schedule_type'] == 'reverse' ) ? $item['deck']['departureFrom']['ghat']['name'] : $item['deck']['departureTo']['ghat']['name'];
                }
                if( $item['trip']['schedule_type'] == 'reverse' ) {
                    $row['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                } else {
                    $row['route_name'] = $item['trip']['startingPoint']['ghat']['name'] . ' - ' . $item['trip']['endingPoint']['ghat']['name'];
                }
                array_push($responseArr['items'], $row);
            }

            $responseArr['items'] = ( $responseArr['items'] ) ? _my_group_by($responseArr['items'], 'schedule_date' ) : [];

            $tickets = [];
            foreach( $responseArr['items'] as $key => $items ) {
                array_push($tickets, ['date' => $key, 'tickets' => $items]);
            }

            $responseArr['items'] = $tickets;
        }
        $pdf = PDF::loadView('emails.invoice',['invoice' => $responseArr]);
        $user = User::find($booking->customer_id);
        if( $user->email ) {
            try {
                \Mail::send('emails.booking', $user->toArray(), function ($message) use ($user, $pdf) {
                    $message->to($user->email, $user->name)
                        ->subject('Launch ticket purchase')
                        ->attachData($pdf->output(), "invoice.pdf");
                });
                $message = 'Ticket-' . $booking->id . '%0A';
                $scheduleSms = [];
                if( $booking->bookingItems ) {
                    foreach ($booking->BookingItems as $item) {
                        $scheduleSms[$item->trip_id][] = $item;
                    }
                }
                if( $scheduleSms ) {
                    foreach ($scheduleSms as $key => $items) {
                        $message .= $items[0]->launch['name'] . '<>' . date('d-m-Y h:iA', strtotime($items[0]->trip['leaving_at'])) . '<>' . $items[0]->customer['mobile'];
                        foreach ($items as $k => $item) {
//                                if ($k > 0) {
//                                    $message .= ',';
//                                }
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
                sendSMS([
                    'mobile' => $booking->customer->mobile,
                    'message' => $message
                ]);
            } catch (\Exception $e) {
            }
        }
    }
}
