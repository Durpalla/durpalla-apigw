<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('invoice.company_name', 'Durpalla Limited') }} {{ __('invoice.title') }} #{{ $invoice['pnr'] ?? '' }}</title>
    @if (app()->getLocale() === 'bn')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;600;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    @endif
    <style>
        #booking-invoice-document,
        #booking-invoice-document * { box-sizing: border-box; }
        #booking-invoice-document {
            margin: 0;
            font-family: {{ app()->getLocale() === 'bn'
                ? "'Noto Sans Bengali', 'Noto Sans', 'FreeSans', Arial, sans-serif"
                : "Arial, Helvetica, sans-serif" }};
            color: #0f172a;
            background: #f1f5f9;
            font-size: 12px;
            line-height: 1.45;
            padding: 12px;
        }
        #booking-invoice-document .bi-sheet {
            max-width: 900px;
            margin: 0 auto;
            padding: 14px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        #booking-invoice-document .bi-header {
            display: grid;
            grid-template-columns: 1.4fr 1fr auto;
            gap: 12px;
            align-items: start;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        #booking-invoice-document .bi-merchant {
            display: flex;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        #booking-invoice-document .bi-logo {
            height: 64px;
            max-width: 84px;
            width: 64px;
            min-width: 48px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #1d4ed8;
            display: grid;
            place-items: center;
            font-size: 22px;
            font-weight: 700;
            overflow: hidden;
            flex-shrink: 0;
        }
        #booking-invoice-document .bi-logo img {
            height: 64px;
            width: auto;
            max-width: 84px;
            object-fit: contain;
            display: block;
        }
        #booking-invoice-document .bi-merchant-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px;
        }
        #booking-invoice-document .bi-merchant-meta {
            font-size: 11px;
            line-height: 1.5;
            color: #64748b;
        }
        #booking-invoice-document .bi-title-wrap { text-align: center; padding-top: 6px; }
        #booking-invoice-document .bi-title {
            margin: 0;
            font-size: 18px;
            letter-spacing: 0.05em;
            color: #1d4ed8;
            font-weight: 800;
        }
        #booking-invoice-document .bi-barcode {
            margin-top: 4px;
            font-family: ui-monospace, monospace;
            font-size: 12px;
            letter-spacing: 1px;
            color: #475569;
        }
        #booking-invoice-document .bi-qr { width: 78px; text-align: center; flex-shrink: 0; }
        #booking-invoice-document .bi-qr img {
            width: 68px;
            height: 68px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fff;
        }
        #booking-invoice-document .bi-qr span {
            display: block;
            margin-top: 3px;
            font-size: 10px;
            color: #94a3b8;
        }
        #booking-invoice-document .bi-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }
        #booking-invoice-document .bi-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            background: #fff;
        }
        #booking-invoice-document .bi-card-title {
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        #booking-invoice-document .bi-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 11px;
            margin-bottom: 4px;
        }
        #booking-invoice-document .bi-row span:first-child {
            color: #94a3b8;
            flex-shrink: 0;
        }
        #booking-invoice-document .bi-row strong {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }
        #booking-invoice-document .bi-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
        }
        #booking-invoice-document .bi-badge-success { background: #ecfdf5; color: #047857; }
        #booking-invoice-document .bi-badge-warning { background: #fffbeb; color: #b45309; }
        #booking-invoice-document .bi-badge-danger { background: #fef2f2; color: #b91c1c; }
        #booking-invoice-document .bi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 11px;
            margin: 0 0 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        #booking-invoice-document .bi-table th {
            background: #f8fafc;
            color: #475569;
            text-align: left;
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
        }
        #booking-invoice-document .bi-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        #booking-invoice-document .bi-table tr:last-child td { border-bottom: none; }
        #booking-invoice-document .bi-meta-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 14px;
            font-size: 11px;
            color: #475569;
            margin-bottom: 10px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fafafa;
        }
        #booking-invoice-document .bi-meta-line strong { color: #0f172a; }
        #booking-invoice-document .bi-seat {
            min-width: 32px;
            min-height: 24px;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            color: #1d4ed8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 11px;
            line-height: 1;
        }
        #booking-invoice-document .bi-fare-total {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #e2e8f0;
        }
        #booking-invoice-document .bi-fare-grand {
            font-size: 15px;
            font-weight: 800;
            color: #1d4ed8;
        }
        #booking-invoice-document .bi-policies {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
        }
        #booking-invoice-document .bi-policy-block {
            font-size: 11px;
            color: #475569;
            line-height: 1.5;
        }
        #booking-invoice-document .bi-policy-block strong {
            display: block;
            margin-bottom: 5px;
            font-size: 11px;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        #booking-invoice-document .bi-policy-block ul { margin: 0; padding-left: 16px; }
        #booking-invoice-document .bi-policy-block li { margin-bottom: 3px; }
        #booking-invoice-document .bi-footer { margin-top: 10px; }
        #booking-invoice-document .bi-third-party {
            margin-top: 10px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
            text-align: center;
        }
        #booking-invoice-document .bi-third-party strong { color: #475569; font-weight: 700; }
        #booking-invoice-document .bi-durpalla-contact {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 10px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }
        #booking-invoice-document .bi-durpalla-mark {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: #1d4ed8;
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 14px;
            flex-shrink: 0;
        }
        #booking-invoice-document .bi-durpalla-contact-title {
            margin: 0 0 4px;
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        #booking-invoice-document .bi-durpalla-contact-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 14px;
            font-size: 10px;
            color: #64748b;
            line-height: 1.45;
        }
        #booking-invoice-document .bi-powered-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            padding-top: 8px;
            border-top: 1px solid #f1f5f9;
            font-size: 10px;
            color: #94a3b8;
        }
        #booking-invoice-document .bi-powered-brand {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: #475569;
            font-size: 10px;
        }
        #booking-invoice-document .bi-disclaimer {
            margin: 6px 0 0;
            padding-bottom: 4px;
            text-align: center;
            font-size: 10px;
            color: #cbd5e1;
        }
        #booking-invoice-document .bi-durpalla-logo {
            height: 26px;
            width: auto;
            max-width: 140px;
            object-fit: contain;
            display: block;
            flex-shrink: 0;
        }
        #booking-invoice-document .bi-brand-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        #booking-invoice-document .bi-brand-row img {
            height: 28px;
            width: auto;
            max-width: 160px;
            object-fit: contain;
            display: block;
        }
        #booking-invoice-document .bi-brand-row .bi-brand-label {
            font-size: 10px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        #booking-invoice-document .bi-brand-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        #booking-invoice-document .bi-download {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            border-radius: 8px;
            background: #1d4ed8;
            color: #fff !important;
            text-decoration: none !important;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            border: 1px solid #1d4ed8;
            white-space: nowrap;
        }
        #booking-invoice-document .bi-download:hover {
            background: #1e40af;
            border-color: #1e40af;
        }
        #booking-invoice-document .bi-download svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }
        /* Mobile-only: never apply when printing (print preview width can be narrow). */
        @media screen and (max-width: 720px) {
            #booking-invoice-document {
                padding: 8px;
                overflow-x: hidden;
            }
            #booking-invoice-document .bi-sheet {
                padding: 12px;
                overflow: hidden;
            }
            #booking-invoice-document .bi-header {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            #booking-invoice-document .bi-title-wrap {
                order: 0;
                text-align: center;
                width: 100%;
            }
            #booking-invoice-document .bi-merchant {
                order: 1;
                width: 100%;
                min-width: 0;
            }
            #booking-invoice-document .bi-merchant > div:last-child {
                min-width: 0;
                overflow-wrap: anywhere;
            }
            #booking-invoice-document .bi-qr {
                order: 2;
                width: 100%;
                margin: 0 auto;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            #booking-invoice-document .bi-qr img {
                width: 96px;
                height: 96px;
            }
            #booking-invoice-document .bi-grid-3,
            #booking-invoice-document .bi-policies {
                grid-template-columns: 1fr;
            }
            #booking-invoice-document .bi-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            #booking-invoice-document .bi-brand-row img {
                max-width: 130px;
            }
            #booking-invoice-document .bi-brand-row {
                flex-wrap: wrap;
            }
            #booking-invoice-document .bi-brand-actions {
                width: 100%;
                justify-content: space-between;
            }
            #booking-invoice-document .bi-download {
                padding: 9px 14px;
            }
        }
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            #booking-invoice-document {
                padding: 0 !important;
                background: #fff !important;
                font-size: 10.5px !important;
                line-height: 1.35 !important;
            }
            #booking-invoice-document .bi-sheet {
                border: 0 !important;
                border-radius: 0 !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            #booking-invoice-document .bi-header {
                display: grid !important;
                grid-template-columns: 1.4fr 1fr auto !important;
                gap: 8px !important;
                margin-bottom: 8px !important;
                padding-bottom: 8px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            #booking-invoice-document .bi-grid-3 {
                display: grid !important;
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 8px !important;
                margin-bottom: 8px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            #booking-invoice-document .bi-policies {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            #booking-invoice-document .bi-card,
            #booking-invoice-document .bi-merchant,
            #booking-invoice-document .bi-meta-line,
            #booking-invoice-document .bi-footer,
            #booking-invoice-document .bi-third-party,
            #booking-invoice-document .bi-durpalla-contact,
            #booking-invoice-document .bi-table,
            #booking-invoice-document .bi-brand-row {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            #booking-invoice-document .bi-logo {
                height: 48px;
                width: 48px;
                max-width: 64px;
            }
            #booking-invoice-document .bi-logo img {
                height: 48px;
                max-width: 64px;
            }
            #booking-invoice-document .bi-qr img {
                width: 56px;
                height: 56px;
            }
            #booking-invoice-document .bi-download,
            #booking-invoice-document .bi-brand-actions .bi-download {
                display: none !important;
            }
            #booking-invoice-document .bi-title { font-size: 15px !important; }
            #booking-invoice-document .bi-merchant-name { font-size: 13px !important; }
            #booking-invoice-document .bi-card { padding: 8px !important; }
            #booking-invoice-document .bi-merchant { padding: 8px !important; }
            #booking-invoice-document .bi-table th,
            #booking-invoice-document .bi-table td { padding: 4px 6px !important; }
            #booking-invoice-document .bi-meta-line { margin-bottom: 8px !important; padding: 6px 8px !important; }
            #booking-invoice-document .bi-policies { margin-top: 8px !important; padding-top: 8px !important; }
            #booking-invoice-document .bi-footer { margin-top: 8px !important; }
            #booking-invoice-document img {
                max-width: 100% !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
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
    $payLabel = $isPaid
        ? __('invoice.status_paid')
        : ($isFailed
            ? __('invoice.status_failed')
            : (str_contains($payRaw, 'PARTIAL') ? __('invoice.status_partial') : __('invoice.status_pending')));
    $payBadge = $isPaid ? 'bi-badge-success' : ($isFailed ? 'bi-badge-danger' : 'bi-badge-warning');
    $invoiceCompany = (string) config('invoice.company_name', 'Durpalla Limited');
    $companyLogo = (string) ($invoice['company_logo_url'] ?? '');
    $merchantLogo = (string) ($merchant['logo_url'] ?? '');
    $companyLogoOk = $companyLogo !== '' && (
        str_starts_with($companyLogo, 'data:')
        || str_starts_with($companyLogo, 'http://')
        || str_starts_with($companyLogo, 'https://')
        || str_starts_with($companyLogo, '/')
        || is_file($companyLogo)
    );
    $merchantLogoOk = $merchantLogo !== '' && (
        str_starts_with($merchantLogo, 'data:')
        || str_starts_with($merchantLogo, 'http://')
        || str_starts_with($merchantLogo, 'https://')
        || str_starts_with($merchantLogo, '/')
        || is_file($merchantLogo)
    );
    $hasRealMerchant = ! empty($merchant['name']) && strcasecmp((string) $merchant['name'], $invoiceCompany) !== 0;
    $operatorName = $hasRealMerchant
        ? (string) $merchant['name']
        : $invoiceCompany;
    $operatorInitial = mb_strtoupper(mb_substr($operatorName !== '' ? $operatorName : 'M', 0, 1));
    $contactLines = array_values(array_unique(array_filter([
        (string) ($merchant['mobile'] ?? ''),
        (string) ($merchant['phone'] ?? ''),
    ])));
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
    $seats = [];
    foreach ($ticketsFlat as $ticket) {
        if (! empty($ticket['cabin_no'])) {
            $seats[] = $ticket['cabin_no'];
        }
    }
    $cancellationLines = $merchant['cancellation_policy_lines'] ?? [];
    if (! is_array($cancellationLines) || $cancellationLines === []) {
        $cancellationLines = [__('invoice.policy_fallback')];
    }
    $isHotelInvoice = ! empty($invoice['hotel']);
    $terms = $isHotelInvoice
        ? [
            __('invoice.term_hotel_checkin'),
            __('invoice.term_hotel_policy'),
            __('invoice.term_hotel_valid'),
        ]
        : [
            __('invoice.term_arrive'),
            __('invoice.term_id'),
            __('invoice.term_valid'),
        ];
    $money = static fn ($amount) => '৳'.number_format((float) $amount, 2);
    $gateway = (string) ($invoice['gateway_name'] ?? '');
    if ($gateway !== '' && strcasecmp($gateway, 'bkash') === 0) {
        $gateway = 'bKash';
    } elseif ($gateway !== '' && strcasecmp($gateway, 'nagad') === 0) {
        $gateway = 'Nagad';
    }
