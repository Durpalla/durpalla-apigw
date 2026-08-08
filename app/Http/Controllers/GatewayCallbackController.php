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
            $payment = Payment::with(['booking', 'gateway'])->find($request->input('payment_id'));
            if (! $payment) {
                if ($url = $this->frontendPaymentStatusUrl(null, false)) {
                    return redirect()->away($url);
                }

                return view('payment.notfound');
            }

            // When FRONTEND_PAYMENT_STATUS_URL is set, always send browsers there.
            // Hotel web bookings were historically stored as platform=android, which
            // used to skip this redirect and strand users on the apigw HTML page.
            if ($url = $this->frontendPaymentStatusUrl($payment)) {
                return redirect()->away($url);
            }

            $isAppClient = $this->isAppPaymentClient($request, $payment);

            return view('payment.status', compact('payment', 'isAppClient'));
        } catch (\Exception $exception) {
            return view('payment.notfound');
        }
    }

    public function paymentFailed(Payment $payment, array $data): RedirectResponse
    {
        $payment->loadMissing('booking');

        if ($url = $this->frontendPaymentStatusUrl($payment, false)) {
            return redirect()->away($url);
        }

        return redirect()->route('payment.status', ['payment_id' => $payment->id])
            ->with('error', __('Payment failed.'));
    }

    public function paymentSuccess(Payment $payment, array $data): RedirectResponse
    {
        $payment->loadMissing('booking');

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
        $base = $this->resolveFrontendPaymentStatusBase();
        if ($base === null) {
            return null;
        }

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

    /**
     * Resolve web app payment result URL from config or .env (works even when
     * bootstrap/config.php was cached before FRONTEND_PAYMENT_STATUS_URL was set).
     */
    private function resolveFrontendPaymentStatusBase(): ?string
    {
        foreach ([
            config('payment.frontend_status_url'),
            config('gateway.bkash.frontend_url'),
            config('gateway.nagad.frontend_url'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return rtrim(trim($candidate), '?&');
            }
        }

        $envPath = base_path('.env');
        if (! is_readable($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            if (! str_starts_with($line, 'FRONTEND_PAYMENT_STATUS_URL=')) {
                continue;
            }
            $raw = trim(substr($line, strlen('FRONTEND_PAYMENT_STATUS_URL=')), " \t\"'");

            return $raw !== '' ? rtrim($raw, '?&') : null;
        }

        return null;
    }

    /** Mobile / native app checkout — keep apigw branded status page in WebView. */
    private function isAppPaymentClient(Request $request, ?Payment $payment): bool
    {
        if ($request->query('client') === 'app') {
            return true;
        }

        if ($payment === null) {
            return false;
        }

        $payment->loadMissing('booking');
        $platform = strtolower((string) ($payment->booking?->platform ?? 'web'));

        return $platform !== '' && $platform !== 'web';
    }
}
