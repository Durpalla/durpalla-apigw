<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('invoice.company_name', 'Durpalla Limited') }} Invoice #{{ $invoice['pnr'] ?? '' }}</title>
    <style>
        #booking-invoice-document,
        #booking-invoice-document * { box-sizing: border-box; }
        #booking-invoice-document {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
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
        #booking-invoice-document .bi-qr { width: 78px; text-align: center; }
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
        @media (max-width: 720px) {
            #booking-invoice-document .bi-header { grid-template-columns: 1fr auto; }
            #booking-invoice-document .bi-title-wrap { grid-column: 1 / -1; order: -1; text-align: left; }
            #booking-invoice-document .bi-grid-3,
            #booking-invoice-document .bi-policies { grid-template-columns: 1fr; }
            #booking-invoice-document .bi-table { display: block; overflow-x: auto; }
        }
        @media print {
            @page { margin: 10mm; }
            body { background: #fff; }
            #booking-invoice-document { padding: 0; background: #fff; }
            #booking-invoice-document .bi-sheet { border: 0; max-width: none; }
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
    $payBadge = $isPaid ? 'bi-badge-success' : ($isFailed ? 'bi-badge-danger' : 'bi-badge-warning');
    $invoiceCompany = (string) config('invoice.company_name', 'Durpalla Limited');
    $operatorName = (string) ($merchant['name'] ?? $invoiceCompany);
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
    $cancellationLines = $merchant['cancellation_policy_lines'] ?? [
        'Cancellation refunds follow the operator policy configured at booking time.',
    ];
    $terms = [
        'Arrive at the boarding point at least 30 minutes before departure.',
        'Valid ID and this booking reference are required at boarding.',
        'This invoice is valid only for the trip and passengers listed above.',
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
        <header class="bi-header">
            <div class="bi-merchant">
                <div class="bi-logo">
                    @if (!empty($merchant['logo_url']))
                        <img src="{{ $merchant['logo_url'] }}" alt="{{ $operatorName }}">
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
                <h1 class="bi-title">BOOKING INVOICE</h1>
                <div class="bi-barcode">{{ $bookingRef }}</div>
            </div>
            <div class="bi-qr">
                <img src="{{ $invoice['qr'] ?? '' }}" alt="Scan to verify">
                <span>Verify</span>
            </div>
        </header>

        <div class="bi-grid-3">
            <section class="bi-card">
                <h3 class="bi-card-title">Booking</h3>
                <div class="bi-row"><span>ID</span><strong>{{ $bookingRef }}</strong></div>
                <div class="bi-row"><span>Date</span><strong>{{ $invoice['booking_date_formated'] ?? '—' }}</strong></div>
                <div class="bi-row"><span>Transaction</span><strong>{{ ($invoice['transaction_id'] ?? '') !== '' ? $invoice['transaction_id'] : '—' }}</strong></div>
                <div class="bi-row">
                    <span>Payment</span>
                    <span class="bi-badge {{ $payBadge }}">{{ $payLabel }}</span>
                </div>
            </section>

            <section class="bi-card">
                <h3 class="bi-card-title">Trip</h3>
                @if (!empty($invoice['hotel']))
                    <div class="bi-row"><span>Hotel</span><strong>{{ $invoice['hotel']['title'] ?? 'Hotel' }}</strong></div>
                    <div class="bi-row"><span>Check-in</span><strong>{{ $invoice['hotel']['check_in'] ?? '—' }}</strong></div>
                    <div class="bi-row"><span>Check-out</span><strong>{{ $invoice['hotel']['check_out'] ?? '—' }}</strong></div>
                    <div class="bi-row"><span>Guests</span><strong>{{ ($invoice['hotel']['adults'] ?? 0) }} adults{{ !empty($invoice['hotel']['children']) ? ', '.$invoice['hotel']['children'].' children' : '' }}</strong></div>
                @else
                    <div class="bi-row"><span>Route</span><strong>{{ $routeLabel }}</strong></div>
                    <div class="bi-row"><span>Vehicle</span><strong>{{ $vehicleName }}</strong></div>
                    <div class="bi-row"><span>Date</span><strong>{{ $scheduleDate }}</strong></div>
                    <div class="bi-row"><span>Departure</span><strong>{{ $departure }}</strong></div>
                    <div class="bi-row"><span>Boarding</span><strong>{{ $boarding }}</strong></div>
                @endif
            </section>

            <section class="bi-card">
                <h3 class="bi-card-title">Payment &amp; Fare</h3>
                <div class="bi-row"><span>Method</span><strong>{{ $gateway !== '' ? $gateway : '—' }}</strong></div>
                <div class="bi-row"><span>Subtotal</span><strong>{{ $money($invoice['total_amount'] ?? 0) }}</strong></div>
                @if ((float) ($invoice['charge_total'] ?? 0) > 0)
                    <div class="bi-row"><span>Service charge</span><strong>{{ $money($invoice['charge_total']) }}</strong></div>
                @endif
                <div class="bi-row"><span>VAT</span><strong>{{ $money($invoice['vat_total'] ?? 0) }}</strong></div>
                @if ((float) ($invoice['total_discount'] ?? 0) > 0)
                    <div class="bi-row"><span>Discount</span><strong>-{{ $money($invoice['total_discount']) }}</strong></div>
                @endif
                <div class="bi-fare-total">
                    <div class="bi-row">
                        <span>Total</span>
                        <span class="bi-fare-grand">{{ $money($invoice['total_payable'] ?? 0) }}</span>
                    </div>
                    <div class="bi-row"><span>Paid</span><strong>{{ $isPaid ? $money($invoice['total_payable'] ?? 0) : $money(0) }}</strong></div>
                </div>
            </section>
        </div>

        <table class="bi-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Passenger</th>
                <th>Phone</th>
                <th>Seat / Cabin</th>
                <th>Type</th>
                <th>AC</th>
                <th>Fare</th>
            </tr>
            </thead>
            <tbody>
            @forelse($ticketsFlat as $index => $ticket)
                @php
                    $passenger = is_array($ticket['passenger'] ?? null) ? $ticket['passenger'] : [];
                    $pName = (string) ($passenger['name'] ?? $customerName);
                    $pMobile = (string) ($passenger['mobile'] ?? $customerMobile);
                    $ac = array_key_exists('is_ac', $ticket)
                        ? (! empty($ticket['is_ac']) ? 'AC' : 'Non-AC')
                        : '—';
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $pName !== '' ? $pName : '—' }}</td>
                    <td>{{ $pMobile !== '' ? $pMobile : '—' }}</td>
                    <td>{{ !empty($ticket['cabin_no']) ? $ticket['cabin_no'] : '—' }}</td>
                    <td>{{ ucfirst((string) ($ticket['seat_cabin_type'] ?? $ticket['cabin_type'] ?? '—')) }}</td>
                    <td>{{ $ac }}</td>
                    <td>{{ $money($ticket['price'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No ticket lines</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="bi-meta-line">
            <span>
                <strong>Seats:</strong>
                @if ($seats)
                    @foreach($seats as $seat)
                        <span class="bi-seat">{{ $seat }}</span>
                    @endforeach
                @else
                    —
                @endif
            </span>
            <span><strong>Status:</strong> {{ $seal }}</span>
            <span><strong>Route:</strong> {{ $routeLabel }}</span>
        </div>

        <div class="bi-policies">
            <div class="bi-policy-block">
                <strong>Terms &amp; Conditions</strong>
                <ul>
                    @foreach($terms as $term)
                        <li>{{ $term }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="bi-policy-block">
                <strong>Cancellation Policy</strong>
                <ul>
                    @foreach($cancellationLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <footer class="bi-footer">
            <p class="bi-third-party">
                This booking was made through <strong>{{ $invoiceCompany }}</strong>, a third-party
                online booking platform. Transport service is operated by
                <strong>{{ $operatorName }}</strong>.
            </p>
            <div class="bi-durpalla-contact">
                <div class="bi-durpalla-mark">D</div>
                <div>
                    <p class="bi-durpalla-contact-title">Need help? Contact {{ $invoiceCompany }}</p>
                    <div class="bi-durpalla-contact-meta">
                        <span><strong>Address:</strong> Dhaka, Bangladesh</span>
                        <span><strong>Email:</strong> support@durpalla.com</span>
                        <span><strong>Hotline:</strong> 16374</span>
                    </div>
                </div>
            </div>
            <div class="bi-powered-row">
                <span>Thank you for your booking. Have a safe journey.</span>
                <span class="bi-powered-brand">Powered by {{ $invoiceCompany }}</span>
            </div>
            <p class="bi-disclaimer">Computer-generated invoice. No signature required.</p>
        </footer>
    </div>
</div>
</body>
</html>
