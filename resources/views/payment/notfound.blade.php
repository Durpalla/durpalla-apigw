@extends('payment.layout')

@section('title', 'Payment not found')

@section('content')
<main class="card" role="main">
    <div class="card-body">
        <div class="status-icon-wrap neutral">
            <div class="status-icon neutral" aria-hidden="true">
                @include('payment._logo', ['tone' => 'neutral', 'size' => 'hero'])
            </div>
        </div>

        <span class="badge pending">Unavailable</span>
        <h1>Payment not found</h1>
        <p class="subtitle">We could not find payment details for this link. If you were charged, check your bookings or contact support.</p>

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
                    <span class="label">Reference</span>
                    <span class="value">N/A</span>
                </div>
                <div class="row">
                    <span class="label">Status</span>
                    <span class="value">No record</span>
                </div>
            </div>
        </div>
    </div>

    <div class="trust" aria-label="Security">
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Secure checkout
        </span>
    </div>
</main>
@endsection
