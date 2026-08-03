<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Booking;
use App\Services\AgentDashboardService;
use App\Services\CancellationService;
use App\Services\InvoiceBuilder;
use App\Support\AgentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

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
        if (! in_array($source, ['all', 'counter', 'referral'], true)) {
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

        $invoice = $invoiceBuilder->build($booking);
        $payment = $booking->payment;
        $trx = (string) ($payment->transaction_id ?? '');

        $items = [];
        foreach ($invoice['items'] ?? [] as $group) {
            foreach ($group['tickets'] ?? [] as $ticket) {
                $items[] = [
                    'id' => $ticket['id'] ?? null,
                    'cabin_no' => $ticket['cabin_no'] ?? '',
                    'cabin_type' => $ticket['cabin_type'] ?? '',
                    'fare' => (float) ($ticket['price'] ?? 0),
                    'is_ac' => (bool) ($ticket['is_ac'] ?? false),
                    'vehicle_name' => $ticket['vehicle_name'] ?? '',
                    'route_name' => $ticket['route_name'] ?? '',
                    'schedule_date' => $ticket['schedule_date'] ?? '',
                    'leaving_time' => $ticket['leaving_time'] ?? '',
                    'leaving_time_formated' => $ticket['leaving_time_formated'] ?? '',
                    'from' => $ticket['from'] ?? '',
                    'to' => $ticket['to'] ?? '',
                    'status' => $ticket['status'] ?? null,
                    'passenger' => $ticket['passenger'] ?? null,
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
            ];
        }

        $customer = $invoice['customer'] ?? null;

        return response()->json([
            'success' => true,
            'message' => '',
            'booking' => [
                'id' => $booking->id,
                'pnr' => $booking->id,
                'qr_code' => $trx !== '' ? $trx : (string) $booking->id,
                'status' => $booking->status,
                'payment_status' => $invoice['payment_status'] ?? ($payment->status ?? ''),
                'transaction_id' => $trx,
                'gateway_name' => $payment?->gateway?->name
                    ?? $payment?->payment_gateway
                    ?? '',
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
                'invoice' => URL::temporarySignedRoute(
                    'invoice.download',
                    now()->addMinutes(60),
                    ['id' => $booking->id]
                ),
                'items' => $items,
                'hotel' => $invoice['hotel'] ?? null,
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

        $itemIds = $request->input('items');
        if (is_string($itemIds)) {
            $itemIds = json_decode($itemIds, true);
        }
        if (! is_array($itemIds) || $itemIds === []) {
            $itemIds = $booking->bookingItems->pluck('id')->map(fn ($v) => (int) $v)->values()->all();
        }
        if ($itemIds === []) {
            return response()->json([
                'success' => false,
                'message' => __('No booking items to cancel'),
            ], 422);
        }

        $data = ['success' => false, 'message' => __('Your cancellation request failed')];
        try {
            DB::transaction(function () use ($id, $itemIds, &$data) {
                $this->cancellationService->cancelBooking([
                    'booking_id' => $id,
                    'items' => $itemIds,
                    'type' => request()->input('type'),
                ]);
                $data['success'] = true;
                $data['message'] = __('Your cancellation request success');
            }, 2);
        } catch (\Throwable $e) {
            $data['message'] = $e->getMessage();
        }

        return response()->json($data, $data['success'] ? 200 : 422);
    }
}
