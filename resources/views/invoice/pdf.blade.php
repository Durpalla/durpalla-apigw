<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('invoice.company_name', 'Durpalla Limited') }} Invoice #{{ $invoice['pnr'] ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 16px; color: #1d4ed8; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 0 0 4px; }
        .muted { color: #64748b; }
        .header { width: 100%; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 12px; }
        .header td { vertical-align: top; }
        .logo { max-height: 52px; max-width: 140px; }
        .logo-sm { max-height: 22px; max-width: 110px; }
        .box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; }
        .section { margin-bottom: 10px; }
        .label { color: #94a3b8; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th, table.items td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; }
        table.items th { background: #f8fafc; font-size: 10px; text-transform: uppercase; color: #475569; }
        .totals td { padding: 3px 0; }
        .grand { font-size: 14px; font-weight: bold; color: #1d4ed8; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: bold; }
        .badge-ok { background: #ecfdf5; color: #047857; }
        .badge-warn { background: #fffbeb; color: #b45309; }
        .badge-fail { background: #fef2f2; color: #b91c1c; }
        .policies { width: 100%; margin-top: 12px; }
        .policy { border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; vertical-align: top; }
        .policy strong { display: block; margin-bottom: 4px; font-size: 11px; }
        .policy ul { margin: 0; padding-left: 14px; }
        .policy li { margin-bottom: 2px; color: #475569; font-size: 10px; }
        .footer { margin-top: 14px; border-top: 1px solid #e2e8f0; padding-top: 8px; color: #64748b; font-size: 10px; }
        .qr { width: 70px; height: 70px; }
        .initial {
            width: 48px; height: 48px; line-height: 48px; text-align: center;
            background: #eff6ff; color: #1d4ed8; font-size: 20px; font-weight: bold;
            border-radius: 6px;
        }
    </style>
</head>
<body>
@php
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
    $payLabel = $isPaid ? 'Paid' : ($isFailed ? 'Failed' : (str_contains($payRaw, 'PARTIAL') ? 'Partial' : 'Pending'));
    $payBadge = $isPaid ? 'badge-ok' : ($isFailed ? 'badge-fail' : 'badge-warn');
    $invoiceCompany = (string) config('invoice.company_name', 'Durpalla Limited');
    $pdfImages = is_array($invoice['pdf_images'] ?? null) ? $invoice['pdf_images'] : [];
    // Prefer mPDF imageVars (var:name) — set by FrontController from binary bytes.
    $merchantLogoOk = ! empty($pdfImages['merchant']);
    $companyLogoOk = ! empty($pdfImages['company']);
    $qrOk = ! empty($pdfImages['qr']);
    $merchantLogo = $merchantLogoOk ? 'var:merchantLogo' : '';
    $companyLogo = $companyLogoOk ? 'var:companyLogo' : '';
    $qrSrc = $qrOk ? 'var:invoiceQr' : '';
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
    $cancellationLines = $merchant['cancellation_policy_lines'] ?? [
        'Cancellation refunds follow the operator policy configured at booking time.',
    ];
    if (! is_array($cancellationLines) || $cancellationLines === []) {
        $cancellationLines = [
            'Cancellation refunds follow the operator policy configured at booking time.',
        ];
    }
    $terms = [
        'Arrive at the boarding point at least 30 minutes before departure.',
        'Valid ID and this booking reference are required at boarding.',
        'This invoice is valid only for the trip and passengers listed above.',
    ];
    $money = static fn ($amount) => 'BDT '.number_format((float) $amount, 2);
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
                        <h2>{{ $operatorName }}</h2>
                        <div class="muted">{{ $merchant['address'] ?? '' }}</div>
                        <div class="muted">{{ $merchant['mobile'] ?? ($merchant['phone'] ?? '') }}</div>
                        <div class="muted">{{ $merchant['email'] ?? '' }}</div>
                    </td>
                </tr>
            </table>
        </td>
        <td width="35%" style="text-align:center;">
            <h1>BOOKING INVOICE</h1>
            <div><strong>{{ $bookingRef }}</strong></div>
            <div class="muted">{{ $invoice['booking_date_formated'] ?? '' }}</div>
        </td>
        <td width="15%" style="text-align:right;">
            @if ($qrOk)
                <img class="qr" src="{{ $qrSrc }}" alt="QR">
            @endif
        </td>
    </tr>
</table>

<table width="100%" class="section">
    <tr>
        <td width="33%" class="box">
            <strong>Booking</strong><br>
            <span class="label">ID:</span> {{ $bookingRef }}<br>
            <span class="label">Transaction:</span> {{ ($invoice['transaction_id'] ?? '') !== '' ? $invoice['transaction_id'] : '—' }}<br>
            <span class="label">Payment:</span> <span class="badge {{ $payBadge }}">{{ $payLabel }}</span>
        </td>
        <td width="2%"></td>
        <td width="33%" class="box">
            <strong>Trip</strong><br>
            @if (! empty($invoice['hotel']))
                <span class="label">Hotel:</span> {{ $invoice['hotel']['title'] ?? 'Hotel' }}<br>
                <span class="label">Check-in:</span> {{ $invoice['hotel']['check_in'] ?? '—' }}<br>
                <span class="label">Check-out:</span> {{ $invoice['hotel']['check_out'] ?? '—' }}
            @else
                <span class="label">Route:</span> {{ $routeLabel }}<br>
                <span class="label">Vehicle:</span> {{ $vehicleName }}<br>
                <span class="label">Date:</span> {{ $scheduleDate }} · {{ $departure }}<br>
                <span class="label">Boarding:</span> {{ $boarding }}
            @endif
        </td>
        <td width="2%"></td>
        <td width="30%" class="box">
            <strong>Customer</strong><br>
            {{ $customerName }}<br>
            {{ $customerMobile }}<br>
            <span class="label">Method:</span> {{ $gateway !== '' ? $gateway : '—' }}
        </td>
    </tr>
</table>

<table class="items">
    <thead>
    <tr>
        <th>#</th>
        <th>Passenger</th>
        <th>Phone</th>
        <th>Seat / Cabin</th>
        <th>Type</th>
        <th>Fare</th>
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
            <td>{{ $index + 1 }}</td>
            <td>{{ $pName !== '' ? $pName : '—' }}</td>
            <td>{{ $pMobile !== '' ? $pMobile : '—' }}</td>
            <td>{{ ! empty($ticket['cabin_no']) ? $ticket['cabin_no'] : '—' }}</td>
            <td>{{ ucfirst((string) ($ticket['seat_cabin_type'] ?? $ticket['cabin_type'] ?? '—')) }}</td>
            <td>{{ $money($ticket['price'] ?? 0) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6">No ticket lines</td>
        </tr>
    @endforelse
    </tbody>
</table>

<table width="100%" class="totals section" style="margin-top:12px;">
    <tr>
        <td width="60%"></td>
        <td width="40%">
            <table width="100%">
                <tr><td>Subtotal</td><td align="right">{{ $money($invoice['total_amount'] ?? 0) }}</td></tr>
                @if ((float) ($invoice['charge_total'] ?? 0) > 0)
                    <tr><td>Service charge</td><td align="right">{{ $money($invoice['charge_total']) }}</td></tr>
                @endif
                <tr><td>VAT on charge</td><td align="right">{{ $money($invoice['vat_total'] ?? 0) }}</td></tr>
                @if ((float) ($invoice['total_discount'] ?? 0) > 0)
                    <tr><td>Discount</td><td align="right">-{{ $money($invoice['total_discount']) }}</td></tr>
                @endif
                <tr>
                    <td class="grand">Total</td>
                    <td class="grand" align="right">{{ $money($invoice['total_payable'] ?? 0) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="policies" width="100%">
    <tr>
        <td width="49%" class="policy">
            <strong>Terms &amp; Conditions</strong>
            <ul>
                @foreach ($terms as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ul>
        </td>
        <td width="2%"></td>
        <td width="49%" class="policy">
            <strong>Cancellation Policy</strong>
            <ul>
                @foreach ($cancellationLines as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </td>
    </tr>
</table>

<div class="footer">
    This booking was made through {{ $invoiceCompany }}, a third-party online booking platform.
    Transport service is operated by {{ $operatorName }}.
    <br>Computer-generated invoice. No signature required. Status: {{ $seal }}
    <br><br>
    @if ($companyLogoOk)
        <img class="logo-sm" src="{{ $companyLogo }}" alt="{{ $invoiceCompany }}">
        &nbsp;
    @endif
    Need help? {{ $invoiceCompany }} · support@durpalla.com · Hotline 16374
</div>
</body>
</html>
