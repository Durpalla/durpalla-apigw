<?php

namespace App\Gateways;

use App\Models\Agent;
use App\Models\Booking;
use App\Services\AgentCounterPaymentService;
use Illuminate\Support\Facades\DB;

/**
 * Agent wallet / fund payment — deducts balance and returns immediate success (no WebView).
 */
class Fund implements GatewayInterface
{
    public function create($payment, $request, &$data)
    {
        $data['success'] = false;
        $data['status'] = false;

        $agent = auth()->user();
        if (! ($agent instanceof Agent)) {
            $data['message'] = __('Only agents can pay with fund');

            return;
        }

        $booking = $payment->relationLoaded('booking')
            ? $payment->booking
            : $payment->booking()->first();

        if (! $booking) {
            $booking = Booking::query()->find($payment->booking_id);
        }

        if (! $booking) {
            $data['message'] = __('Booking not found');

            return;
        }

        try {
            DB::transaction(function () use ($agent, $booking, $payment, &$data) {
                app(AgentCounterPaymentService::class)->debitFund($agent, $booking);

                $amount = (float) $booking->total_payable;
                $payment->update([
                    'payment_method' => AgentCounterPaymentService::METHOD_FUND,
                    'status' => 'success',
                    'paid_amount' => $amount,
                    'store_amount' => $amount,
                    'dues' => 0,
                    'bank_tran_id' => $payment->bank_tran_id ?: ('FUND-'.$booking->id),
                ]);

                $data['success'] = true;
                $data['status'] = 'success';
                $data['paid'] = true;
                $data['message'] = __('Payment successful');
                $data['payment_method'] = AgentCounterPaymentService::METHOD_FUND;
                $data['order_id'] = $booking->id;
                $data['transaction_id'] = $payment->transaction_id;
            });
        } catch (\Throwable $e) {
            $data['success'] = false;
            $data['status'] = false;
            $data['paid'] = false;
            $data['message'] = $e->getMessage() ?: __('Fund payment failed');
        }
    }

    public function execute($payment, $request, &$data)
    {
        // Fund is completed in create(); execute is a no-op success for interface parity.
        if (strtolower((string) ($payment->status ?? '')) === 'success') {
            $data['status'] = true;
            $data['success'] = true;
            $data['message'] = __('Payment successful');

            return;
        }

        $this->create($payment, $request, $data);
        $data['status'] = ! empty($data['success']);
    }

    public function verify($payment, $request, &$data): void
    {
        $paid = strtolower((string) ($payment->status ?? '')) === 'success';
        $data['success'] = $paid;
        $data['paid'] = $paid;
        $data['message'] = $paid
            ? __('Payment successful')
            : __('Fund payment is not completed');
    }
}
