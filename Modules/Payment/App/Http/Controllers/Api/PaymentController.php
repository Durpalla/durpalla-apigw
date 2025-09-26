<?php

namespace Modules\Payment\App\Http\Controllers\Api;

use App\Helpers\CommonHelper;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Your payment cannot be validate')];

        try {
            $payment = Payment::with(['booking'])->where('booking_id', $request->input('booking_id'))->first();

            if ($payment) {
                $gwt = CommonHelper::purseGateway($payment->gateway);
                $data = ['uuid' => $payment->uuid];
                $gwt->verify($payment, $request, $data);

                $payment->refresh();
                $data['success'] = true;
                $data['message'] = __('Your payment has been verified');
                $data['data'] = $payment->format();
                $data['data']['booking'] = $payment->booking->format();
            }
        } catch (\Exception $exception) {
            Log::error($exception->getMessage(), [
                'keyword' => 'PAYMENT_VERIFY_EXCEPTION',
                'request-data' => $request->all(),
            ]);
            dd($exception);
        }

        return response()->json($data);
    }
}
