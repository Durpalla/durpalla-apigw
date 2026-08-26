<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Jobs\SendPaymentLinkJob;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Gateway\Constants\GatewayConstant;
use Modules\Gateway\Entities\Gateway;
use Modules\Gateway\Services\GatewayCatalogService;

class MerchantBookingPaymentService
{
    public function __construct(private readonly GatewayCatalogService $catalog)
    {
    }

    /**
     * Ensure booking has a payment_token and return the public merchant pay URL.
     *
     * @return array{url:string,token:string,external_reference:string}
     */
    public function createPaymentLink(Booking $booking, bool $dispatchNotify = true): array
    {
        if (empty($booking->payment_token)) {
            $booking->payment_token = (string) Str::uuid();
            $booking->save();
        }

        $ref = 'MPAY-'.$booking->id.'-'.Str::upper(Str::random(8));
        $payment = $booking->payment;
        if (! $payment) {
            $total = (float) ($booking->total_payable ?? $booking->total_amount);
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'paid_amount' => 0,
                'dues' => max(0, $total),
                'payment_method' => 'merchant_link',
                'payment_gateway' => 'merchant',
                'status' => 'pending',
                'channel' => GatewayConstant::CHANNEL_MERCHANT,
                'external_reference' => $ref,
            ]);
            $booking->setRelation('payment', $payment);
        } else {
            $payment->channel = GatewayConstant::CHANNEL_MERCHANT;
            $payment->external_reference = $payment->external_reference ?: $ref;
            $payment->save();
        }

        $url = route('merchant.pay.show', ['token' => $booking->payment_token]);

        if ($dispatchNotify) {
            SendPaymentLinkJob::dispatch($booking->id);
        }

        return [
            'url' => $url,
            'token' => (string) $booking->payment_token,
            'external_reference' => (string) $payment->external_reference,
        ];
    }

    /**
     * @return array{url:string,token:string,external_reference:string,qr_payload:string}
     */
    public function createPaymentQr(Booking $booking): array
    {
        $link = $this->createPaymentLink($booking, false);
        $link['qr_payload'] = $link['url'];

        return $link;
    }

    /**
     * Officer attaches customer trx / reference after QR or manual pay.
     *
     * @return array{success:bool,message:string,booking?:Booking}
     */
    public function attachPayment(
        Booking $booking,
        float $amount,
        string $trxOrRef,
        ?string $method,
        int $actorId,
        ?int $gatewayId = null,
    ): array {
        $total = (float) ($booking->total_payable ?? $booking->total_amount);
        $payment = $booking->payment;
        if (! $payment) {
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'paid_amount' => 0,
                'dues' => max(0, $total),
                'status' => 'pending',
                'channel' => GatewayConstant::CHANNEL_MERCHANT,
            ]);
        }

        $paidBefore = (float) ($payment->paid_amount ?? 0);
        $dueBefore = max(0.0, $total - $paidBefore);
        if ($dueBefore < 0.01) {
            return ['success' => false, 'message' => __('No balance due')];
        }

        $amount = min($amount, $dueBefore);
        if ($amount < 0.01) {
            return ['success' => false, 'message' => __('Invalid amount')];
        }

        $method = strtolower(trim((string) ($method ?: 'attach')));
        $trxOrRef = trim($trxOrRef);
        if ($trxOrRef === '') {
            return ['success' => false, 'message' => __('Transaction / reference id is required')];
        }

        DB::transaction(function () use ($booking, $payment, $total, $amount, $method, $trxOrRef, $actorId, $gatewayId) {
            $newPaid = (float) $payment->paid_amount + $amount;
            $newDues = max(0.0, $total - $newPaid);
            $payment->paid_amount = $newPaid;
            $payment->dues = $newDues;
            $payment->payment_method = $method;
            $payment->payment_gateway = $method;
            $payment->bank_tran_id = $trxOrRef;
            $payment->gateway_trx_id = $payment->gateway_trx_id ?: $trxOrRef;
            $payment->external_reference = $payment->external_reference ?: $trxOrRef;
            $payment->channel = $gatewayId
                ? GatewayConstant::CHANNEL_MERCHANT
                : (in_array($method, ['cash', 'bank_check', 'bank_transfer'], true)
                    ? GatewayConstant::CHANNEL_OFFLINE
                    : GatewayConstant::CHANNEL_MERCHANT);
            if ($gatewayId) {
                $payment->gateway_id = $gatewayId;
            }
            // Officer-attached payments need verification before treating as fully live-confirmed.
            $payment->status = $newDues < 0.01 ? 'verified' : 'pending';
            $payment->save();

            PaymentCollector::create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'supervisor_id' => $actorId,
                'amount' => $amount,
                'payment_type' => $method,
            ]);

            $booking->payment_status = $newDues < 0.01 ? 1 : 0;
            $booking->status = $newDues < 0.01 ? AppConst::BOOKING_COMPLETE : AppConst::BOOKING_RESERVED;
            $booking->save();
        });

        $booking = $booking->fresh(['payment', 'customer', 'bookingItems']);
        if ($booking && $booking->status === AppConst::BOOKING_COMPLETE) {
            app(BookingCompletionService::class)->dispatchCompleteEvent($booking);
        }

        return [
            'success' => true,
            'message' => __('Payment attached to booking'),
            'booking' => $booking,
        ];
    }

    /**
     * Start live checkout with a merchant-owned gateway.
     *
     * @return array{success:bool,message:string,data?:array}
     */
    public function initiateMerchantGateway(Booking $booking, Gateway $gateway, Request $request): array
    {
        if ((int) $gateway->merchant_id < 1 || $gateway->channel !== GatewayConstant::CHANNEL_MERCHANT) {
            return ['success' => false, 'message' => __('Invalid merchant gateway')];
        }

        $payment = $booking->payment;
        $total = (float) ($booking->total_payable ?? $booking->total_amount);
        if (! $payment) {
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'paid_amount' => 0,
                'dues' => max(0, $total),
                'status' => 'pending',
            ]);
        }

        $payment->gateway_id = $gateway->id;
        $payment->channel = GatewayConstant::CHANNEL_MERCHANT;
        $payment->payment_method = (string) $gateway->code;
        $payment->payment_gateway = (string) $gateway->name;
        $payment->transaction_id = $payment->transaction_id ?: ('M'.$booking->id.time());
        $payment->paid_amount = $payment->paid_amount > 0 ? $payment->paid_amount : $total;
        $payment->dues = max(0, $total - (float) $payment->paid_amount);
        $payment->status = 'pending';
        $payment->save();

        $data = ['success' => false, 'message' => __('Could not start payment')];
        try {
            $handler = $this->catalog->resolveHandler($gateway);
            $handler->create($payment, $request, $data);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return [
            'success' => (bool) ($data['success'] ?? false),
            'message' => (string) ($data['message'] ?? ''),
            'data' => $data,
        ];
    }
}
