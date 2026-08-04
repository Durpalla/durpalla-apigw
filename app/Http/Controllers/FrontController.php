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
     * Signed HTML invoice preview (agent / customer WebView).
     * Route: GET /invoice/{id} (name: invoice.view)
     */
    public function viewInvoice(Request $request, $id, InvoiceBuilder $builder)
    {
        try {
            $booking = Booking::query()->findOrFail($id);
            $invoice = $builder->build($booking);

            return response()
                ->view('invoice.show', compact('invoice'))
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Cache-Control', 'no-store, private');
        } catch (\Throwable $e) {
            Log::error('Invoice HTML view failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->view('invoice.error', [
                'message' => __('Unable to load invoice. Please try again from your bookings.'),
                'booking_id' => $id,
            ], 500);
        }
    }

    /**
     * Signed invoice PDF download used by all apps after payment success.
     * Route: GET /download/{id} (name: invoice.download)
     *
     * Logos/QR are injected via mPDF imageVars (var:name) — never remote HTTP URLs.
     */
    public function downloadInvoice(Request $request, $id, InvoiceBuilder $builder)
    {
        try {
            $booking = Booking::query()->findOrFail($id);
            $invoice = $builder->build($booking);
            $reference = BookingInvoice::formatReference($booking);
            $fileName = 'invoice-'.$reference.'.pdf';
            $tempDir = storage_path('app/mpdf');

            $merchantBinary = $builder->resolveLogoBinary(
                $invoice['merchant']['logo_url'] ?? null
            );
            $companyBinary = $builder->resolveLogoBinary(
                $invoice['company_logo_url'] ?? config('invoice.company_logo_url')
            );
            $qrBinary = null;
            if (! empty($invoice['qr_payload'])) {
                $qrBinary = $builder->resolveQrBinary((string) $invoice['qr_payload']);
            }

            $invoice['pdf_images'] = [
                'merchant' => $merchantBinary !== null,
                'company' => $companyBinary !== null,
                'qr' => $qrBinary !== null,
            ];
            // Clear URL-based src so the blade only uses var: placeholders.
            $invoice['merchant']['logo_url'] = null;
            $invoice['company_logo_url'] = null;
            $invoice['qr'] = null;

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

            if ($merchantBinary !== null) {
                $mpdf->imageVars['merchantLogo'] = $merchantBinary;
            }
            if ($companyBinary !== null) {
                $mpdf->imageVars['companyLogo'] = $companyBinary;
            }
            if ($qrBinary !== null) {
                $mpdf->imageVars['invoiceQr'] = $qrBinary;
            }

            $seal = (string) ($invoice['seal'] ?? '');
            if ($seal !== '') {
                $mpdf->SetWatermarkText($seal, 0.08);
                $mpdf->showWatermarkText = true;
            }

            $mpdf->WriteHTML($html);
            $pdf = $mpdf->Output($fileName, \Mpdf\Output\Destination::STRING_RETURN);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
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
