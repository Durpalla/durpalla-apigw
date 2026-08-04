<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ config('invoice.company_name', 'Durpalla Limited') }} {{ __('invoice.title') }} #{{ $invoice['pnr'] ?? '' }}</title>
    <style>
        body { font-family: {{ (app()->getLocale() === 'bn') ? 'notosansbengali, freesans, sans-serif' : 'DejaVu Sans, sans-serif' }}; font-size: 11px; color: #0f172a; @if(app()->getLocale() === 'bn') font-weight: normal; @endif }
        h1 { font-size: 16px; color: #1d4ed8; margin: 0 0 4px; font-family: inherit; @if(app()->getLocale() === 'bn') font-weight: normal; @endif }
        h2 { font-size: 13px; margin: 0 0 4px; font-family: inherit; @if(app()->getLocale() === 'bn') font-weight: normal; @endif }
        .muted { color: #64748b; }
        .header { width: 100%; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 12px; }
        .header td { vertical-align: top; }
        .logo { max-height: 52px; max-width: 140px; }
        .logo-sm { max-height: 22px; max-width: 110px; }
        .box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; }
        .section { margin-bottom: 10px; }
        .label { color: #94a3b8; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th, table.items td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; font-family: inherit; }
        table.items th { background: #f8fafc; font-size: 10px; color: #475569; @if(app()->getLocale() !== 'bn') text-transform: uppercase; @endif @if(app()->getLocale() === 'bn') font-weight: normal; @endif }
        .totals td { padding: 3px 0; font-family: inherit; }
        .grand { font-size: 14px; color: #1d4ed8; @if(app()->getLocale() === 'bn') font-weight: normal; @else font-weight: bold; @endif }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; @if(app()->getLocale() === 'bn') font-weight: normal; @else font-weight: bold; @endif }
        .badge-ok { background: #ecfdf5; color: #047857; }
        .badge-warn { background: #fffbeb; color: #b45309; }
        .badge-fail { background: #fef2f2; color: #b91c1c; }
        .policies { width: 100%; margin-top: 12px; }
        .policy { border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; vertical-align: top; }
        .policy strong { display: block; margin-bottom: 4px; font-size: 11px; font-family: inherit; @if(app()->getLocale() === 'bn') font-weight: normal; @endif }
        .policy ul { margin: 0; padding-left: 14px; }
        .policy li { margin-bottom: 2px; color: #475569; font-size: 10px; }
        .footer { margin-top: 14px; border-top: 1px solid #e2e8f0; padding-top: 8px; color: #64748b; font-size: 10px; }
        .qr { width: 70px; height: 70px; }
        .latin { font-family: freesans, DejaVu Sans, sans-serif; }
        .initial {
            width: 48px; height: 48px; line-height: 48px; text-align: center;
            background: #eff6ff; color: #1d4ed8; font-size: 20px; font-weight: bold;
            border-radius: 6px;
        }
        @if(app()->getLocale() === 'bn')
        /* Force Noto Sans Bengali into every table cell — mPDF often drops inherited fonts in nested tables. */
        table, td, th, div, span, strong, b, h1, h2, li, p {
            font-family: notosansbengali !important;
            font-weight: normal !important;
        }
        .latin, .latin * { font-family: freesans !important; }
        @else
        strong, b { font-family: inherit; }
        @endif
    </style>
</head>
<body>
@php
    $isBn = app()->getLocale() === 'bn';
    $latin = static function ($value) use ($isBn): string {
        $value = (string) $value;
        if ($value === '' || ! $isBn) {
            return e($value);
        }

        return '<span class="latin">'.e($value).'</span>';
    };
    $customer = $invoice['customer'] ?? null;
    $payment = $invoice['payment'] ?? null;
    $merchant = $invoice['merchant'] ?? [];
    $customerName = is_object($customer) ? ($customer->name ?? '—') : ($customer['name'] ?? '—');
    $customerMobile = is_object($customer) ? ($customer->mobile ?? '—') : ($customer['mobile'] ?? '—');
    $bookingRef = (string) ($invoice['booking_reference'] ?? ('#'.($invoice['pnr'] ?? '')));
    $payRaw = strtoupper((string) ($invoice['payment_status'] ?? (is_object($payment) ? ($payment->status ?? '') : '')));
    $seal = strtoupper((string) ($invoice['seal'] ?? $payRaw));
    $isPaid = str_contains($seal, 'PAID') || str_contains($payRaw, 'PAID') || str_contains($payRaw, 'COMPLETE') || str_contains($payRaw, 'SUCCESS');
    $isFailed = str_contains($seal, 'FAIL') || str_contains($payRaw, 'FAIL');
    $payLabel = $isPaid
        ? __('invoice.status_paid')
        : ($isFailed
            ? __('invoice.status_failed')
            : (str_contains($payRaw, 'PARTIAL') ? __('invoice.status_partial') : __('invoice.status_pending')));
    $payBadge = $isPaid ? 'badge-ok' : ($isFailed ? 'badge-fail' : 'badge-warn');
    $invoiceCompany = (string) config('invoice.company_name', 'Durpalla Limited');
    $pdfImages = is_array($invoice['pdf_images'] ?? null) ? $invoice['pdf_images'] : [];
    $pdfUris = is_array($invoice['pdf_data_uris'] ?? null) ? $invoice['pdf_data_uris'] : [];
    // Absolute temp file paths (preferred), else mPDF imageVars.
    $merchantLogo = (string) ($pdfUris['merchant'] ?? '');
    $companyLogo = (string) ($pdfUris['company'] ?? '');
    $qrSrc = (string) ($pdfUris['qr'] ?? '');
    $qrSvg = is_string($invoice['qr_svg'] ?? null) ? trim($invoice['qr_svg']) : '';
    if ($merchantLogo === '' && ! empty($pdfImages['merchant'])) {
        $merchantLogo = 'var:merchantLogo';
    }
    if ($companyLogo === '' && ! empty($pdfImages['company'])) {
        $companyLogo = 'var:companyLogo';
    }
    // Only use imageVars for QR when we have PNG bytes (not SVG-only).
    if ($qrSrc === '' && $qrSvg === '' && ! empty($pdfImages['qr'])) {
        $qrSrc = 'var:invoiceQr';
    }
    $merchantLogoOk = $merchantLogo !== '';
    $companyLogoOk = $companyLogo !== '';
    $qrOk = $qrSrc !== '' || $qrSvg !== '';
    $hasRealMerchant = ! empty($merchant['name']) && strcasecmp((string) $merchant['name'], $invoiceCompany) !== 0;
    $operatorName = $hasRealMerchant
        ? (string) $merchant['name']
        : $invoiceCompany;
    $operatorInitial = mb_strtoupper(mb_substr($operatorName !== '' ? $operatorName : 'M', 0, 1));
    $ticketsFlat = [];
    foreach (($invoice['items'] ?? []) as $group) {
        foreach (($group['tickets'] ?? []) as $ticket) {
            $ticketsFlat[] = $ticket;
        }
    }
    $firstTicket = $ticketsFlat[0] ?? null;
    $routeLabel = $firstTicket['route_name']
        ?? (($firstTicket['from'] ?? null) && ($firstTicket['to'] ?? null)
            ? ($firstTicket['from'].' → '.$firstTicket['to'])
            : '—');
    $vehicleName = (string) ($firstTicket['vehicle_name'] ?? '—');
    $scheduleDate = (string) ($firstTicket['schedule_date'] ?? '—');
    $departure = (string) ($firstTicket['leaving_time_formated'] ?? '—');
    $boarding = is_array($firstTicket['boarding_point'] ?? null)
        ? (string) ($firstTicket['boarding_point']['name'] ?? '—')
        : '—';
    $cancellationLines = $merchant['cancellation_policy_lines'] ?? [];
    if (! is_array($cancellationLines) || $cancellationLines === []) {
        $cancellationLines = [__('invoice.policy_fallback')];
    }
    $terms = [
        __('invoice.term_arrive'),
        __('invoice.term_id'),
        __('invoice.term_valid'),
    ];
    $money = static function ($amount) use ($latin) {
        return $latin('BDT '.number_format((float) $amount, 2));
    };
    $gateway = (string) ($invoice['gateway_name'] ?? '');
@endphp

<table class="header">
    <tr>
        <td width="50%">
            <table>
                <tr>
                    <td width="56" style="vertical-align:middle;">
                        @if ($merchantLogoOk)
                            <img class="logo" src="{{ $merchantLogo }}" alt="{{ $operatorName }}">
                        @else
                            <div class="initial">{{ $operatorInitial }}</div>
                        @endif
                    </td>
                    <td style="padding-left:8px; vertical-align:middle;">
                        <h2>{!! $latin($operatorName) !!}</h2>
                        <div class="muted">{!! $latin($merchant['address'] ?? '') !!}</div>
                        <div class="muted">{!! $latin($merchant['mobile'] ?? ($merchant['phone'] ?? '')) !!}</div>
                        <div class="muted">{!! $latin($merchant['email'] ?? '') !!}</div>
                    </td>
                </tr>
            </table>
        </td>
        <td width="35%" style="text-align:center;">
            <h1>{{ __('invoice.title_upper') }}</h1>
            <div><strong>{!! $latin($bookingRef) !!}</strong></div>
            <div class="muted">{!! $latin($invoice['booking_date_formated'] ?? '') !!}</div>
        </td>
        <td width="15%" style="text-align:right;">
            @if ($qrSrc !== '')
                <img class="qr" src="{{ $qrSrc }}" alt="QR">
            @elseif ($qrSvg !== '')
                <div class="qr" style="width:70px;height:70px;overflow:hidden;">{!! $qrSvg !!}</div>
            @endif
        </td>
    </tr>
</table>

<table width="100%" class="section">
    <tr>
        <td width="33%" class="box">
            <strong>{{ __('invoice.section_booking') }}</strong><br>
            <span class="label">{{ __('invoice.label_id') }}:</span> {!! $latin($bookingRef) !!}<br>
            <span class="label">{{ __('invoice.label_transaction') }}:</span> {!! $latin(($invoice['transaction_id'] ?? '') !== '' ? $invoice['transaction_id'] : '—') !!}<br>
            <span class="label">{{ __('invoice.label_payment') }}:</span> <span class="badge {{ $payBadge }}">{{ $payLabel }}</span>
        </td>
        <td width="2%"></td>
        <td width="33%" class="box">
            <strong>{{ __('invoice.section_trip') }}</strong><br>
            @if (! empty($invoice['hotel']))
                <span class="label">{{ __('invoice.label_hotel') }}:</span> {!! $latin($invoice['hotel']['title'] ?? __('invoice.hotel_fallback')) !!}<br>
                <span class="label">{{ __('invoice.label_check_in') }}:</span> {!! $latin($invoice['hotel']['check_in'] ?? '—') !!}<br>
                <span class="label">{{ __('invoice.label_check_out') }}:</span> {!! $latin($invoice['hotel']['check_out'] ?? '—') !!}
            @else
                <span class="label">{{ __('invoice.label_route') }}:</span> {!! $latin($routeLabel) !!}<br>
                <span class="label">{{ __('invoice.label_vehicle') }}:</span> {!! $latin($vehicleName) !!}<br>
                <span class="label">{{ __('invoice.label_date') }}:</span> {!! $latin($scheduleDate.' · '.$departure) !!}<br>
                <span class="label">{{ __('invoice.label_boarding') }}:</span> {!! $latin($boarding) !!}
            @endif
        </td>
        <td width="2%"></td>
        <td width="30%" class="box">
            <strong>{{ __('invoice.section_customer') }}</strong><br>
            {!! $latin($customerName) !!}<br>
            {!! $latin($customerMobile) !!}<br>
            <span class="label">{{ __('invoice.label_method') }}:</span> {!! $latin($gateway !== '' ? $gateway : '—') !!}
            @if (! empty($invoice['agent']))
                <br><br>
                <strong>{{ __('invoice.section_agent') }}</strong><br>
                @if (($invoice['agent']['name'] ?? '') !== '')
                    {!! $latin($invoice['agent']['name']) !!}<br>
                @endif
                @if (($invoice['agent']['mobile'] ?? '') !== '')
                    <span class="label">{{ __('invoice.label_agent_mobile') }}:</span> {!! $latin($invoice['agent']['mobile']) !!}
                @endif
            @endif
        </td>
    </tr>
</table>

<table class="items">
    <thead>
    <tr>
        <td style="background:#f8fafc;font-size:10px;color:#475569;border:1px solid #e2e8f0;padding:6px;font-family:notosansbengali;">#</td>
        <td style="background:#f8fafc;font-size:10px;color:#475569;border:1px solid #e2e8f0;padding:6px;font-family:notosansbengali;">{{ __('invoice.col_passenger') }}</td>
        <td style="background:#f8fafc;font-size:10px;color:#475569;border:1px solid #e2e8f0;padding:6px;font-family:notosansbengali;">{{ __('invoice.col_phone') }}</td>
        <td style="background:#f8fafc;font-size:10px;color:#475569;border:1px solid #e2e8f0;padding:6px;font-family:notosansbengali;">{{ __('invoice.col_seat_cabin') }}</td>
        <td style="background:#f8fafc;font-size:10px;color:#475569;border:1px solid #e2e8f0;padding:6px;font-family:notosansbengali;">{{ __('invoice.col_type') }}</td>
        <td style="background:#f8fafc;font-size:10px;color:#475569;border:1px solid #e2e8f0;padding:6px;font-family:notosansbengali;">{{ __('invoice.col_fare') }}</td>
    </tr>
    </thead>
    <tbody>
    @forelse($ticketsFlat as $index => $ticket)
        @php
            $passenger = is_array($ticket['passenger'] ?? null) ? $ticket['passenger'] : [];
            $pName = (string) ($passenger['name'] ?? $customerName);
            $pMobile = (string) ($passenger['mobile'] ?? $customerMobile);
        @endphp
        <tr>
            <td>{!! $latin((string) ($index + 1)) !!}</td>
            <td>{!! $latin($pName !== '' ? $pName : '—') !!}</td>
            <td>{!! $latin($pMobile !== '' ? $pMobile : '—') !!}</td>
            <td>{!! $latin(! empty($ticket['cabin_no']) ? $ticket['cabin_no'] : '—') !!}</td>
            <td>{!! $latin(ucfirst((string) ($ticket['seat_cabin_type'] ?? $ticket['cabin_type'] ?? '—'))) !!}</td>
            <td>{!! $money($ticket['price'] ?? 0) !!}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6">{{ __('invoice.no_ticket_lines') }}</td>
        </tr>
    @endforelse
    </tbody>
</table>

<table width="100%" class="totals section" style="margin-top:12px;">
    <tr>
        <td width="60%"></td>
        <td width="40%">
            <table width="100%">
                <tr><td style="font-family:notosansbengali;">{{ __('invoice.subtotal') }}</td><td align="right">{!! $money($invoice['total_amount'] ?? 0) !!}</td></tr>
                @if ((float) ($invoice['charge_total'] ?? 0) > 0)
                    <tr><td style="font-family:notosansbengali;">{{ __('invoice.service_charge') }}</td><td align="right">{!! $money($invoice['charge_total']) !!}</td></tr>
                @endif
                <tr><td style="font-family:notosansbengali;">{{ __('invoice.vat_on_charge') }}</td><td align="right">{!! $money($invoice['vat_total'] ?? 0) !!}</td></tr>
                @if ((float) ($invoice['total_discount'] ?? 0) > 0)
                    <tr><td style="font-family:notosansbengali;">{{ __('invoice.discount') }}</td><td align="right">-{!! $money($invoice['total_discount']) !!}</td></tr>
                @endif
                <tr>
                    <td class="grand" style="font-family:notosansbengali;">{{ __('invoice.total') }}</td>
                    <td class="grand" align="right">{!! $money($invoice['total_payable'] ?? 0) !!}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="policies" width="100%">
    <tr>
        <td width="49%" class="policy">
            <strong>{{ __('invoice.terms_title') }}</strong>
            <ul>
                @foreach ($terms as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ul>
        </td>
        <td width="2%"></td>
        <td width="49%" class="policy">
            <strong>{{ __('invoice.cancellation_title') }}</strong>
            <ul>
                @foreach ($cancellationLines as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </td>
    </tr>
</table>

<div class="footer">
    {!! str_replace(
        ['__COMPANY__', '__OPERATOR__'],
        ['<span class="latin">'.e($invoiceCompany).'</span>', '<span class="latin">'.e($operatorName).'</span>'],
        e(__('invoice.footer_third_party', ['company' => '__COMPANY__', 'operator' => '__OPERATOR__']))
    ) !!}
    <br>
    {!! str_replace(
        '__STATUS__',
        '<span class="latin">'.e($seal).'</span>',
        e(__('invoice.footer_computer_status', ['status' => '__STATUS__']))
    ) !!}
    <br><br>
    @if ($companyLogoOk)
        <img class="logo-sm" src="{{ $companyLogo }}" alt="{{ $invoiceCompany }}">
        &nbsp;
    @endif
    {!! str_replace(
        '__COMPANY__',
        '<span class="latin">'.e($invoiceCompany).'</span>',
        e(__('invoice.footer_need_help', ['company' => '__COMPANY__']))
    ) !!}
</div>
</body>
</html>
