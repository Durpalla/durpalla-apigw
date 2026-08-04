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
            $booking = Booking::query()->findOrFail($id);
            $invoice = $builder->build($booking);
            $invoice['lang'] = $lang;

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
            $booking = Booking::query()->findOrFail($id);
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
        $fontData['lohitbengali'] = [
            'R' => 'Lohit-Bengali.ttf',
            'useOTL' => 0xFF,
        ];

        $defaultFont = 'dejavusans';
        if ($lang === BookingInvoice::LANG_BN && is_file(resource_path('fonts/Lohit-Bengali.ttf'))) {
            $defaultFont = 'lohitbengali';
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
        ]);
    }
}
