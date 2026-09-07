<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\TourHold;
use App\Services\Tour\TourBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TourController extends Controller
{
    public function __construct(
        private readonly TourBookingService $tours,
    ) {}

    public function homeTop(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->tours->homeTop($request),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->tours->search($request),
        ]);
    }

    public function show(int $tour): JsonResponse
    {
        $data = $this->tours->show($tour);
        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => __('Tour not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'departure_id' => 'required|integer|exists:tour_departures,id',
            'places' => 'nullable|integer|min:1|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $this->tours->quote($request),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function hold(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'departure_id' => 'required|integer|exists:tour_departures,id',
            'places' => 'nullable|integer|min:1|max:100',
            'guest' => 'nullable|array',
            'guest.name' => 'nullable|string|max:120',
            'guest.mobile' => 'nullable|string|max:32',
            'guest.email' => 'nullable|email|max:120',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            return response()->json([
                'success' => false,
                'message' => __('Send a non-empty Idempotency-Key header (max 64 characters).'),
            ], 422);
        }

        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json([
                'success' => false,
                'message' => __('Customer authentication required.'),
            ], 401);
        }

        try {
            $hold = $this->tours->createHold($customer, $validator->validated(), $idempotencyKey);

            return response()->json([
                'success' => true,
                'data' => $this->formatHold($hold),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : __('Could not create hold. Please try again.'),
            ], 422);
        }
    }

    public function releaseHold(Request $request, int $hold): JsonResponse
    {
        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json([
                'success' => false,
                'message' => __('Customer authentication required.'),
            ], 401);
        }

        $ok = $this->tours->releaseHold($customer, $hold);
        if (! $ok) {
            return response()->json([
                'success' => false,
                'message' => __('Hold not found or already released.'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('Hold released.'),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hold_id' => 'required|integer|exists:tour_holds,id',
            'customer_name' => 'nullable|string|max:120',
            'customer_mobile' => 'nullable|string|max:32',
            'customer_email' => 'nullable|email|max:120',
            'guest_name' => 'nullable|string|max:120',
            'guest_mobile' => 'nullable|string|max:32',
            'guest_email' => 'nullable|email|max:120',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json([
                'success' => false,
                'message' => __('Customer authentication required.'),
            ], 401);
        }

        try {
            $result = $this->tours->confirmFromHold(
                $customer,
                (int) $request->input('hold_id'),
                (string) $request->input('platform', 'web'),
                [
                    'name' => (string) ($request->input('customer_name') ?: $request->input('guest_name') ?: ''),
                    'mobile' => (string) ($request->input('customer_mobile') ?: $request->input('guest_mobile') ?: ''),
                    'email' => (string) ($request->input('customer_email') ?: $request->input('guest_email') ?: ''),
                ],
            );
            $booking = $result['booking'];
            $payment = $result['payment'];
            $item = $result['item'];
            $pnr = method_exists($booking, 'publicReference') ? $booking->publicReference() : (string) $booking->id;

            return response()->json([
                'success' => true,
                'data' => [
                    'booking_id' => $booking->id,
                    'pnr' => $pnr,
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'total_payable' => (float) $booking->total_payable,
                    'status' => $booking->status,
                    'service_type' => 'tour',
                    'tour' => [
                        'id' => (int) $item->tour_id,
                        'title' => $item->tour_title,
                        'depart_date' => $item->depart_date?->toDateString(),
                        'destination' => $item->destination,
                        'places' => (int) $item->places,
                    ],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : __('Could not confirm booking.'),
            ], 422);
        }
    }

    private function formatHold(TourHold $hold): array
    {
        return [
            'hold_id' => $hold->id,
            'tour_id' => (int) $hold->tour_id,
            'departure_id' => (int) $hold->departure_id,
            'places' => (int) $hold->places,
            'unit_price' => (float) $hold->unit_price,
            'total' => (float) $hold->total_price,
            'expires_at' => $hold->expires_at?->toIso8601String(),
            'status' => $hold->status,
        ];
    }

    private function authenticatedCustomer(): ?Customer
    {
        $user = Auth::guard('customer')->user() ?? Auth::user();

        return $user instanceof Customer ? $user : null;
    }
}
