<?php

namespace App\Jobs;

use App\Constants\AppConst;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Models\Booking;
use PDF;

class BookingInvoiceSendToEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Booking
     */
    private $booking;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Booking $booking)
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
        $responseArr = [];
        if ($this->booking && in_array($this->booking->status, [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE])) {
            $responseArr['id'] = $this->booking->id;
            $responseArr['pnr'] = $this->booking->id;
            $responseArr['qr'] = upload_asset('qrs/' . $this->booking->id . '.png');
            $responseArr['booking_date'] = date('Y-m-d H:i:s', strtotime($this->booking->created_at));
            $responseArr['booking_date_formated'] = date('d M, Y h:i A', strtotime($this->booking->created_at));
            $responseArr['payment_status'] = $this->booking->payment['status'];
            $responseArr['total_amount'] = $this->booking->total_amount;
            $responseArr['total_discount'] = $this->booking->total_discount;
            $responseArr['vat_amount'] = $this->booking->vat_amount;
            $responseArr['vat_total'] = $this->booking->vat_total;
            $responseArr['charge_amount'] = $this->booking->charge_amount;
            $responseArr['charge_total'] = $this->booking->charge_total;
            $responseArr['total_payable'] = number_format(($this->booking->total_amount + $this->booking->vat_total + $this->booking->charge_total - $this->booking->total_discount), 2);
            $responseArr['payment'] = $this->booking->payment;
            $responseArr['customer'] = $this->booking->customer;
            $responseArr['items'] = [];

            $cancellations = [];
            if ($this->booking->cancellations) {
                foreach ($this->booking->cancellations as $cancellation) {
                    $cancellations = array_merge_recursive($cancellations, explode(',', $cancellation->items));
                }
            }

            foreach ($this->booking->bookingItems as $item) {
                $row = [
                    'id' => $item['id'],
                    'cabin_no' => ($item['item']) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                    'cabin_type' => $item['booking_type'],
                    'price' => $item['price'],
                    'discount' => $item['discount'],
                    'is_ac' => ($item['booking_type'] != 'deck') ? $item['item']['cabinType']['is_ac'] : 0,
                    'vehicle_name' => $item['trip']['launch']['name'],
                    'route_name' => $item['trip']['route']['route_name'],
                    'schedule_date' => date('d F Y', strtotime($item['trip_date'])),
                    'leaving_time' => $item['trip']['leaving_at'],
                    'leaving_time_formated' => date('h:i A', strtotime($item['trip']['leaving_at'])),
                    'boarding_point' => json_decode($item['boarding_point']),
                    'passenger' => json_decode($item['passenger']),
                    'from' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                    'to' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                    'cancellable' => ($item['trip_date'] >= date('Y-m-d')) ? ((in_array($item['id'], $cancellations)) ? false : true) : false,
                    'status' => $item['status']
                ];
                if ($item['trip']['schedule_type'] == 'reverse') {
                    $row['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                }
                array_push($responseArr['items'], $row);
            }

            $responseArr['items'] = ($responseArr['items']) ? _my_group_by_old($responseArr['items'], 'schedule_date') : [];

            $tickets = [];
            foreach ($responseArr['items'] as $key => $items) {
                array_push($tickets, ['date' => $key, 'tickets' => $items]);
            }

            $responseArr['items'] = $tickets;

            //make pdf
            $pdf = PDF::loadView('emails.invoice', ['invoice' => $responseArr]);
            $user = $this->booking->customer;
            //send mail to customer
            Mail::send('emails.booking', $user->toArray(), function ($message) use ($user, $pdf) {
                $message->to($user->email, $user->name)
                    ->subject('Booking Invoice')
                    ->attachData($pdf->output(), "invoice.pdf");
            });
        }
    }
}
