<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\InvoiceBuilder;
use App\Support\BookingInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class FrontController extends Controller
{
    public function index(Request $request)
    {
        return response('OK', 200);
    }

    /**
     * Signed invoice PDF download used by all apps after payment success.
     * Route: GET /download/{id} (name: invoice.download)
     */
    public function downloadInvoice(Request $request, $id, InvoiceBuilder $builder)
    {
        try {
            $booking = Booking::query()->findOrFail($id);
            $invoice = $builder->build($booking);
            $reference = BookingInvoice::formatReference($booking);
            $fileName = 'invoice-'.$reference.'.pdf';
            $tempDir = storage_path('app/mpdf');

            // Materialize logos to local files — remote CDN URLs often 403 / fail inside mPDF.
            $merchantLogo = $builder->materializeLogoForPdf(
                $invoice['merchant']['logo_url'] ?? null,
                $tempDir,
                'merchant-'.$booking->id
            );
            $companyLogo = $builder->materializeLogoForPdf(
                $invoice['company_logo_url'] ?? config('invoice.company_logo_url'),
                $tempDir,
                'company'
            );
            $invoice['merchant']['logo_url'] = $merchantLogo;
            $invoice['company_logo_url'] = $companyLogo;

            $html = view('invoice.pdf', compact('invoice'))->render();

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'tempDir' => $tempDir,
            ]);
            $mpdf->showImageErrors = false;
            $mpdf->curlAllowUnsafeSslRequests = true;

            $seal = (string) ($invoice['seal'] ?? '');
            if ($seal !== '') {
                $mpdf->SetWatermarkText($seal, 0.08);
                $mpdf->showWatermarkText = true;
            }

            $mpdf->WriteHTML($html);
            $pdf = $mpdf->Output($fileName, \Mpdf\Output\Destination::STRING_RETURN);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                'Cache-Control' => 'no-store, private',
                'Content-Length' => (string) strlen($pdf),
            ]);
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
