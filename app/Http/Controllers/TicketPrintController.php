<?php

namespace App\Http\Controllers;

use App\Models\Booking;

class TicketPrintController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $booking = Booking::with(['bookingItems.mapping.cabinType', 'bookingItems.trip.launch','bookingItems.trip.startFrom', 'bookingItems.trip.stopTo', 'bookingItems.customer'])->findOrFail($id);
//        dd($booking);
        return view('print.ticket', compact('booking'))->withTitle('Print ticket');
    }
}
