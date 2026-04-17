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
                return view('payment.notfound');
            }

            return view('payment.status', compact('payment'));
        } catch (\Exception $exception) {
            return view('payment.notfound');
        }
    }

    public function paymentFailed(Payment $payment, array $data): RedirectResponse
    {
        return redirect()->route('payment.status', ['payment_id' => $payment->id])
            ->with('error', __('Payment failed.'));
    }

    public function paymentSuccess(Payment $payment, array $data): RedirectResponse
    {
        return redirect()->route('payment.status', ['payment_id' => $payment->id])
            ->with('success', __('Payment successful.'));
    }
}
