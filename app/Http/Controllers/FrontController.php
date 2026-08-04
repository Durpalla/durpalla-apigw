<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\InvoiceBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FrontController extends Controller
{
    public function index(Request $request)
    {
        return response('OK', 200);
    }

    /**
     * Signed invoice download/view used by all apps after payment success.
     * Same HTML template for customer, agent, merchant, and web.
     * Route: GET /download/{id} (name: invoice.download)
     */
    public function downloadInvoice(Request $request, $id, InvoiceBuilder $builder)
    {
        try {
            $booking = Booking::query()->findOrFail($id);
            $invoice = $builder->build($booking);

            return response()
                ->view('invoice.show', compact('invoice'))
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Cache-Control', 'no-store, private');
        } catch (\Throwable $e) {
            Log::error('Invoice download failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->view('invoice.error', [
                'message' => __('Unable to load invoice. Please try again from your bookings.'),
                'booking_id' => $id,
            ], 500);
        }
    }
}
