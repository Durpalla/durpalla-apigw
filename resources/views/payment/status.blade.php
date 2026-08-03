@extends('payment.layout')

@php
    $status = strtolower((string) ($payment->status ?? 'pending'));
    $isSuccess = $status === 'success';
    $isFailed = in_array($status, ['fail', 'failed', 'cancelled', 'canceled'], true);
    $tone = $isSuccess ? 'success' : ($isFailed ? 'failed' : 'pending');
    $amount = number_format((float) ($payment->paid_amount ?? $payment->amount ?? 0), 2);
    $currency = strtoupper((string) ($payment->currency ?: 'BDT'));
    $gatewayName = $payment->gateway?->name;
    $bookingId = $payment->booking_id;
    $compact = ! empty($isAppClient);
@endphp

@section('title', $isSuccess ? 'Payment successful' : ($isFailed ? 'Payment failed' : 'Payment status'))

@section('body_class', $compact ? 'app-compact' : '')

@section('content')
<main class="card" role="main">
    <div class="card-body">
        <div class="status-icon-wrap {{ $tone }}">
            <div class="status-icon {{ $tone }}" aria-hidden="true">
                @include('payment._logo', ['tone' => $tone, 'size' => 'hero'])
            </div>
        </div>

        @if ($isSuccess)
            <span class="badge success">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                Verified
            </span>
            <h1>Payment successful</h1>
            <p class="subtitle">{{ $compact ? 'Your booking is confirmed.' : 'Your booking is confirmed. A receipt will reach your registered contact details.' }}</p>
        @elseif ($isFailed)
            <span class="badge failed">Not completed</span>
            <h1>Payment not completed</h1>
            <p class="subtitle">{{ $compact ? 'No charge was confirmed. Try again from the app.' : 'No charge was confirmed. You can safely try again from the app.' }}</p>
        @else
            <span class="badge pending">Processing</span>
            <h1>Payment {{ $payment->nice_status }}</h1>
            <p class="subtitle">{{ $compact ? 'Confirming with your payment gateway…' : 'We are confirming your payment with the gateway. This may take a moment.' }}</p>
        @endif

        <div class="receipt">
            <div class="receipt-head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Secure receipt
            </div>
            <div class="receipt-body">
                <div class="row">
                    <span class="label">Amount</span>
                    <span class="value">{{ $currency }} {{ $amount }}</span>
                </div>
                @if ($bookingId)
                    <div class="row">
                        <span class="label">Booking</span>
                        <span class="value">#{{ $bookingId }}</span>
                    </div>
                @endif
                @if ($gatewayName)
                    <div class="row">
                        <span class="label">Gateway</span>
                        <span class="value">{{ $gatewayName }}</span>
                    </div>
                @endif
                @if ($payment->transaction_id)
                    <div class="row">
                        <span class="label">Reference</span>
                        <span class="value mono">{{ $payment->transaction_id }}</span>
                    </div>
                @endif
                <div class="row">
                    <span class="label">Date</span>
                    <span class="value">{{ $payment->created_at?->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</span>
                </div>
                <div class="row">
                    <span class="label">Status</span>
                    <span class="value">{{ $payment->nice_status }}</span>
                </div>
            </div>
        </div>

        @if ($isSuccess)
            <p class="hint">You may close this screen and return to the app.</p>
        @elseif ($isFailed)
            <p class="hint">If money was deducted, it will be reversed per your gateway&apos;s policy.</p>
        @endif
    </div>

    <div class="trust" aria-label="Security">
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            Encrypted
        </span>
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Secure
        </span>
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            Verified
        </span>
    </div>
</main>
@endsection
