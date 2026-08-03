<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Helpers\CommonHelper;
use App\Helpers\LogHelper;
use App\Models\BookingCancellation;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;

/**
 * Executes gateway refunds for approved customer cancellations.
 */
class CustomerRefundService
{
    public function execute(BookingCancellation $cancellation): PaymentRefund
    {
        $cancellation->loadMissing(['booking.payment.gateway', 'payment.gateway']);

        $existing = PaymentRefund::query()
            ->where('booking_cancellation_id', $cancellation->id)
            ->first();

        if ($existing && $existing->status === 'success') {
            return $existing;
        }

        $amount = (float) ($cancellation->refund_amount ?? $cancellation->total_refundable ?? 0);
        if ($amount <= 0) {
            $refund = $this->upsertRefund($cancellation, null, $amount, 'manual', null, 'success', [
                'message' => 'No refundable amount',
            ]);
            $cancellation->update([
                'status' => AppConst::CANCELLATION_REFUNDED,
                'refund_error' => null,
            ]);

            return $refund;
        }

        /** @var Payment|null $payment */
        $payment = $cancellation->payment
            ?? $cancellation->booking?->payment
            ?? Payment::query()
                ->where('booking_id', $cancellation->booking_id)
                ->where('status', 'success')
                ->latest('id')
                ->first();

        if (! $payment || $payment->status !== 'success') {
            return $this->fail($cancellation, $existing, $amount, null, 'No successful payment found for refund.');
        }

        $gatewayCode = strtolower((string) ($payment->payment_method ?: $payment->gateway?->code ?: 'unknown'));
        $manualMethods = ['cash', 'fund', 'wallet', 'counter', 'manual'];

        if (in_array($gatewayCode, $manualMethods, true)) {
            $refund = $this->upsertRefund($cancellation, $payment, $amount, $gatewayCode, null, 'success', [
                'message' => 'Marked refunded for offline/manual payment method',
            ]);
            $cancellation->update([
                'status' => AppConst::CANCELLATION_REFUNDED,
                'refund_error' => null,
                'payment_method' => $gatewayCode,
            ]);

            return $refund;
        }

        try {
            if (! $payment->gateway) {
                return $this->fail(
                    $cancellation,
                    $existing,
                    $amount,
                    $payment,
                    'Payment gateway record missing for automated refund ('.$gatewayCode.').'
                );
            }

            $handler = CommonHelper::purseGateway($payment->gateway);

            if (! method_exists($handler, 'refund')) {
                return $this->fail(
                    $cancellation,
                    $existing,
                    $amount,
                    $payment,
                    'Gateway does not support automated refunds: '.$gatewayCode
                );
            }

            $cancellation->update(['status' => AppConst::CANCELLATION_PROCESSING]);

            $response = $handler->refund($payment, request(), $amount);
            $ok = $this->isSuccessfulResponse($response);
            $gatewayRefundId = $this->extractRefundId($response);

            if (! $ok) {
                $message = is_array($response)
                    ? (string) ($response['statusMessage'] ?? $response['message'] ?? json_encode($response))
                    : 'Gateway refund failed';

                return $this->fail($cancellation, $existing, $amount, $payment, $message, $response);
            }

            $refund = $this->upsertRefund(
                $cancellation,
                $payment,
                $amount,
                $gatewayCode,
                $gatewayRefundId,
                'success',
                is_array($response) ? $response : ['raw' => $response]
            );

            $cancellation->update([
                'status' => AppConst::CANCELLATION_REFUNDED,
                'refund_error' => null,
                'payment_method' => $gatewayCode,
            ]);

            return $refund;
        } catch (\Throwable $e) {
            LogHelper::exception($e, [
                'keyword' => 'CUSTOMER_REFUND_EXCEPTION',
                'cancellation_id' => $cancellation->id,
            ]);

            return $this->fail($cancellation, $existing, $amount, $payment, $e->getMessage());
        }
    }

    private function fail(
        BookingCancellation $cancellation,
        ?PaymentRefund $existing,
        float $amount,
        ?Payment $payment,
        string $message,
        mixed $payload = null
    ): PaymentRefund {
        $refund = $this->upsertRefund(
            $cancellation,
            $payment,
            $amount,
            strtolower((string) ($payment?->payment_method ?: 'unknown')),
            null,
            'failed',
            is_array($payload) ? ($payload + ['error' => $message]) : ['error' => $message, 'raw' => $payload]
        );

        $cancellation->update([
            'status' => AppConst::CANCELLATION_REFUND_FAILED,
            'refund_error' => $message,
        ]);

        return $refund;
    }

    private function upsertRefund(
        BookingCancellation $cancellation,
        ?Payment $payment,
        float $amount,
        ?string $gateway,
        ?string $gatewayRefundId,
        string $status,
        mixed $payload
    ): PaymentRefund {
        return DB::transaction(function () use ($cancellation, $payment, $amount, $gateway, $gatewayRefundId, $status, $payload) {
            $refund = PaymentRefund::query()->firstOrNew([
                'booking_cancellation_id' => $cancellation->id,
            ]);
            $refund->fill([
                'payment_id' => $payment?->id,
                'amount' => $amount,
                'gateway' => $gateway,
                'gateway_refund_id' => $gatewayRefundId,
                'status' => $status,
                'response_payload' => $payload,
            ]);
            $refund->save();

            return $refund;
        });
    }

    private function isSuccessfulResponse(mixed $response): bool
    {
        if (! is_array($response)) {
            return false;
        }

        if (($response['message'] ?? null) === 'Not implemented') {
            return false;
        }

        $code = (string) ($response['statusCode'] ?? $response['status_code'] ?? '');
        if (in_array($code, ['0000', '0', '200'], true)) {
            return true;
        }

        if (isset($response['refundTrxID']) || isset($response['refund_id']) || isset($response['trxID'])) {
            return true;
        }

        return (bool) ($response['success'] ?? false);
    }

    private function extractRefundId(mixed $response): ?string
    {
        if (! is_array($response)) {
            return null;
        }

        $id = $response['refundTrxID']
            ?? $response['refund_id']
            ?? $response['trxID']
            ?? $response['transaction_id']
            ?? null;

        return $id !== null ? (string) $id : null;
    }
}
