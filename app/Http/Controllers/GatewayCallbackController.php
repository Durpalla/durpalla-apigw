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
                return $this->paymentFailed($data);
            }

            $data['id'] = $payment->id;

            if ($payment->user_id !== auth('api')->id()) {
                $data['status'] = 'failed';
                return $this->paymentFailed($data);
            }

            $data['uuid'] = $payment->uuid;
            $this->gateway->execute($payment, $request, $data);

            if ($data['status']) {
                $data['status'] = 'success';
                return $this->paymentSuccess($data);
            } else {
                $data['status'] = 'failed';
            }

            dd($data);
        } catch (\Throwable $e) {
            $data['status'] = 'success';
            Log::error('Bkash execute error', ['e' => $e->getMessage()]);
            return $this->paymentFailed($data);
        }
    }

    public function paymentFailed(array $data): RedirectResponse
    {
        $url = config('bkash.frontend_url') . '?' . http_build_query($data);
        return redirect()->route('payment.status')->with('error', 'Payment failed.');
    }

    public function paymentSuccess(array $data): RedirectResponse
    {
        $data['status'] = 'success';
        $url = config('bkash.frontend_url') . '?' . http_build_query($data);
        return redirect()->route('payment.status')->with('error', 'Payment failed.');
    }
}
