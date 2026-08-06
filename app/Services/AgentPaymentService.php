<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Helpers\CommonHelper;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\Gateway;

/**
 * Start / verify live gateway payments for agent counter bookings.
 */
class AgentPaymentService
{
    /**
     * Charge agent fund wallet for an existing payment row (Fund gateway).
     *
     * @return array{success:bool,message:string,paid?:bool}
     */
    public function payWithFund(Agent $agent, Payment $payment, ?Request $request = null): array
    {
        if (! $this->ownsBooking($agent, $payment->booking ?? $payment->booking()->first())) {
            return ['success' => false, 'message' => __('Booking not found')];
        }

        $fundGatewayId = app(AgentCounterPaymentService::class)
            ->defaultGatewayId(AgentCounterPaymentService::METHOD_FUND);
        if ($fundGatewayId && (int) ($payment->gateway_id ?? 0) !== $fundGatewayId) {
            $payment->gateway_id = $fundGatewayId;
            $payment->payment_method = AgentCounterPaymentService::METHOD_FUND;
            $payment->save();
        }

        $data = [];
        CommonHelper::purseGatewayByCode(AgentCounterPaymentService::METHOD_FUND)
            ->create($payment, $request ?? request(), $data);

        return [
            'success' => ! empty($data['success']),
            'paid' => ! empty($data['paid']),
            'message' => $data['message'] ?? __('Fund payment failed'),
        ];
    }

    /**
     * @return array{success:bool,message:string,paymentURL?:string,paymentID?:string,order_id?:int,data?:array}
     */
    public function initiate(Agent $agent, int $orderId, int $gatewayId, ?Request $request = null): array
    {
        $request = $request ?? request();
        $data = ['success' => false, 'message' => __('Your payment cannot be processed'), 'data' => []];

        // Read from primary: confirm() just wrote this row; MySQL Router read
        // replicas can lag and make find()/ownsBooking fail with "Booking not found".
        $order = Booking::query()
            ->useWritePdo()
            ->with(['payment', 'bookingItems', 'customer'])
            ->find($orderId);
        if (! $order || ! $this->ownsBooking($agent, $order)) {
            $data['message'] = __('Booking not found');

            return $data;
        }

        $block = PendingBookingPaymentWindow::reasonPaymentBlocked($order);
        if ($block !== null) {
            $data['message'] = $block;

            return $data;
        }

        $payment = $order->payment;
        if (! $payment || ! ($payment->transaction_id ?? null)) {
            $payment = Payment::firstOrNew(['booking_id' => $order->id]);
            if (! $payment->id) {
                $payment->booking_id = $order->id;
                $payment->customer_id = $order->customer_id;
                $payment->transaction_id = strtoupper(uniqid($order->id.'_', false));
                $payment->status = 'pending';
            }
        }

        // Undo premature success written at booking create (before gateway pay).
        if ($order->status === AppConst::BOOKING_PENDING && ! $payment->isCollected()) {
            $payment->status = 'pending';
            $payment->bank_tran_id = null;
        }

        $payment->gateway_id = $gatewayId;
        $payable = (float) ($order->total_payable ?? 0);
        if (($payment->paid_amount === null || (float) $payment->paid_amount <= 0) && $payable > 0) {
            $payment->paid_amount = $payable;
            $payment->store_amount = $payable;
            $payment->dues = 0;
        }
        if ((float) ($payment->paid_amount ?? 0) <= 0) {
            $data['message'] = __('Invalid payment amount for this booking.');

            return $data;
        }
        $payment->save();

        $gateway = Gateway::query()->find($gatewayId);
        if (! $gateway) {
            $data['message'] = __('Invalid payment gateway.');

            return $data;
        }

        // Fund gateway_id from catalog → wallet debit path (no WebView).
        $gatewayCode = strtolower(trim((string) ($gateway->code ?: '')));
        if ($gatewayCode === AgentCounterPaymentService::METHOD_FUND) {
            return array_merge(
                $this->payWithFund($agent, $payment, $request),
                ['order_id' => $order->id]
            );
        }

        $data['data']['id'] = $payment->id;
        $data['data']['booking_id'] = $payment->booking_id;
        $data['data']['order_id'] = $order->id;
        $data['data']['transaction_id'] = $payment->transaction_id;
        $data['order_id'] = $order->id;

        $gwt = CommonHelper::purseGateway($gateway);
        $gwt->create($payment, $request, $data);

        foreach ($data as $key => $value) {
            if (! in_array($key, ['success', 'message', 'data'], true)) {
                $data['data'][$key] = $value;
            }
        }

        $url = $data['paymentURL']
            ?? ($data['data']['paymentURL'] ?? null)
            ?? ($data['data']['bkashURL'] ?? null);
        if (! empty($url)) {
            $data['success'] = true;
            $data['paymentURL'] = $url;
            $data['paymentID'] = $data['paymentID']
                ?? ($data['data']['paymentID'] ?? null);
            $data['message'] = $data['message'] ?: __('Open payment page to complete booking');
        }

        return $data;
    }

    /**
     * Poll / finalize after WebView returns.
     *
     * @return array{success:bool,message:string,paid:bool,status?:string,order_id?:int}
     */
    public function status(Agent $agent, int $orderId): array
    {
        $order = Booking::query()
            ->useWritePdo()
            ->with(['payment', 'bookingItems'])
            ->find($orderId);
        if (! $order || ! $this->ownsBooking($agent, $order)) {
            return [
                'success' => false,
                'paid' => false,
                'message' => __('Booking not found'),
            ];
        }

        $payment = $order->payment;
        if ($payment
            && $payment->isCollected()
            && $order->status === AppConst::BOOKING_PENDING) {
            $this->markBookingPaid($order, $payment);
            $order->refresh();
            $payment = $order->payment;
        } elseif ($payment
            && $order->status === AppConst::BOOKING_PENDING
            && ! $payment->isCollected()
            && strtolower((string) $payment->status) === 'success') {
            // Heal rows created before live-gateway payments stayed pending.
            $payment->update(['status' => 'pending', 'bank_tran_id' => null]);
            $payment->refresh();
        }

        $paid = $order->status === AppConst::BOOKING_COMPLETE
            || ($payment && $payment->isCollected());

        $payload = [
            'success' => true,
            'paid' => $paid,
            'status' => $order->status,
            'payment_status' => $payment
                ? $payment->displayStatusForBooking($order)
                : null,
            'order_id' => $order->id,
            'booking_id' => $order->id,
            'message' => $paid
                ? __('Payment successful')
                : __('Payment pending'),
        ];

        if ($paid) {
            $payload['invoice'] = \App\Support\BookingInvoice::signedUrl($order, 60);
            $payload['paid_amount'] = (float) ($payment?->paid_amount ?: $order->total_payable);
            $payload['transaction_id'] = $payment?->transaction_id;
        }

        return $payload;
    }

    public function markBookingPaid(Booking $booking, Payment $payment): void
    {
        if ($booking->status === AppConst::BOOKING_COMPLETE) {
            return;
        }

        app(BookingCompletionService::class)->complete($booking, $payment, [
            'paid_amount' => (float) ($payment->paid_amount ?: $booking->total_payable),
            'store_amount' => (float) ($payment->store_amount ?: $booking->total_payable),
            'dues' => 0,
        ]);
    }

    public function ownsBooking(Agent $agent, Booking $booking): bool
    {
        if ((int) $booking->booked_by_id !== (int) $agent->getKey()) {
            return false;
        }

        $type = (string) $booking->booked_by_type;

        return $type === Agent::class
            || $type === (string) $agent->getMorphClass();
    }
}
