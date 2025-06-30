<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use PDF;

class FrontController extends Controller
{

    public function index()
    {
        return view('front.index');
    }

    public function downloadInvoice( Request $request, $id )
    {
        // Fetch all customers from database
        $booking = Booking::with(['bookingItems.trip.route', 'cancellations', 'bookingItems.item.cabinType', 'bookingItems.trip.launch', 'payment', 'customer'])->findOrFail($id);
        // Send data to the view using loadView function of PDF facade
        // $pdf = PDF::loadView('emails.invoice', $customers);
        $responseArr = [];

        if( $booking ) {
            $file_name = $booking->customer->name . '_' . $booking->customer->mobile . '_' . $booking->id;
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
            $responseArr['seal'] = config('constants.seals')[$booking->status];
            $responseArr['items'] = [];

            $cancellations = [];
            if( $booking->cancellations ) {
                foreach( $booking->cancellations as $cancellation ) {
                    $cancellations = array_merge_recursive( $cancellations, explode(',', $cancellation->items) );
                }
            }

            // $responseArr['status'] = $booking->status;

            foreach( $booking->bookingItems as $item ) {
                $schedule_date = date('d F Y', strtotime( $item['trip_date'] ) );
                $from = ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'];
                $to = ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'];
                $file_name .= '_' . $from . '-' . $to . '_' . str_replace(' ', '_', $schedule_date);
                $row = [
                    'id' => $item['id'],
                    'cabin_no' => ( $item['item'] ) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                    'cabin_type' => $item['booking_type'],
                    'price' => $item['price'],
                    'cabin_position' => $item['cabin_position'],
                    'discount' => $item['discount'],
                    'is_ac' => ($item['item']) ? $item['item']['cabinType']['is_ac'] : '',
                    'vehicle_name' => $item['trip']['launch']['name'],
                    'route_name' => $item['trip']['route']['route_name'],
                    'schedule_date' => $schedule_date,
                    'leaving_time' => $item['trip']['leaving_at'],
                    'leaving_time_formated' => date('h:i A', strtotime($item['trip']['leaving_at'])),
                    'boarding_point' => json_decode($item['boarding_point']),
                    'passenger' => json_decode($item['passenger']),
                    'from' => $from,
                    'to' => $to,
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

            $responseArr['items'] = ( $responseArr['items'] ) ? _my_group_by_old($responseArr['items'], 'schedule_date' ) : [];

            $tickets = [];
            foreach( $responseArr['items'] as $key => $items ) {
                array_push($tickets, ['date' => $key, 'tickets' => $items]);
            }

            $responseArr['items'] = $tickets;

            $pdf = PDF::loadHtml(view('emails.invoice', ['invoice' => $responseArr]), [
                'instanceConfigurator' => function($mpdf) use($responseArr) {
                    $mpdf->SetWatermarkText($responseArr['seal'], 0.1);
                    $mpdf->showWatermarkText = true;
                }
            ]);
            $pdf->SetProtection(['copy', 'print'], '', 'jolzan');

            return $pdf->stream($file_name . '.pdf');
        }
    }
}
