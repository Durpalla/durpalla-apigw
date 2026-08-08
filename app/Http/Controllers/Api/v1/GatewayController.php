<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Booking;
use App\Services\GatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class GatewayController extends Controller
{
    private GatewayService $gatewayService;

    public function __construct(GatewayService $gatewayService)
    {
        $this->gatewayService = $gatewayService;
    }

    public function index(Request $request): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $this->gatewayService->forPublicCustomers(),
        ];

        $bookingId = (int) $request->query('booking_id', 0);
        if ($bookingId > 0) {
            $booking = $this->resolvePayableBooking($bookingId);
            if ($booking !== null) {
                $payment = $booking->payment;
                $payable = (float) ($booking->total_payable ?? 0);
                if ($payable <= 0) {
                    $payable = (float) $booking->total_amount
                        + (float) $booking->vat_total
                        + (float) $booking->charge_total
                        - (float) $booking->total_discount;
                }
                // Hotel pending rows often store charge in paid_amount with dues=0.
                $paidAmount = (float) ($payment?->paid_amount ?? 0);
                $dues = (float) ($payment?->dues ?? 0);
                $status = strtolower((string) ($payment?->status ?? 'pending'));
                $alreadyPaid = in_array($status, ['success', 'paid', 'complete', 'completed'], true);
                $amount = $alreadyPaid
                    ? 0.0
                    : max($dues, $payable, $paidAmount);
                $payload['booking'] = [
                    'id' => $booking->id,
                    'pnr' => $booking->publicReference(),
                    'booking_reference' => $booking->publicReference(),
                    'total_payable' => round(max($payable, $paidAmount, 0), 2),
                    'total_dues' => round(max($dues, 0), 2),
                    'amount' => round(max($amount, 0), 2),
                    'payment_status' => $payment?->status,
                ];
            }
        }

        return response()->json($payload);
    }

    private function resolvePayableBooking(int $bookingId): ?Booking
    {
        $booking = Booking::query()->with('payment')->find($bookingId);
        if ($booking === null) {
            return null;
        }

        $customerId = Auth::guard('customer')->id();
        if ($customerId !== null && (int) $booking->customer_id !== (int) $customerId) {
            return null;
        }

        return $booking;
    }
}
