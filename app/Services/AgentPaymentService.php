<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Helpers\CommonHelper;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Events\BookingCompleteEvent;
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

        $order = Booking::with(['payment', 'bookingItems', 'customer'])->find($orderId);
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
        $order = Booking::with(['payment', 'bookingItems'])->find($orderId);
        if (! $order || ! $this->ownsBooking($agent, $order)) {
            return [
                'success' => false,
                'paid' => false,
                'message' => __('Booking not found'),
            ];
        }

        $payment = $order->payment;
        if ($payment && strtolower((string) $payment->status) === 'success'
            && $order->status === AppConst::BOOKING_PENDING) {
            $this->markBookingPaid($order, $payment);
            $order->refresh();
        }

        $paid = $order->status === AppConst::BOOKING_COMPLETE
            || ($payment && strtolower((string) $payment->status) === 'success');

        return [
            'success' => true,
            'paid' => $paid,
            'status' => $order->status,
            'payment_status' => $payment?->status,
            'order_id' => $order->id,
            'message' => $paid
                ? __('Payment successful')
                : __('Payment pending'),
        ];
    }

    public function markBookingPaid(Booking $booking, Payment $payment): void
    {
        if ($booking->status === AppConst::BOOKING_COMPLETE) {
            return;
        }

        $payment->update([
            'status' => 'success',
            'dues' => 0,
            'paid_amount' => (float) ($payment->paid_amount ?: $booking->total_payable),
            'store_amount' => (float) ($payment->store_amount ?: $booking->total_payable),
        ]);

        $booking->update(['status' => AppConst::BOOKING_COMPLETE]);
        BookingItem::query()
            ->where('booking_id', $booking->id)
            ->update(['status' => AppConst::BOOKING_ITEM_ACTIVE]);

        $booking->refresh();
        $booking->load(['bookingItems', 'customer', 'payment']);
        BookingCompleteEvent::dispatch($booking);
    }

    public function ownsBooking(Agent $agent, Booking $booking): bool
    {
        return (int) $booking->booked_by_id === (int) $agent->getKey()
            && (string) $booking->booked_by_type === (string) $agent->getMorphClass();
    }
}
