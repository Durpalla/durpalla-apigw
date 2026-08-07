<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\InvoiceBuilder;
use App\Support\BookingInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
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
        $lang = BookingInvoice::applyLocale($request->query('lang'));

        try {
            $booking = Booking::query()->with('payment')->findOrFail($id);
            if (! $this->bookingAllowsInvoice($booking)) {
                return response()->view('invoice.error', [
                    'message' => __('Invoice is available only for successful bookings.'),
                    'booking_id' => $id,
                ], 403);
            }
            $invoice = $builder->build($booking);
            $invoice['lang'] = $lang;

            // PDF link lasts at least as long as the HTML signed URL when ?expires= is present.
            $expiresAt = (int) $request->query('expires');
            $downloadMinutes = ($expiresAt > time())
                ? max(1, (int) ceil(($expiresAt - time()) / 60))
                : (60 * 24);
            $invoice['download_url'] = BookingInvoice::signedUrl($booking, $downloadMinutes, $lang);

            return response()
                ->view('invoice.show', compact('invoice'))
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Cache-Control', 'no-store, private')
                ->header('Content-Language', $lang);
        } catch (\Throwable $e) {
            Log::error('Invoice HTML view failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->view('invoice.error', [
                'message' => __('invoice.load_failed'),
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
        $lang = BookingInvoice::applyLocale($request->query('lang'));

        try {
            $booking = Booking::query()->with('payment')->findOrFail($id);
            if (! $this->bookingAllowsInvoice($booking)) {
                return response()->json([
                    'success' => false,
                    'message' => __('Invoice is available only for successful bookings.'),
                ], 403);
            }
            $invoice = $builder->build($booking);
            $reference = BookingInvoice::formatReference($booking);
            $fileName = 'invoice-'.$reference.'.pdf';
            $tempDir = storage_path('app/mpdf');

            $merchantBinary = $builder->resolveLogoBinary(
                $invoice['merchant']['logo_url'] ?? null,
                false
            );
            $companyBinary = $builder->resolveLogoBinary(
                $invoice['company_logo_url'] ?? null,
                true
            ) ?? $builder->resolvePackagedCompanyLogoBinary();

            $merchantFile = $merchantBinary !== null
                ? $builder->writePdfImageFile($merchantBinary, $tempDir, 'inv-'.$booking->id.'-merchant')
                : null;
            $companyFile = $companyBinary !== null
                ? $builder->writePdfImageFile($companyBinary, $tempDir, 'inv-'.$booking->id.'-company')
                : null;

            $qrFile = null;
            $qrBinary = null;
            $qrSvg = null;
            if (! empty($invoice['qr_payload'])) {
                $payload = (string) $invoice['qr_payload'];
                $qrBinary = $builder->resolveQrBinary($payload);
                if ($qrBinary !== null) {
                    $qrFile = $builder->writePdfImageFile($qrBinary, $tempDir, 'inv-'.$booking->id.'-qr');
                }
                // Inline SVG works in mPDF without GD; <img src="*.svg"> often fails.
                if ($qrFile === null) {
                    $qrSvg = $builder->localQrSvgMarkup($payload, 140);
                }
            }

            // Absolute filesystem paths — mPDF is reliable with local JPEG/PNG files.
            $invoice['pdf_data_uris'] = [
                'merchant' => $merchantFile,
                'company' => $companyFile,
                'qr' => $qrFile,
            ];
            $invoice['qr_svg'] = $qrSvg;
            $invoice['pdf_images'] = [
                'merchant' => $merchantFile !== null,
                'company' => $companyFile !== null,
                'qr' => $qrFile !== null || $qrSvg !== null,
            ];
            $invoice['lang'] = $lang;
            $invoice['merchant']['logo_url'] = null;
            $invoice['company_logo_url'] = null;
            $invoice['qr'] = null;

            $html = view('invoice.pdf', compact('invoice'))->render();

            $mpdf = $this->makeInvoiceMpdf($tempDir, $lang);
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

            Log::info('Invoice PDF logos resolved', [
                'booking_id' => $id,
                'merchant_file' => $merchantFile,
                'company_file' => $companyFile,
                'qr_file' => $qrFile,
                'qr_svg' => $qrSvg !== null,
                'merchant_bytes' => $merchantBinary !== null ? strlen($merchantBinary) : 0,
                'company_bytes' => $companyBinary !== null ? strlen($companyBinary) : 0,
                'qr_bytes' => $qrBinary !== null ? strlen($qrBinary) : 0,
                'gd' => extension_loaded('gd'),
                'packaged_logo' => is_file(resource_path('invoice-assets/logo-company.jpg')),
            ]);

            $seal = (string) ($invoice['seal'] ?? '');
            if ($seal !== '') {
                // Seal is always Latin (PAID/FAILED). Bangla default font has no Latin
                // glyphs — without this, the watermark renders as empty boxes.
                $mpdf->watermark_font = is_file(resource_path('fonts/FreeSans.ttf'))
                    ? 'freesans'
                    : 'dejavusans';
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
                'Content-Language' => $lang,
            ]);
        } catch (\Throwable $e) {
            Log::error('Invoice download failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->view('invoice.error', [
                'message' => __('invoice.load_failed'),
                'booking_id' => $id,
            ], 500);
        }
    }

    private function makeInvoiceMpdf(string $tempDir, string $lang): Mpdf
    {
        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            $tempDir = sys_get_temp_dir();
        }

        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontDirs[] = resource_path('fonts');

        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        // Noto Sans Bengali for bn invoices (complex-script OTL). Latin/DPB/emails
        // stay on FreeSans via .latin spans — Noto Sans Bengali is Bengali-script only.
        $fontData['notosansbengali'] = [
            'R' => 'NotoSansBengali-Regular.ttf',
            'B' => 'NotoSansBengali-Bold.ttf',
            'useOTL' => 0xFF,
        ];
        $fontData['freesans'] = [
            'R' => 'FreeSans.ttf',
            'B' => 'FreeSans.ttf',
            'useOTL' => 0xFF,
        ];
        // Kept as fallbacks if Noto files are missing from the image.
        $fontData['freeserif'] = [
            'R' => 'FreeSerif.ttf',
            'B' => 'FreeSerif.ttf',
            'useOTL' => 0xFF,
        ];
        $fontData['mukti'] = [
            'R' => 'Mukti.ttf',
            'B' => 'Mukti.ttf',
            'useOTL' => 0xFF,
        ];

        $defaultFont = 'dejavusans';
        if ($lang === BookingInvoice::LANG_BN) {
            if (is_file(resource_path('fonts/NotoSansBengali-Regular.ttf'))) {
                $defaultFont = 'notosansbengali';
            } elseif (is_file(resource_path('fonts/FreeSerif.ttf'))) {
                $defaultFont = 'freeserif';
            }
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => $tempDir,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'default_font' => $defaultFont,
            // Keep off: autoLangToFont remaps bn inconsistently and can produce tofu.
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
        ]);
    }

    /**
     * Invoice view/download only for successful bookings with paid/settled payment.
     */
    private function bookingAllowsInvoice(Booking $booking): bool
    {
        $bookingStatus = strtoupper(trim((string) ($booking->status ?? '')));
        if (in_array($bookingStatus, ['PENDING', 'FAILED', 'CANCELLED', 'REJECTED', ''], true)) {
            return false;
        }

        $payment = $booking->payment;
        if ($payment === null) {
            return false;
        }

        if (method_exists($payment, 'isCollected') && $payment->isCollected()) {
            return true;
        }

        $st = strtoupper(trim((string) ($payment->status ?? '')));

        return str_contains($st, 'PAID')
            || str_contains($st, 'COMPLETE')
            || str_contains($st, 'SUCCESS')
            || $st === 'ADVANCE';
    }
}