@endphp
<div id="booking-invoice-document">
    <div class="bi-sheet">
        <div class="bi-brand-row">
            <div>
                @if ($companyLogoOk)
                    <img src="{{ $companyLogo }}" alt="{{ $invoiceCompany }}">
                @else
                    <strong>{{ $invoiceCompany }}</strong>
                @endif
            </div>
            <div class="bi-brand-actions">
                <div class="bi-brand-label">{{ __('invoice.brand_label') }}</div>
                @if (!empty($invoice['download_url']))
                    <a class="bi-download" href="{{ $invoice['download_url'] }}" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3v12m0 0l4-4m-4 4l-4-4M4 21h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        {{ __('invoice.download_pdf') }}
                    </a>
                @endif
            </div>
        </div>

        <header class="bi-header">
            <div class="bi-merchant">
                <div class="bi-logo">
                    @if ($merchantLogoOk)
                        <img src="{{ $merchantLogo }}" alt="{{ $operatorName }}" referrerpolicy="no-referrer" loading="eager">
                    @else
                        {{ $operatorInitial }}
                    @endif
                </div>
                <div>
                    <h2 class="bi-merchant-name">{{ $operatorName }}</h2>
                    <div class="bi-merchant-meta">
                        @if (!empty($merchant['address']))
                            <div>{{ $merchant['address'] }}</div>
                        @endif
                        @if ($contactLines)
                            <div>{{ implode(' · ', $contactLines) }}</div>
                        @endif
                        @if (!empty($merchant['email']))
                            <div>{{ $merchant['email'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="bi-title-wrap">
                <h1 class="bi-title">{{ __('invoice.title_upper') }}</h1>
                <div class="bi-barcode">{{ $bookingRef }}</div>
            </div>
            <div class="bi-qr">
                <img src="{{ $invoice['qr'] ?? '' }}" alt="{{ __('invoice.scan_to_verify') }}">
                <span>{{ __('invoice.verify') }}</span>
            </div>
        </header>

        <div class="bi-grid-3">
            <section class="bi-card">
                <h3 class="bi-card-title">{{ __('invoice.section_booking') }}</h3>
                <div class="bi-row"><span>{{ __('invoice.label_id') }}</span><strong>{{ $bookingRef }}</strong></div>
                <div class="bi-row"><span>{{ __('invoice.label_date') }}</span><strong>{{ $invoice['booking_date_formated'] ?? '—' }}</strong></div>
                <div class="bi-row"><span>{{ __('invoice.label_transaction') }}</span><strong>{{ ($invoice['transaction_id'] ?? '') !== '' ? $invoice['transaction_id'] : '—' }}</strong></div>
                <div class="bi-row"><span>{{ __('invoice.label_payment') }}</span>
                    <span class="bi-badge {{ $payBadge }}">{{ $payLabel }}</span>
                </div>
                @if (!empty($invoice['agent']))
                    @if (($invoice['agent']['name'] ?? '') !== '')
                        <div class="bi-row"><span>{{ __('invoice.label_agent') }}</span><strong>{{ $invoice['agent']['name'] }}</strong></div>
                    @endif
                    @if (($invoice['agent']['mobile'] ?? '') !== '')
                        <div class="bi-row"><span>{{ __('invoice.label_agent_mobile') }}</span><strong>{{ $invoice['agent']['mobile'] }}</strong></div>
                    @endif
                @endif
            </section>

            <section class="bi-card">
                <h3 class="bi-card-title">{{ !empty($invoice['hotel']) ? __('invoice.section_stay') : __('invoice.section_trip') }}</h3>
                @if (!empty($invoice['hotel']))
                    <div class="bi-row"><span>{{ __('invoice.label_hotel') }}</span><strong>{{ $invoice['hotel']['name'] ?? $invoice['hotel']['title'] ?? __('invoice.hotel_fallback') }}</strong></div>
                    <div class="bi-row"><span>{{ __('invoice.label_room') }}</span><strong>{{ $invoice['hotel']['title'] ?? '—' }}</strong></div>
                    <div class="bi-row"><span>{{ __('invoice.label_check_in') }}</span><strong>{{ $invoice['hotel']['check_in'] ?? '—' }}</strong></div>
                    <div class="bi-row"><span>{{ __('invoice.label_check_out') }}</span><strong>{{ $invoice['hotel']['check_out'] ?? '—' }}</strong></div>
                    <div class="bi-row"><span>{{ __('invoice.label_guests') }}</span><strong>{{ $invoice['hotel']['guests_label'] ?? (__('invoice.adults', ['count' => (int) ($invoice['hotel']['adults'] ?? 0)]).(!empty($invoice['hotel']['children']) ? ', '.__('invoice.children', ['count' => (int) $invoice['hotel']['children']]) : '')) }}</strong></div>
                @else
                    <div class="bi-row"><span>{{ __('invoice.label_route') }}</span><strong>{{ $routeLabel }}</strong></div>
                    <div class="bi-row"><span>{{ __('invoice.label_vehicle') }}</span><strong>{{ $vehicleName }}</strong></div>
                    <div class="bi-row"><span>{{ __('invoice.label_date') }}</span><strong>{{ $scheduleDate }}</strong></div>
                    <div class="bi-row"><span>{{ __('invoice.label_departure') }}</span><strong>{{ $departure }}</strong></div>
                    <div class="bi-row"><span>{{ __('invoice.label_boarding') }}</span><strong>{{ $boarding }}</strong></div>
                @endif
            </section>

            <section class="bi-card">
                <h3 class="bi-card-title">{{ __('invoice.section_payment_fare') }}</h3>
                <div class="bi-row"><span>{{ __('invoice.label_method') }}</span><strong>{{ $gateway !== '' ? $gateway : '—' }}</strong></div>
                <div class="bi-row"><span>{{ __('invoice.subtotal') }}</span><strong>{{ $money($invoice['total_amount'] ?? 0) }}</strong></div>
                @if ((float) ($invoice['charge_total'] ?? 0) > 0)
                    <div class="bi-row"><span>{{ __('invoice.service_charge') }}</span><strong>{{ $money($invoice['charge_total']) }}</strong></div>
                @endif
                <div class="bi-row"><span>{{ __('invoice.vat_on_charge') }}</span><strong>{{ $money($invoice['vat_total'] ?? 0) }}</strong></div>
                @if ((float) ($invoice['total_discount'] ?? 0) > 0)
                    <div class="bi-row"><span>{{ __('invoice.discount') }}</span><strong>-{{ $money($invoice['total_discount']) }}</strong></div>
                @endif
                <div class="bi-fare-total">
                    <div class="bi-row">
                        <span>{{ __('invoice.total') }}</span>
                        <span class="bi-fare-grand">{{ $money($invoice['total_payable'] ?? 0) }}</span>
                    </div>
                    <div class="bi-row"><span>{{ __('invoice.paid_amount') }}</span><strong>{{ $isPaid ? $money($invoice['total_payable'] ?? 0) : $money(0) }}</strong></div>
                </div>
            </section>
        </div>

        <table class="bi-table">
            <thead>
            <tr>
                <th>#</th>
                <th>{{ $isHotelInvoice ? __('invoice.col_guest') : __('invoice.col_passenger') }}</th>
                <th>{{ __('invoice.col_phone') }}</th>
                <th>{{ $isHotelInvoice ? __('invoice.col_room') : __('invoice.col_seat_cabin') }}</th>
                <th>{{ __('invoice.col_type') }}</th>
                @unless($isHotelInvoice)
                    <th>{{ __('invoice.col_ac') }}</th>
                @endunless
                <th>{{ __('invoice.col_fare') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($ticketsFlat as $index => $ticket)
                @php
                    $passenger = is_array($ticket['passenger'] ?? null) ? $ticket['passenger'] : [];
                    $pName = (string) ($passenger['name'] ?? $customerName);
                    $pMobile = (string) ($passenger['mobile'] ?? $customerMobile);
                    $ac = array_key_exists('is_ac', $ticket)
                        ? (! empty($ticket['is_ac']) ? __('invoice.ac') : __('invoice.non_ac'))
                        : '—';
                    $typeLabel = (string) ($ticket['cabin_type'] ?? '');
                    if (strcasecmp($typeLabel, 'hotel') === 0) {
                        $typeLabel = __('invoice.hotel_fallback');
                    } else {
                        $typeLabel = ucfirst((string) ($ticket['seat_cabin_type'] ?? $ticket['cabin_type'] ?? '—'));
                    }
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $pName !== '' ? $pName : '—' }}</td>
                    <td>{{ $pMobile !== '' ? $pMobile : '—' }}</td>
                    <td>{{ !empty($ticket['cabin_no']) ? $ticket['cabin_no'] : '—' }}</td>
                    <td>{{ $typeLabel !== '' ? $typeLabel : '—' }}</td>
                    @unless($isHotelInvoice)
                        <td>{{ $ac }}</td>
                    @endunless
                    <td>{{ $money($ticket['price'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isHotelInvoice ? 6 : 7 }}">{{ __('invoice.no_ticket_lines') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="bi-meta-line">
            <span>
                <strong>{{ $isHotelInvoice ? __('invoice.label_room') : __('invoice.label_seats') }}:</strong>
                @if ($seats)
                    @foreach($seats as $seat)
                        <span class="bi-seat">{{ $seat }}</span>
                    @endforeach
                @else
                    —
                @endif
            </span>
            <span><strong>{{ __('invoice.label_status') }}:</strong> {{ $seal }}</span>
            <span><strong>{{ $isHotelInvoice ? __('invoice.label_hotel') : __('invoice.label_route') }}:</strong> {{ $isHotelInvoice ? ($invoice['hotel']['name'] ?? $routeLabel) : $routeLabel }}</span>
        </div>

        <div class="bi-policies">
            <div class="bi-policy-block">
                <strong>{{ __('invoice.terms_title') }}</strong>
                <ul>
                    @foreach($terms as $term)
                        <li>{{ $term }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="bi-policy-block">
                <strong>{{ __('invoice.cancellation_title') }}</strong>
                <ul>
                    @foreach($cancellationLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <footer class="bi-footer">
            <p class="bi-third-party">
                {{ __('invoice.footer_third_party', ['company' => $invoiceCompany, 'operator' => $operatorName]) }}
            </p>
            <div class="bi-durpalla-contact">
                @if ($companyLogoOk)
                    <img src="{{ $companyLogo }}" alt="{{ $invoiceCompany }}" class="bi-durpalla-logo">
                @else
                    <div class="bi-durpalla-mark">D</div>
                @endif
                <div>
                    <p class="bi-durpalla-contact-title">{{ __('invoice.footer_need_help_contact', ['company' => $invoiceCompany]) }}</p>
                    <div class="bi-durpalla-contact-meta">
                        <span><strong>{{ __('invoice.label_address') }}:</strong> {{ __('invoice.address_value') }}</span>
                        <span><strong>{{ __('invoice.label_email') }}:</strong> support@durpalla.com</span>
                        <span><strong>{{ __('invoice.label_hotline') }}:</strong> 16374</span>
                    </div>
                </div>
            </div>
            <div class="bi-powered-row">
                <span>{{ __('invoice.footer_thanks') }}</span>
                <span class="bi-powered-brand">
                    @if ($companyLogoOk)
                        <img src="{{ $companyLogo }}" alt="{{ $invoiceCompany }}" class="bi-durpalla-logo" style="height:18px;max-width:110px">
                    @else
                        {{ __('invoice.powered_by', ['company' => $invoiceCompany]) }}
                    @endif
                </span>
            </div>
            <p class="bi-disclaimer">{{ __('invoice.footer_computer') }}</p>
        </footer>
    </div>
</div>
</body>
</html>
