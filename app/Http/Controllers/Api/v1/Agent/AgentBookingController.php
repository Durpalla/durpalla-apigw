<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Constants\AppConst;
use App\Models\Agent;
use App\Models\Booking;
use App\Services\AgentDashboardService;
use App\Services\CalculationService;
use App\Services\CancellationService;
use App\Services\InvoiceBuilder;
use App\Support\AgentApiPresenter;
use App\Support\BookingInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AgentBookingController extends Controller
{
    public function __construct(
        private readonly AgentDashboardService $dashboardService,
        private readonly CancellationService $cancellationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $size = max(1, min(50, (int) $request->get('size', 20)));
        $source = (string) $request->get('source', 'all');
        if ($source === 'counter') {
            // Legacy alias - "counter" now refers to merchant panel/app bookings only.
            $source = 'agent';
        }
        if (! in_array($source, ['all', 'agent', 'referral'], true)) {
            $source = 'all';
        }

        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $dateFrom = $request->filled('date_from') ? (string) $request->input('date_from') : null;
        $dateTo = $request->filled('date_to') ? (string) $request->input('date_to') : null;

        $paginator = $this->dashboardService->bookings(
            (int) auth()->id(),
            $page,
            $size,
            $source,
            $dateFrom,
            $dateTo,
        );

        return response()->json([
            'success' => true,
            'message' => '',
            'total' => $paginator->total(),
            'data' => collect($paginator->items())
                ->map(fn (Booking $booking) => AgentApiPresenter::booking($booking))
                ->values()
                ->all(),
            'page' => max(0, $page - 1),
            'size' => $size,
            'totalElements' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
            'meta' => [
                'source' => $source,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Booking invoice / details for a counter booking owned by the agent.
     */
    public function show(int $id, InvoiceBuilder $invoiceBuilder): JsonResponse
    {
        $agent = auth()->user();
        if (! $agent instanceof Agent) {
            return response()->json(['success' => false, 'message' => __('Unauthorized')], 401);
        }

        $booking = Booking::query()->find($id);
        if (! $booking) {
            return response()->json(['success' => false, 'message' => __('Booking not found')], 404);
        }

        $allowed = $this->dashboardService
            ->bookingsQuery((int) $agent->id, 'all')
            ->where('bookings.id', $id)
            ->exists();
        if (! $allowed) {
            return response()->json(['success' => false, 'message' => __('Booking not found')], 404);
        }

        $booking->loadMissing(['bookingItems.trip', 'cancellations', 'payment.gateway']);
        $payment = $booking->payment;
        // Heal premature success on unpaid PENDING agent bookings (pre-gateway).
        if ($payment
            && $booking->status === AppConst::BOOKING_PENDING
            && ! $payment->isCollected()
            && strtolower((string) $payment->status) === 'success') {
            $payment->update(['status' => 'pending', 'bank_tran_id' => null]);
            $payment->refresh();
        }

        $invoice = $invoiceBuilder->build($booking);
        $trx = (string) ($payment->transaction_id ?? '');

        $calculation = app(CalculationService::class);
        $cancellationEnabled = filter_var(
            getOption('is_cancellation_enabled', '1'),
            FILTER_VALIDATE_BOOLEAN
        );
        $bookingLevelCancellable = AgentApiPresenter::isBookingCancellable($booking);
        $cancellationItemIds = $this->cancellationRequestedItemIds($booking);
        $itemsById = $booking->bookingItems->keyBy('id');
        $anyItemCancellable = false;

        $items = [];
        foreach ($invoice['items'] ?? [] as $group) {
            foreach ($group['tickets'] ?? [] as $ticket) {
                $itemId = isset($ticket['id']) ? (int) $ticket['id'] : null;
                $bookingItem = $itemId ? $itemsById->get($itemId) : null;
                $cancelRequested = $itemId !== null && in_array($itemId, $cancellationItemIds, true);
                $itemCancelled = $bookingItem
                    && (int) $bookingItem->status === AppConst::BOOKING_ITEM_CANCELLED;
                $itemCancellable = false;
                if ($bookingItem
                    && $cancellationEnabled
                    && $bookingLevelCancellable
                    && (int) $bookingItem->status === AppConst::BOOKING_ITEM_ACTIVE
                    && ! $cancelRequested
                    && ! $itemCancelled
                    && $calculation->isItemCancellableByPolicy($bookingItem->toArray())
                ) {
                    $itemCancellable = true;
                    $anyItemCancellable = true;
                }

                $items[] = [
                    'id' => $itemId,
                    'cabin_no' => $ticket['cabin_no'] ?? '',
                    'cabin_type' => $ticket['cabin_type'] ?? '',
                    'fare' => (float) ($ticket['price'] ?? 0),
                    'is_ac' => (bool) ($ticket['is_ac'] ?? false),
                    'vehicle_name' => $ticket['vehicle_name'] ?? '',
                    'vehicle_type' => $ticket['vehicle_type'] ?? '',
                    'route_name' => $ticket['route_name'] ?? '',
                    'schedule_date' => $ticket['schedule_date'] ?? '',
                    'leaving_time' => $ticket['leaving_time'] ?? '',
                    'leaving_time_formated' => $ticket['leaving_time_formated'] ?? '',
                    'boarding_point' => $ticket['boarding_point'] ?? null,
                    'from' => $ticket['from'] ?? '',
                    'to' => $ticket['to'] ?? '',
                    'status' => $ticket['status'] ?? null,
                    'passenger' => $ticket['passenger'] ?? null,
                    'cancellable' => $itemCancellable,
                    'cancel_requested' => $cancelRequested,
                    'cancelled' => (bool) $itemCancelled,
                ];
            }
        }

        if (! empty($invoice['hotel'])) {
            $hotel = $invoice['hotel'];
            $items[] = [
                'id' => null,
                'is_hotel_stay' => true,
                'hotel_name' => $hotel['title'] ?? 'Hotel',
                'room_type_title' => $hotel['title'] ?? 'Room',
                'check_in' => $hotel['check_in'] ?? '',
                'check_out' => $hotel['check_out'] ?? '',
                'adults' => (int) ($hotel['adults'] ?? 0),
                'children' => (int) ($hotel['children'] ?? 0),
                'fare' => (float) ($booking->total_payable ?? $booking->total_amount ?? 0),
                'cabin_type' => 'hotel',
                'cancellable' => false,
                'cancel_requested' => false,
                'cancelled' => false,
            ];
        }

        $customer = $invoice['customer'] ?? null;
        $paymentWindow = \App\Services\PendingBookingPaymentWindow::paymentWindowPayload($booking);
        $pendingVoid = AgentApiPresenter::isPendingPaymentCancellable($booking);
        $cancellable = $pendingVoid
            || ($cancellationEnabled && $bookingLevelCancellable && $anyItemCancellable);

        return response()->json([
            'success' => true,
            'message' => '',
            'booking' => [
                'id' => $booking->id,
                'pnr' => $booking->id,
                'booking_reference' => AgentApiPresenter::formatBookingReference($booking),
                'qr_code' => $trx !== '' ? $trx : (string) $booking->id,
                'status' => $booking->status,
                'payment_status' => $invoice['payment_status']
                    ?? ($payment ? $payment->displayStatusForBooking($booking) : ''),
                'transaction_id' => $trx,
                'gateway_name' => $payment?->gateway?->name
                    ?? $payment?->payment_gateway
                    ?? '',
                'gateway_id' => $paymentWindow['gateway_id'],
                'booking_date' => $invoice['booking_date'] ?? null,
                'booking_date_formated' => $invoice['booking_date_formated'] ?? null,
                'total_amount' => (float) ($invoice['total_amount'] ?? 0),
                'total_discount' => (float) ($invoice['total_discount'] ?? 0),
                'vat_total' => (float) ($invoice['vat_total'] ?? 0),
                'charge_total' => (float) ($invoice['charge_total'] ?? 0),
                'total_payable' => (float) str_replace(',', '', (string) ($invoice['total_payable'] ?? 0)),
                'seal' => $invoice['seal'] ?? '',
                'customer_name' => is_object($customer) ? ($customer->name ?? '') : ($customer['name'] ?? ''),
                'customer_mobile' => is_object($customer) ? ($customer->mobile ?? '') : ($customer['mobile'] ?? ''),
                'invoice' => BookingInvoice::signedUrl($booking, 60),
                'items' => $items,
                'hotel' => $invoice['hotel'] ?? null,
                'display_status' => AgentApiPresenter::displayStatus($booking),
                'cancellable' => $cancellable,
                'pending_payment_cancellable' => $pendingVoid,
                'payment_due_at' => $paymentWindow['payment_due_at'],
                'payment_due_at_ms' => $paymentWindow['payment_due_at_ms'],
                'can_pay' => $paymentWindow['can_pay'],
                'payment_date' => optional($payment?->created_at)->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Agent cancels their own counter booking (creates cancellation request).
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $agent = auth()->user();
        if (! $agent instanceof Agent) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized'),
            ], 401);
        }

        $booking = Booking::query()->with(['bookingItems.trip'])->find($id);
        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => __('Booking not found'),
            ], 404);
        }

        $owns = Schema::hasColumn('bookings', 'booked_by_type')
            && Schema::hasColumn('bookings', 'booked_by_id')
            && $booking->booked_by_type === Agent::class
            && (int) $booking->booked_by_id === (int) $agent->id;

        if (! $owns) {
            return response()->json([
                'success' => false,
                'message' => __('You can only cancel your own counter bookings'),
            ], 403);
        }

        $status = strtolower((string) $booking->status);
        if (str_contains($status, 'cancel')) {
            return response()->json([
                'success' => false,
                'message' => __('Booking is already cancelled'),
            ], 422);
        }

        // Unpaid PENDING: agent voids booking immediately (release seats).
        if (AgentApiPresenter::isPendingPaymentCancellable($booking)) {
            try {
                \App\Services\PendingBookingPaymentWindow::cancelUnpaidPendingBooking($booking);

                return response()->json([
                    'success' => true,
                    'message' => __('Booking cancelled successfully'),
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: __('Your cancellation request failed'),
                ], 422);
            }
        }

        if (! AgentApiPresenter::isBookingCancellable($booking)) {
            return response()->json([
                'success' => false,
                'message' => strtoupper((string) $booking->status) !== 'COMPLETE'
                    ? __('Only confirmed bookings can be cancelled')
                    : __('This booking can no longer be cancelled as the trip date has passed'),
            ], 422);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'type' => ['nullable', 'string'],
        ]);

        $itemIds = array_values(array_unique(array_map('intval', $validated['items'])));
        $ownedIds = $booking->bookingItems->pluck('id')->map(fn ($v) => (int) $v)->all();
        foreach ($itemIds as $itemId) {
            if (! in_array($itemId, $ownedIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => __('Booking item :id is not valid.', ['id' => $itemId]),
                ], 422);
            }
        }

        $data = ['success' => false, 'message' => __('Your cancellation request failed')];
        try {
            DB::transaction(function () use ($id, $itemIds, $validated, &$data) {
                $this->cancellationService->cancelBooking([
                    'booking_id' => $id,
                    'items' => $itemIds,
                    'type' => $validated['type'] ?? null,
                ]);
                $data['success'] = true;
                $data['message'] = __('Your cancellation request success');
            }, 2);
        } catch (\Throwable $e) {
            $data['message'] = $e->getMessage();
        }

        return response()->json($data, $data['success'] ? 200 : 422);
    }

    /**
     * @return list<int>
     */
    private function cancellationRequestedItemIds(Booking $booking): array
    {
        $ids = [];
        foreach ($booking->cancellations ?? [] as $cancellation) {
            foreach (explode(',', (string) $cancellation->items) as $id) {
                $id = (int) trim($id);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
