<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Entertainment\EntertainmentBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EntertainmentController extends Controller
{
    public function __construct(
        private readonly EntertainmentBookingService $entertainment,
    ) {}

    public function homeTop(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->entertainment->homeTop($request),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->entertainment->search($request),
        ]);
    }

    public function show(Request $request, int $venue): JsonResponse
    {
        $user = $this->authenticatedCustomer();
        $data = $this->entertainment->show($venue, $user?->id);
        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => __('Venue not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function availability(Request $request, int $venue): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $this->entertainment->availability(
                    $venue,
                    (string) $request->input('from'),
                    (string) $request->input('to'),
                ),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function reviews(int $venue): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->entertainment->listReviews($venue),
        ]);
    }

    public function storeReview(Request $request, int $venue): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|numeric|min:1|max:5',
            'text' => 'required|string|min:3|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        try {
            $review = $this->entertainment->storeReview(
                $customer,
                $venue,
                (float) $request->input('rating'),
                (string) $request->input('text'),
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $review->id,
                    'author' => $review->author,
                    'rating' => (float) $review->rating,
                    'text' => $review->text,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function quote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'venue_id' => 'required|integer',
            'visit_date' => 'nullable|date',
            'slot_template_id' => 'nullable|integer',
            'lines' => 'required|array|min:1',
            'lines.*.ticket_type_id' => 'required|integer',
            'lines.*.qty' => 'nullable|integer|min:1|max:50',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $this->entertainment->quote($validator->validated()),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function hold(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'venue_id' => 'required|integer',
            'visit_date' => 'nullable|date',
            'slot_template_id' => 'nullable|integer',
            'lines' => 'required|array|min:1',
            'lines.*.ticket_type_id' => 'required|integer',
            'lines.*.qty' => 'nullable|integer|min:1|max:50',
            'guest' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
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
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        try {
            $hold = $this->entertainment->createHold($customer, $validator->validated(), $idempotencyKey);

            return response()->json([
                'success' => true,
                'data' => $this->entertainment->formatHold($hold),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function releaseHold(int $hold): JsonResponse
    {
        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        $ok = $this->entertainment->releaseHold($customer, $hold);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? __('Hold released') : __('Hold not found'),
        ], $ok ? 200 : 404);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hold_id' => 'required|integer',
            'guest' => 'nullable|array',
            'payment' => 'nullable|array',
            'platform' => 'nullable|string|max:32',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $customer = $this->authenticatedCustomer();
        if ($customer === null) {
            return response()->json(['success' => false, 'message' => __('Customer authentication required.')], 401);
        }

        try {
            $result = $this->entertainment->confirmFromHold(
                $customer,
                (int) $request->input('hold_id'),
                (string) $request->input('platform', 'web'),
                $request->input('guest'),
                null,
                null,
                is_array($request->input('payment')) ? $request->input('payment') : null,
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'booking_id' => $result['booking']->id,
                    'pnr' => method_exists($result['booking'], 'publicReference')
                        ? $result['booking']->publicReference()
                        : null,
                    'status' => $result['booking']->status,
                    'total_payable' => (float) $result['booking']->total_payable,
                    'payment_id' => $result['payment']->id,
                    'payment_status' => $result['payment']->status,
                    'valid_until' => $result['item']->valid_until?->toIso8601String(),
                    'tickets' => array_map(
                        fn ($t) => $this->entertainment->ticketPayload($t),
                        $result['tickets'],
                    ),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function authenticatedCustomer(): ?Customer
    {
        $user = Auth::guard('customer')->user() ?? Auth::user();

        return $user instanceof Customer ? $user : null;
    }
}
