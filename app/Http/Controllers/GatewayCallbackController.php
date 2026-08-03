<?php

namespace App\Http\Controllers;

use App\Helpers\CommonHelper;
use App\Models\Gateway;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GatewayCallbackController extends Controller
{
    /**
     * Web redirect from bKash / Nagad after customer action. Handler is chosen from the {gateway} route model (class_name).
     */
    public function callback(Request $request, Gateway $gateway): RedirectResponse
    {
        $data = ['status' => false, 'id' => '', 'uuid' => '', 'paymentID' => $request->input('paymentID')];
        $payment = null;

        try {
            $payment = Payment::where('gateway_initiated_id', $request->input('paymentID'))->first();

            if (! $payment) {
                return redirect()->route('payment.status', ['payment_id' => 0])
                    ->with('error', __('Payment not found.'));
            }

            $data['id'] = $payment->id;

            if (auth('api')->check()
                && (int) $payment->customer_id !== (int) auth('api')->id()) {
                return redirect()->route('payment.status', ['payment_id' => $payment->id])
                    ->with('error', __('This payment does not belong to the signed-in customer.'));
            }

            $data['uuid'] = $payment->uuid;

            $gateway->load(['credentials', 'params', 'endpoints']);
            $handler = CommonHelper::purseGateway($gateway);
            $handler->execute($payment, $request, $data);

            if (! empty($data['status'])) {
                $data['status'] = 'success';

                return $this->paymentSuccess($payment, $data);
            }

            $data['status'] = 'failed';

            return $this->paymentFailed($payment, $data);
        } catch (\Throwable $e) {
            Log::error('Gateway callback execute failed', [
                'gateway_id' => $gateway->id,
                'payment_id' => $payment?->id,
                'message' => $e->getMessage(),
            ]);

            if ($payment) {
                return redirect()->route('payment.status', ['payment_id' => $payment->id])
                    ->with('error', __('Payment processing error.'));
            }

            return redirect()->route('payment.status', ['payment_id' => 0])
                ->with('error', __('Payment processing error.'));
        }
    }

    public function paymentStatus(Request $request)
    {
        try {
            $payment = Payment::find($request->input('payment_id'));
            if (! $payment) {
                if ($url = $this->frontendPaymentStatusUrl(null, false)) {
                    return redirect()->away($url);
                }

                return view('payment.notfound');
            }

            if ($url = $this->frontendPaymentStatusUrl($payment)) {
                return redirect()->away($url);
            }

            return view('payment.status', compact('payment'));
        } catch (\Exception $exception) {
            return view('payment.notfound');
        }
    }

    public function paymentFailed(Payment $payment, array $data): RedirectResponse
    {
        if ($url = $this->frontendPaymentStatusUrl($payment, false)) {
            return redirect()->away($url);
        }

        return redirect()->route('payment.status', ['payment_id' => $payment->id])
            ->with('error', __('Payment failed.'));
    }

    public function paymentSuccess(Payment $payment, array $data): RedirectResponse
    {
        if ($url = $this->frontendPaymentStatusUrl($payment, true)) {
            return redirect()->away($url);
        }

        return redirect()->route('payment.status', ['payment_id' => $payment->id])
            ->with('success', __('Payment successful.'));
    }

    /**
     * Browser checkout: send the customer back to the web app result page.
     * Mobile apps leave FRONTEND_PAYMENT_STATUS_URL unset and keep the apigw status view / API polling.
     */
    private function frontendPaymentStatusUrl(?Payment $payment, ?bool $success = null): ?string
    {
        $base = config('gateway.bkash.frontend_url') ?: config('gateway.nagad.frontend_url');
        if (! is_string($base) || trim($base) === '') {
            return null;
        }

        $base = rtrim(trim($base), '?&');
        $isSuccess = $success ?? ($payment !== null && $payment->status === 'success');
        $query = ['success' => $isSuccess ? '1' : '0'];

        if ($payment !== null) {
            if ($payment->booking_id) {
                $query['bookingId'] = (string) $payment->booking_id;
            }
            if ($payment->transaction_id) {
                $query['ref'] = $payment->transaction_id;
            }

            $payment->loadMissing('booking');
            $platform = strtolower((string) ($payment->booking?->platform ?? 'web'));
            if ($platform !== '' && $platform !== 'web') {
                $query['client'] = 'app';
            }
        }

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($query);
    }
}
