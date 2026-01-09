<?php

namespace App\Http\Controllers;

use App\Gateways\Bkash;
use App\Gateways\GatewayInterface;
use App\Models\Gateway;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GatewayCallbackController extends Controller
{
    private GatewayInterface $gateway;

    public function __construct(Bkash $gateway)
    {
        $this->gateway = $gateway;
    }

    public function callback(Request $request, Gateway $gateway): RedirectResponse
    {
        $data = ['status' => false, 'id' => '', 'uuid' => '', 'paymentID' => $request->input('paymentID')];
        try {
            $payment = Payment::where('gateway_initiated_id', $request->input('paymentID'))->first();

            if (!$payment) {
                $data['status'] = 'failed';
                return $this->paymentFailed($payment, $data);
            }

            $data['id'] = $payment->id;

            if ($payment->user_id !== auth('api')->id()) {
                $data['status'] = 'failed';
                return $this->paymentFailed($payment, $data);
            }

            $data['uuid'] = $payment->uuid;
            $this->gateway->execute($payment, $request, $data);

            if ($data['status']) {
                $data['status'] = 'success';
                return $this->paymentSuccess($payment, $data);
            } else {
                $data['status'] = 'failed';
            }
            return $this->paymentFailed($payment, $data);
        } catch (\Throwable $e) {
            $data['status'] = 'success';
            Log::error('Bkash execute error', ['e' => $e->getMessage()]);
        }
    }

    public function paymentStatus(Payment $payment)
    {
        return view('payment.status', compact('payment'));
    }

    public function paymentFailed($payment, array $data): RedirectResponse
    {
        $url = config('bkash.frontend_url') . '?' . http_build_query($data);
        return redirect()->route('payment.failed', $payment->id)
            ->with('error', 'Payment failed.');
    }

    public function paymentSuccess($payment, array $data): RedirectResponse
    {
        $data['status'] = 'success';
        $url = config('bkash.frontend_url') . '?' . http_build_query($data);
        return redirect()->route('payment.success', $payment->id)
            ->with('error', 'Payment failed.');
    }
}
