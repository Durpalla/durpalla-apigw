<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Merchant;
use App\Support\BookingInvoice;

/**
 * Builds invoice payload for HTML/PDF rendering (transport + hotel bookings).
 */
class InvoiceBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Booking $booking): array
    {
        $booking->loadMissing([
            'customer',
            'payment.gateway',
            'cancellations',
            'hotelReservation.roomType',
            'bookingItems.trip.route',
            'bookingItems.trip.launch.merchant',
            'bookingItems.trip.merchant',
            'bookingItems.trip.startingPoint.ghat',
            'bookingItems.trip.endingPoint.ghat',
            'bookingItems.item.cabinType',
            'bookingItems.vehicle.merchant',
        ]);

        $sealMap = config('constants.seals', []);
        $status = (string) ($booking->status ?? '');
        $payable = (float) ($booking->total_payable
            ?? ((float) $booking->total_amount + (float) $booking->vat_total + (float) $booking->charge_total - (float) $booking->total_discount));

        $payment = $booking->payment;
        $trx = (string) ($payment->transaction_id ?? '');
        $bookingReference = BookingInvoice::formatReference($booking);
        $merchant = $this->resolveMerchant($booking);
        $serviceType = (string) ($booking->service_type ?? 'transport');

        $invoice = [
            'id' => $booking->id,
            'pnr' => $booking->id,
            'booking_reference' => $bookingReference,
            'qr' => $this->qrUrl($bookingReference !== '' ? $bookingReference : (string) $booking->id),
            'qr_payload' => $bookingReference !== '' ? $bookingReference : (string) $booking->id,
            'booking_date' => optional($booking->created_at)->format('Y-m-d H:i:s'),
            'booking_date_formated' => optional($booking->created_at)->format('d M, Y h:i A'),
            'payment_status' => $payment
                ? $payment->displayStatusForBooking($booking)
                : ($booking->payment_status ?? ''),
            'transaction_id' => $trx,
            'gateway_name' => $payment?->gateway?->name
                ?? $payment?->payment_gateway
                ?? '',
            'total_amount' => (float) ($booking->total_amount ?? 0),
            'total_discount' => (float) ($booking->total_discount ?? 0),
            'vat_amount' => (float) ($booking->vat_amount ?? 0),
            'vat_total' => (float) ($booking->vat_total ?? 0),
            'charge_amount' => (float) ($booking->charge_amount ?? 0),
            'charge_total' => (float) ($booking->charge_total ?? 0),
            'total_payable' => number_format($payable, 2, '.', ''),
            'payment' => $payment,
            'customer' => $booking->customer,
            'seal' => $sealMap[$status] ?? strtoupper($status ?: 'PAID'),
            'service_type' => $serviceType,
            'items' => [],
            'hotel' => null,
            'merchant' => $this->formatMerchant($merchant, $serviceType),
            'company_logo_url' => $this->resolveCompanyLogoUrl(),
            'status' => $status,
        ];

        if ($booking->hotelReservation) {
            $res = $booking->hotelReservation;
            $invoice['hotel'] = [
                'title' => (string) ($res->roomType->title ?? $res->roomType->name ?? 'Hotel'),
                'check_in' => optional($res->check_in)->toDateString(),
                'check_out' => optional($res->check_out)->toDateString(),
                'adults' => (int) ($res->adults ?? 0),
                'children' => (int) ($res->children ?? 0),
            ];
        }

        $cancellations = [];
        foreach ($booking->cancellations ?? [] as $cancellation) {
            $cancellations = array_merge($cancellations, explode(',', (string) ($cancellation->items ?? '')));
        }

        foreach ($booking->bookingItems as $item) {
            $trip = $item->trip;
            if (! $trip) {
                continue;
            }

            $scheduleDate = date('d F Y', strtotime((string) $item->trip_date));
            $isReverse = ($trip->schedule_type ?? '') === 'reverse';
            $from = $isReverse
                ? (string) data_get($trip, 'endingPoint.ghat.name', '')
                : (string) data_get($trip, 'startingPoint.ghat.name', '');
            $to = $isReverse
                ? (string) data_get($trip, 'startingPoint.ghat.name', '')
                : (string) data_get($trip, 'endingPoint.ghat.name', '');

            $cabinLetter = data_get($item, 'item.cabinType.letter');
            $cabinNo = data_get($item, 'item.cabin_no');
            $row = [
                'id' => $item->id,
                'cabin_no' => ($cabinLetter && $cabinNo) ? ($cabinLetter.'-'.$cabinNo) : '',
                'cabin_type' => (string) $item->booking_type,
                'price' => (float) $item->price,
                'cabin_position' => $item->cabin_position,
                'discount' => (float) ($item->discount ?? 0),
                'is_ac' => data_get($item, 'item.cabinType.is_ac') ?? data_get($trip, 'launch.ac_available'),
                'vehicle_name' => (string) data_get($trip, 'launch.name', ''),
                'vehicle_type' => (string) data_get($trip, 'launch.vehicle_type', ''),
                'route_name' => $from && $to ? ($from.' - '.$to) : (string) data_get($trip, 'route.route_name', ''),
                'schedule_date' => $scheduleDate,
                'leaving_time' => $trip->leaving_at,
                'leaving_time_formated' => $trip->leaving_at ? date('h:i A', strtotime((string) $trip->leaving_at)) : '',
                'boarding_point' => json_decode((string) ($item->boarding_point ?? ''), true),
                'passenger' => json_decode((string) ($item->passenger ?? ''), true),
                'from' => $from,
                'to' => $to,
                'cancellable' => ((string) $item->trip_date >= date('Y-m-d'))
                    ? (! in_array((string) $item->id, $cancellations, true))
                    : false,
                'status' => $item->status,
                'seat_cabin_type' => (string) data_get($item, 'item.cabinType.name', $item->booking_type ?? ''),
            ];

            $invoice['items'][] = $row;
        }

        $grouped = $invoice['items'] ? _my_group_by_old($invoice['items'], 'schedule_date') : [];
        $tickets = [];
        foreach ($grouped as $date => $items) {
            $tickets[] = ['date' => $date, 'tickets' => $items];
        }
        $invoice['items'] = $tickets;

        return $invoice;
    }

    private function resolveMerchant(Booking $booking): ?Merchant
    {
        foreach ($booking->bookingItems as $item) {
            $merchant = data_get($item, 'trip.merchant')
                ?? data_get($item, 'trip.launch.merchant')
                ?? data_get($item, 'vehicle.merchant');
            if ($merchant instanceof Merchant) {
                return $merchant;
            }

            $merchantId = (int) (
                data_get($item, 'trip.merchant_id')
                ?: data_get($item, 'trip.launch.merchant_id')
                ?: data_get($item, 'vehicle.merchant_id')
                ?: 0
            );
            if ($merchantId > 0) {
                $found = Merchant::query()->withTrashed()->find($merchantId);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @return array{name:string,address:string,email:string,mobile:string,phone:string,registration_no:string,logo_url:?string,cancellation_policy_lines:list<string>}
     */
    private function formatMerchant(?Merchant $merchant, string $serviceType): array
    {
        if (! $merchant) {
            return [
                'name' => config('invoice.company_name', 'Durpalla Limited'),
                'address' => 'Dhaka, Bangladesh',
                'email' => 'support@durpalla.com',
                'mobile' => '16374',
                'phone' => '',
                'registration_no' => '',
                // Platform fallback only — never pretend Durpalla is the transport operator logo
                // when a merchant exists but has no logo uploaded.
                'logo_url' => null,
                'cancellation_policy_lines' => [
                    'Cancellation refunds follow the operator policy configured at booking time.',
                ],
            ];
        }

        $lines = app(MerchantCancellationPolicyResolver::class)
            ->invoicePolicyLines((int) $merchant->id, $serviceType ?: 'transport');

        if ($lines === []) {
            $lines = [
                'Cancellation refunds follow the operator policy configured at booking time.',
            ];
        }

        return [
            'name' => (string) ($merchant->merchant_name ?? ''),
            'address' => (string) ($merchant->merchant_address ?? ''),
            'email' => (string) ($merchant->merchant_email ?? ''),
            'mobile' => (string) ($merchant->merchant_mobile ?? ''),
            'phone' => (string) ($merchant->merchant_phone ?? ''),
            'registration_no' => (string) ($merchant->merchant_reg_no ?? ''),
            'logo_url' => $this->resolveMerchantLogoUrl($merchant),
            'cancellation_policy_lines' => $lines,
        ];
    }

    private function resolveCompanyLogoUrl(): ?string
    {
        $configured = trim((string) config('invoice.company_logo_url', ''));
        $candidates = array_values(array_filter([
            'logos/logo-horizontal-colored-premium.png',
            'logos/logo-horizontal-primary.png',
            $configured !== '' ? $configured : null,
        ]));

        foreach ($candidates as $candidate) {
            $embedded = $this->embedLocalAssetAsDataUri($candidate);
            if ($embedded !== null) {
                return $embedded;
            }
        }

        foreach ($candidates as $candidate) {
            if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
                $embedded = $this->embedRemoteAssetAsDataUri($candidate);
                if ($embedded !== null) {
                    return $embedded;
                }
            } else {
                foreach ($this->candidatePublicUrls($candidate) as $url) {
                    $embedded = $this->embedRemoteAssetAsDataUri($url);
                    if ($embedded !== null) {
                        return $embedded;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Write a data-URI / remote / local logo into mPDF temp dir so the PDF never depends
     * on live HTTP fetches (assets CDN often 403s; remote fetch can fail in containers).
     */
    public function materializeLogoForPdf(?string $src, string $tempDir, string $prefix): ?string
    {
        $src = trim((string) $src);
        if ($src === '') {
            return null;
        }

        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            return null;
        }

        $binary = null;
        $ext = 'png';

        if (str_starts_with($src, 'data:')) {
            if (! preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#s', $src, $m)) {
                return null;
            }
            $mime = strtolower($m[1]);
            $binary = base64_decode($m[2], true);
            $ext = match ($mime) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'png',
            };
        } elseif (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            $binary = $this->fetchBinary($src);
            if ($binary === null) {
                // assets.durpalla.com often 403 — retry known public hosts for same path.
                $path = $this->normalizeAssetPath($src);
                if ($path !== null) {
                    foreach ($this->candidatePublicUrls($path) as $alt) {
                        if (strcasecmp($alt, $src) === 0) {
                            continue;
                        }
                        $binary = $this->fetchBinary($alt);
                        if ($binary !== null) {
                            break;
                        }
                    }
                }
            }
        } elseif (is_file($src) && is_readable($src)) {
            $binary = @file_get_contents($src);
            $ext = pathinfo($src, PATHINFO_EXTENSION) ?: 'png';
        } else {
            $embedded = $this->embedLocalAssetAsDataUri($src);
            if ($embedded !== null) {
                return $this->materializeLogoForPdf($embedded, $tempDir, $prefix);
            }
            foreach ($this->candidatePublicUrls($src) as $url) {
                $binary = $this->fetchBinary($url);
                if ($binary !== null) {
                    break;
                }
            }
        }

        if ($binary === false || $binary === null || $binary === '' || strlen($binary) > 1024000) {
            return null;
        }

        $path = rtrim($tempDir, '/').'/'.$prefix.'-'.substr(md5($src), 0, 16).'.'.$ext;
        if (@file_put_contents($path, $binary) === false) {
            return null;
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    private function candidatePublicUrls(string $pathOrUrl): array
    {
        $path = $this->normalizeAssetPath($pathOrUrl);
        if ($path === null) {
            return [];
        }

        $bases = array_values(array_unique(array_filter([
            rtrim((string) config('invoice.assets_base_url', ''), '/'),
            rtrim((string) config('uploads.public_base_url', ''), '/'),
            'https://admin.durpalla.com',
            'https://web.durpalla.com',
            rtrim((string) config('app.url', ''), '/'),
        ])));

        $urls = [];
        foreach ($bases as $base) {
            if ($base === '') {
                continue;
            }
            $urls[] = $base.'/'.ltrim($path, '/');
        }

        return $urls;
    }

    private function fetchBinary(string $url): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'follow_location' => 1,
                    'user_agent' => 'DurpallaInvoice/1.0',
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $binary = @file_get_contents($url, false, $context);
            if ($binary === false || $binary === '' || strlen($binary) > 1024000) {
                return null;
            }
            // Cloudflare 403 body is tiny; reject non-images.
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($binary);
            if (! is_string($detected) || ! str_starts_with($detected, 'image/')) {
                return null;
            }

            return $binary;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveMerchantLogoUrl(Merchant $merchant): ?string
    {
        $path = (string) ($merchant->logo ?? '');
        if ($path === '') {
            return null;
        }

        // Normalize legacy bare filenames to public/images/{file}.
        $normalized = $this->normalizeMerchantLogoPath($path);

        // Prefer inlined data URI so WebView / print never depend on a remote host.
        $embedded = $this->embedLocalAssetAsDataUri($normalized);
        if ($embedded !== null) {
            return $embedded;
        }

        // Try known public hosts (assets CDN currently 403s for logos/).
        foreach ($this->candidatePublicUrls($normalized) as $url) {
            $remoteEmbedded = $this->embedRemoteAssetAsDataUri($url);
            if ($remoteEmbedded !== null) {
                return $remoteEmbedded;
            }
        }

        $absolute = $this->absoluteAssetUrl($normalized);
        if ($absolute !== null) {
            $remoteEmbedded = $this->embedRemoteAssetAsDataUri($absolute);
            if ($remoteEmbedded !== null) {
                return $remoteEmbedded;
            }
            // Prefer admin host over broken assets CDN for the raw URL fallback.
            $pathOnly = $this->normalizeAssetPath($normalized);
            if ($pathOnly !== null && str_starts_with($pathOnly, 'logos/')) {
                return 'https://admin.durpalla.com/'.$pathOnly;
            }
        }

        return $absolute;
    }

    private function embedRemoteAssetAsDataUri(string $url): ?string
    {
        $binary = $this->fetchBinary($url);
        if ($binary === null) {
            return null;
        }

        $mime = 'image/png';
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($binary);
        if (is_string($detected) && str_starts_with($detected, 'image/')) {
            $mime = $detected;
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function normalizeMerchantLogoPath(string $pathOrUrl): string
    {
        $pathOrUrl = trim($pathOrUrl);
        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        $normalized = ltrim(str_replace('\\', '/', $pathOrUrl), '/');
        if ($normalized === '') {
            return $normalized;
        }

        // Legacy admin uploads: filename only → images/{filename}
        if (! str_contains($normalized, '/')) {
            return 'images/'.$normalized;
        }

        return $normalized;
    }

    private function embedLocalAssetAsDataUri(string $pathOrUrl): ?string
    {
        $normalized = $this->normalizeAssetPath($pathOrUrl);
        if ($normalized === null) {
            return null;
        }

        foreach ((array) config('invoice.local_asset_roots', []) as $root) {
            $root = rtrim((string) $root, '/');
            if ($root === '') {
                continue;
            }
            $full = $root.'/'.$normalized;
            if (! is_file($full) || ! is_readable($full)) {
                continue;
            }

            $mime = @mime_content_type($full) ?: 'image/png';
            if (! str_starts_with($mime, 'image/')) {
                continue;
            }

            $binary = @file_get_contents($full);
            if ($binary === false || $binary === '') {
                continue;
            }

            // Keep invoices small; skip huge logos.
            if (strlen($binary) > 512000) {
                continue;
            }

            return 'data:'.$mime.';base64,'.base64_encode($binary);
        }

        return null;
    }

    private function absoluteAssetUrl(string $pathOrUrl): ?string
    {
        $pathOrUrl = trim($pathOrUrl);
        if ($pathOrUrl === '') {
            return null;
        }

        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            // Rewrite known broken apigw-hosted logo URLs to the assets base.
            $normalized = $this->normalizeAssetPath($pathOrUrl);
            if ($normalized !== null && str_starts_with($normalized, 'logos/')) {
                $host = (string) parse_url($pathOrUrl, PHP_URL_HOST);
                $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
                if ($host !== '' && $appHost !== '' && strcasecmp($host, $appHost) === 0) {
                    $base = rtrim((string) config('invoice.assets_base_url', 'https://admin.durpalla.com'), '/');

                    return $base.'/'.$normalized;
                }
            }

            return $pathOrUrl;
        }

        $normalized = $this->normalizeAssetPath($pathOrUrl);
        if ($normalized === null) {
            return null;
        }

        $invoiceBase = rtrim((string) config('invoice.assets_base_url', ''), '/');
        // Prefer admin origin for logos — assets.durpalla.com currently 403s logo paths.
        if (str_starts_with($normalized, 'logos/')) {
            return 'https://admin.durpalla.com/'.$normalized;
        }

        if ($invoiceBase !== '') {
            return $invoiceBase.'/'.$normalized;
        }

        $uploadsBase = rtrim((string) config('uploads.public_base_url', ''), '/');
        if ($uploadsBase !== '' && str_starts_with($normalized, 'logos/')) {
            return $uploadsBase.'/'.$normalized;
        }

        if (function_exists('upload_asset')) {
            $viaUpload = upload_asset($normalized);
            if (is_string($viaUpload) && $viaUpload !== '') {
                return $viaUpload;
            }
        }

        return rtrim((string) config('app.url', ''), '/').'/'.$normalized;
    }

    private function normalizeAssetPath(string $pathOrUrl): ?string
    {
        $pathOrUrl = trim($pathOrUrl);
        if ($pathOrUrl === '') {
            return null;
        }

        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            $path = (string) parse_url($pathOrUrl, PHP_URL_PATH);
            $pathOrUrl = $path !== '' ? $path : $pathOrUrl;
        }

        $normalized = ltrim(str_replace('\\', '/', $pathOrUrl), '/');
        if ($normalized === '' || str_contains($normalized, '..')) {
            return null;
        }

        return $normalized;
    }

    /**
     * Resolve logo bytes for mPDF imageVars (never a remote URL).
     */
    public function resolveLogoBinary(mixed $src): ?string
    {
        $src = is_string($src) ? trim($src) : '';
        if ($src === '') {
            // Fall through to company default candidates when empty string was company config.
            foreach (['logos/logo-horizontal-colored-premium.png', 'logos/logo-horizontal-primary.png'] as $candidate) {
                $embedded = $this->embedLocalAssetAsDataUri($candidate);
                if ($embedded !== null) {
                    return $this->binaryFromDataUri($embedded);
                }
                foreach ($this->candidatePublicUrls($candidate) as $url) {
                    $binary = $this->fetchBinary($url);
                    if ($binary !== null) {
                        return $binary;
                    }
                }
            }

            return null;
        }

        if (str_starts_with($src, 'data:')) {
            return $this->binaryFromDataUri($src);
        }

        if (is_file($src) && is_readable($src)) {
            $binary = @file_get_contents($src);

            return ($binary !== false && $binary !== '') ? $binary : null;
        }

        // Already a data URI / absolute path from build().
        $tempDir = storage_path('app/mpdf');
        $file = $this->materializeLogoForPdf($src, $tempDir, 'bin-'.substr(md5($src), 0, 10));
        if ($file !== null && is_file($file)) {
            $binary = @file_get_contents($file);

            return ($binary !== false && $binary !== '') ? $binary : null;
        }

        return null;
    }

    public function resolveQrBinary(string $payload): ?string
    {
        $tempDir = storage_path('app/mpdf');
        $file = $this->materializeQrForPdf($payload, $tempDir, 'qr-bin');
        if ($file !== null && is_file($file)) {
            $binary = @file_get_contents($file);

            return ($binary !== false && $binary !== '') ? $binary : null;
        }

        return $this->fetchBinary($this->qrUrl($payload));
    }

    private function binaryFromDataUri(string $dataUri): ?string
    {
        if (! preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,(.+)$#s', $dataUri, $m)) {
            return null;
        }
        $binary = base64_decode($m[1], true);
        if ($binary === false || $binary === '') {
            return null;
        }

        return $binary;
    }

    private function qrUrl(string $payload): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data='.urlencode($payload);
    }

    /**
     * Generate a local QR PNG for mPDF (avoids remote api.qrserver.com in PDF).
     */
    public function materializeQrForPdf(string $payload, string $tempDir, string $prefix): ?string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            return null;
        }

        $path = rtrim($tempDir, '/').'/'.$prefix.'-'.substr(md5($payload), 0, 12).'.png';
        if (is_file($path) && filesize($path) > 0) {
            return $path;
        }

        // Prefer remote QR generation then cache locally.
        $remote = $this->fetchBinary($this->qrUrl($payload));
        if ($remote !== null && @file_put_contents($path, $remote) !== false) {
            return $path;
        }

        return null;
    }
}
