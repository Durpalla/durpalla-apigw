<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelFavorite;
use App\Models\HotelHold;
use App\Models\HotelReview;
use App\Services\Hotel\HotelBookingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class HotelController extends Controller
{
    public function __construct(
        private readonly HotelBookingService $hotelBooking,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $rows = $this->hotelBooking->search($request);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function homeTop(Request $request): JsonResponse
    {
        $rows = $this->hotelBooking->homeTopHotels($request);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(int $hotel): JsonResponse
    {
        $data = $this->hotelBooking->hotelDetails($hotel);
        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => __('Hotel not found'),
            ], 404);
        }

        $data['is_favourite'] = $this->hotelIsFavouriteForCurrentUser($hotel);
        $data['viewer_has_submitted_review'] = $this->viewerHasSubmittedHotelReview($hotel);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function hotelIsFavouriteForCurrentUser(int $hotelId): bool
    {
        if (! Auth::check()) {
            return false;
        }
        if (! Schema::hasTable('hotel_favorites')) {
            return false;
        }

        return HotelFavorite::query()
            ->where('user_id', (int) Auth::id())
            ->where('hotel_id', $hotelId)
            ->exists();
    }

    private function viewerHasSubmittedHotelReview(int $hotelId): bool
    {
        if (! Auth::check()) {
            return false;
        }
        if (! Schema::hasColumn((new HotelReview)->getTable(), 'user_id')) {
            return false;
        }

        return HotelReview::query()
            ->where('hotel_id', $hotelId)
            ->where('user_id', (int) Auth::id())
            ->exists();
    }

    public function rooms(Request $request, int $hotel): JsonResponse
    {
        $rooms = $this->hotelBooking->roomsForStay($request, $hotel);

        $payload = [
            'success' => true,
            'data' => $rooms,
        ];
        if ($rooms === []) {
            $payload['message'] = $this->hotelBooking->describeEmptyHotelRooms($request, $hotel);
        }

        return response()->json($payload);
    }

    public function quote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'room_type_id' => 'required|integer|exists:hotel_room_types,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'nullable|integer|min:1|max:20',
            'children' => 'nullable|integer|min:0|max:20',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $q = $this->hotelBooking->quote($request);

            return response()->json([
                'success' => true,
                'data' => $q,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function hold(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'room_type_id' => 'required|integer|exists:hotel_room_types,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'nullable|integer|min:1|max:20',
            'children' => 'nullable|integer|min:0|max:20',
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

        try {
            $hold = $this->hotelBooking->createHold(
                Auth::user(),
                $validator->validated(),
                $idempotencyKey,
            );

            return response()->json([
                'success' => true,
                'data' => $this->formatHold($hold),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : __('Could not create hold. Please try again.'),
            ], 422);
        }
    }

    public function releaseHold(Request $request, int $hold): JsonResponse
    {
        $ok = $this->hotelBooking->releaseHold(Auth::user(), $hold);
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

    /**
     * Same as {@see releaseHold} for clients that prefer JSON body over DELETE path param.
     */
    public function releaseHoldPost(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hold_id' => 'required|integer|exists:hotel_holds,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        return $this->releaseHold($request, (int) $request->input('hold_id'));
    }

    public function storeReview(Request $request, int $hotel): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|numeric|min:1|max:5',
            'text' => 'required|string|min:3|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $hotelModel = Hotel::query()->whereKey($hotel)->first();
        if ($hotelModel === null) {
            return response()->json([
                'success' => false,
                'message' => __('Hotel not found'),
            ], 404);
        }

        $user = Auth::user();
        $author = (string) ($user?->name ?? '');
        if (trim($author) === '') {
            $author = 'Guest';
        }

        if (Schema::hasColumn((new HotelReview)->getTable(), 'user_id')) {
            $already = HotelReview::query()
                ->where('hotel_id', $hotel)
                ->where('user_id', (int) $user->id)
                ->exists();
            if ($already) {
                return response()->json([
                    'success' => false,
                    'message' => __('You have already submitted a review for this hotel.'),
                ], 422);
            }
        }

        try {
            $attrs = [
                'hotel_id' => $hotel,
                'author' => $author,
                'rating' => round((float) $request->input('rating'), 1),
                'body' => (string) $request->input('text'),
                'reviewed_at' => now(),
            ];
            if (Schema::hasColumn((new HotelReview)->getTable(), 'user_id')) {
                $attrs['user_id'] = (int) $user->id;
            }
            $review = new HotelReview($attrs);
            $review->save();
            $this->refreshHotelReviewStats($hotelModel);
            $hotelModel->refresh();
        } catch (QueryException $e) {
            report($e);
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'duplicate') || ($e->errorInfo[1] ?? null) === 1062) {
                return response()->json([
                    'success' => false,
                    'message' => __('You have already submitted a review for this hotel.'),
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : __('Could not save your review. Please try again later.'),
            ], 500);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : __('Could not save your review. Please try again later.'),
            ], 500);
        }

        $reviewCount = (int) $hotelModel->reviews()->count();
        $aggregateRating = $reviewCount > 0
            ? round((float) $hotelModel->reviews()->avg('rating'), 2)
            : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'author' => $review->author,
                'rating' => (float) $review->rating,
                'text' => $review->body,
                'date' => $review->reviewed_at?->toDateString(),
                'review_count' => $reviewCount,
                'aggregate_rating' => $aggregateRating,
            ],
        ], 201);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hold_id' => 'required|integer|exists:hotel_holds,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $result = $this->hotelBooking->confirmFromHold(
                Auth::user(),
                (int) $request->input('hold_id'),
            );
            $booking = $result['booking'];
            $payment = $result['payment'];
            $reservation = $result['reservation'];
            $hotel = $reservation->hotel;

            return response()->json([
                'success' => true,
                'message' => __('Booking created. Complete payment to confirm your stay.'),
                'order_id' => $booking->id,
                'booking_id' => $booking->id,
                'booking' => [
                    'id' => $booking->id,
                    'status' => $booking->status,
                    'total_payable' => (float) $booking->total_payable,
                ],
                'payment' => [
                    'id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'paid_amount' => (float) $payment->paid_amount,
                ],
                'hotel' => [
                    'name' => $hotel->name,
                    'check_in' => $reservation->check_in?->toDateString(),
                    'check_out' => $reservation->check_out?->toDateString(),
                    'adults' => (int) $reservation->adults,
                    'children' => (int) $reservation->children,
                ],
                'trans_id' => $payment->transaction_id,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => __('Hold not found.'),
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : __('Could not confirm booking.'),
            ], 500);
        }
    }

    private function formatHold(HotelHold $hold): array
    {
        return [
            'hold_id' => $hold->id,
            'expires_at' => $hold->expires_at?->toIso8601String(),
            'total' => (float) $hold->total_amount,
            'quote' => $hold->quote_json,
            'status' => $hold->status,
        ];
    }

    private function refreshHotelReviewStats(Hotel $hotel): void
    {
        $table = $hotel->getTable();
        $hasReviewCount = Schema::hasColumn($table, 'review_count');
        $hasAggregate = Schema::hasColumn($table, 'aggregate_rating');
        if (! $hasReviewCount && ! $hasAggregate) {
            return;
        }

        $count = (int) $hotel->reviews()->count();
        $payload = [];
        if ($hasReviewCount) {
            $payload['review_count'] = $count;
        }
        if ($hasAggregate) {
            $payload['aggregate_rating'] = $count === 0
                ? 0
                : round((float) HotelReview::query()
                    ->where('hotel_id', $hotel->id)
                    ->avg('rating'), 2);
        }

        if ($payload !== []) {
            $hotel->update($payload);
        }
    }
}
