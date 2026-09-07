<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\BoatRental\BoatRentalBookingService;
use App\Services\BoatRental\BoatTripBidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BoatRentalController extends Controller
{
    public function __construct(
        private readonly BoatRentalBookingService $boats,
        private readonly BoatTripBidService $bids,
    ) {}

    public function homeTop(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->boats->homeTop($request),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->boats->search($request),
        ]);
    }

    public function show(int $boat): JsonResponse
    {
        $data = $this->boats->show($boat);
        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => __('Boat not found'),
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
            'boat_id' => 'required|integer|exists:boats,id',
            'rental_mode' => 'required|string|in:hourly,daily,package',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'guests' => 'nullable|integer|min:1|max:500',
            'package_id' => 'nullable|integer|required_if:rental_mode,package|exists:boat_trip_packages,id',
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
                'data' => $this->boats->quote($request),
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
            'boat_id' => 'required|integer|exists:boats,id',
            'rental_mode' => 'required|string|in:hourly,daily,package',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'guests' => 'nullable|integer|min:1|max:500',
            'package_id' => 'nullable|integer|required_if:rental_mode,package|exists:boat_trip_packages,id',
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
            $hold = $this->boats->createHold($customer, $validator->validated(), $idempotencyKey);

            return response()->json([
                'success' => true,
                'data' => $this->boats->holdPayload($hold),
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

        $ok = $this->boats->releaseHold($customer, $hold);
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
            'hold_id' => 'required|integer|exists:boat_holds,id',
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
            $result = $this->boats->confirmFromHold(
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
                    'service_type' => 'boat_rental',
                    'boat' => [
                        'id' => (int) $item->boat_id,
                        'title' => $item->boat_title,
                        'rental_mode' => $item->rental_mode,
                        'starts_at' => $item->starts_at?->toIso8601String(),
                        'ends_at' => $item->ends_at?->toIso8601String(),
                        'guests' => (int) $item->guests,
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

    public function stoppages(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->bids->listStoppages($request),
        ]);
    }

    public function storeTripRequest(Request $request): JsonResponse
    {
        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        $validator = Validator::make($request->all(), [
            'stoppage_ids' => 'required|array|min:1',
            'stoppage_ids.*' => 'integer|exists:boat_stoppages,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'guests' => 'required|integer|min:1|max:500',
            'city_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $tripRequest = $this->bids->createRequest($customer, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $this->bids->requestPayload($tripRequest),
            ], 201);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function indexTripRequests(Request $request): JsonResponse
    {
        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        $items = collect($this->bids->listMyRequests($customer))
            ->map(fn ($r) => $this->bids->requestPayload($r))
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function showTripRequest(int $id): JsonResponse
    {
        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        $data = $this->bids->showRequest($id, $customer);
        if ($data === null) {
            return response()->json(['success' => false, 'message' => __('Not found')], 404);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function cancelTripRequest(int $id): JsonResponse
    {
        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        $ok = $this->bids->cancelRequest($id, $customer);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? __('Cancelled.') : __('Request not found or already closed.'),
        ], $ok ? 200 : 404);
    }

    public function listBids(int $id): JsonResponse
    {
        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $this->bids->listBidsForRequest($id, $customer),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function acceptBid(Request $request, int $id): JsonResponse
    {
        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            return response()->json([
                'success' => false,
                'message' => __('Send a non-empty Idempotency-Key header (max 64 characters).'),
            ], 422);
        }

        try {
            $hold = $this->bids->acceptBid($customer, $id, null, $idempotencyKey);

            return response()->json([
                'success' => true,
                'data' => $this->boats->holdPayload($hold),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function authenticatedCustomer(): ?Customer
    {
        $user = Auth::guard('customer')->user() ?? Auth::user();

        return $user instanceof Customer ? $user : null;
    }
}
