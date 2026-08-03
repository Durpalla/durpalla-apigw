<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} Invoice #{{ $invoice['pnr'] ?? '' }}</title>
    <style>
        :root {
            --brand: #0077B6;
            --text: #1F2937;
            --muted: #6B7280;
            --line: #E5E7EB;
            --bg: #F5F7FA;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--text);
            background: var(--bg);
            padding: 16px;
        }
        .sheet {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 20px;
            background: linear-gradient(135deg, #E6F4FB, #fff);
            border-bottom: 1px solid var(--line);
        }
        .brand { font-size: 20px; font-weight: 700; color: var(--brand); }
        .seal {
            align-self: flex-start;
            background: #22C55E;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            padding: 6px 10px;
            border-radius: 999px;
        }
        .section { padding: 16px 20px; border-bottom: 1px solid var(--line); }
        .section:last-child { border-bottom: 0; }
        .label { color: var(--muted); font-size: 12px; margin-bottom: 4px; }
        .value { font-size: 15px; font-weight: 600; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .ticket {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px;
            margin-top: 10px;
            background: #FAFBFC;
        }
        .ticket h4 { margin: 0 0 8px; font-size: 14px; color: var(--brand); }
        .row { display: flex; justify-content: space-between; gap: 8px; margin: 4px 0; font-size: 13px; }
        .row span:last-child { font-weight: 600; text-align: right; }
        .totals .row { font-size: 14px; }
        .totals .grand { font-size: 16px; color: var(--brand); font-weight: 700; margin-top: 8px; }
        .footer { padding: 14px 20px; color: var(--muted); font-size: 12px; text-align: center; }
        @media (max-width: 560px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@php
    $customer = $invoice['customer'] ?? null;
    $payment = $invoice['payment'] ?? null;
@endphp
<div class="sheet">
    <div class="header">
        <div>
            <div class="brand">{{ config('app.name', 'Durpalla') }}</div>
            <div class="label" style="margin-top:6px">Invoice / Ticket</div>
            <div class="value">PNR #{{ $invoice['pnr'] ?? '—' }}</div>
        </div>
        <div class="seal">{{ $invoice['seal'] ?? 'PAID' }}</div>
    </div>

    <div class="section">
        <div class="grid">
            <div>
                <div class="label">Customer</div>
                <div class="value">{{ is_object($customer) ? ($customer->name ?? '—') : ($customer['name'] ?? '—') }}</div>
                <div class="label" style="margin-top:8px">Mobile</div>
                <div class="value">{{ is_object($customer) ? ($customer->mobile ?? '—') : ($customer['mobile'] ?? '—') }}</div>
            </div>
            <div>
                <div class="label">Booked at</div>
                <div class="value">{{ $invoice['booking_date_formated'] ?? '—' }}</div>
                <div class="label" style="margin-top:8px">Payment</div>
                <div class="value">{{ strtoupper((string) ($invoice['payment_status'] ?? (is_object($payment) ? ($payment->status ?? '') : ''))) ?: '—' }}</div>
            </div>
        </div>
    </div>

    @if (!empty($invoice['hotel']))
        <div class="section">
            <div class="label">Hotel stay</div>
            <div class="value">{{ $invoice['hotel']['title'] ?? 'Hotel' }}</div>
            <div class="row"><span>Check-in</span><span>{{ $invoice['hotel']['check_in'] ?? '—' }}</span></div>
            <div class="row"><span>Check-out</span><span>{{ $invoice['hotel']['check_out'] ?? '—' }}</span></div>
            <div class="row"><span>Guests</span><span>{{ ($invoice['hotel']['adults'] ?? 0) }} adults{{ !empty($invoice['hotel']['children']) ? ', '.$invoice['hotel']['children'].' children' : '' }}</span></div>
        </div>
    @endif

    <div class="section">
        <div class="label">Tickets / items</div>
        @forelse(($invoice['items'] ?? []) as $group)
            <div class="ticket">
                <h4>{{ $group['date'] ?? 'Trip' }}</h4>
                @foreach(($group['tickets'] ?? []) as $ticket)
                    <div class="row">
                        <span>
                            {{ ucfirst((string) ($ticket['cabin_type'] ?? 'item')) }}
                            @if (!empty($ticket['cabin_no'])) ({{ $ticket['cabin_no'] }}) @endif
                            @if (!empty($ticket['vehicle_name'])) · {{ $ticket['vehicle_name'] }} @endif
                        </span>
                        <span>৳{{ number_format((float) ($ticket['price'] ?? 0), 2) }}</span>
                    </div>
                    @if (!empty($ticket['route_name']))
                        <div class="row"><span>Route</span><span>{{ $ticket['route_name'] }}</span></div>
                    @endif
                    @if (!empty($ticket['leaving_time_formated']))
                        <div class="row"><span>Departure</span><span>{{ $ticket['leaving_time_formated'] }}</span></div>
                    @endif
                @endforeach
            </div>
        @empty
            <div class="value" style="margin-top:8px;font-weight:500;color:var(--muted)">No ticket lines</div>
        @endforelse
    </div>

    <div class="section totals">
        <div class="row"><span>Subtotal</span><span>৳{{ number_format((float) ($invoice['total_amount'] ?? 0), 2) }}</span></div>
        <div class="row"><span>Service charge</span><span>৳{{ number_format((float) ($invoice['charge_total'] ?? 0), 2) }}</span></div>
        <div class="row"><span>VAT</span><span>৳{{ number_format((float) ($invoice['vat_total'] ?? 0), 2) }}</span></div>
        @if ((float) ($invoice['total_discount'] ?? 0) > 0)
            <div class="row"><span>Discount</span><span>-৳{{ number_format((float) $invoice['total_discount'], 2) }}</span></div>
        @endif
        <div class="row grand"><span>Total payable</span><span>৳{{ $invoice['total_payable'] ?? '0.00' }}</span></div>
    </div>

    <div class="footer">Thank you for booking with {{ config('app.name', 'Durpalla') }}.</div>
</div>
</body>
</html>
