<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Api\TransportApiController;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\ScheduleCabinMapping;
use App\Models\VehicleSchedule;
use App\Services\AgentBookingQuotaService;
use App\Services\AgentCounterPaymentService;
use App\Services\AgentPaymentService;
use App\Services\BookingService;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agent counter booking — thin wrappers around common TransportApiController methods.
 * Separate /api/v1/agent/* URLs + agent auth; search/suggest/lock/unlock share core logic.
 * Counter bookings earn seller commission — they are not referral bookings.
 * Cash payment is not eligible for agents.
 */
class AgentTransportBookingController extends Controller
{
    public function __construct(
        private readonly TransportApiController $transport,
        private readonly BookingService $bookingService,
        private readonly AgentCounterPaymentService $counterPayments,
        private readonly TripService $tripService,
        private readonly AgentBookingQuotaService $agentQuota,
        private readonly AgentPaymentService $agentPayments,
    ) {
    }

    public function search(Request $request): JsonResponse
    {
        return $this->transport->search($request);
    }

    public function trip(Request $request, int $id): JsonResponse
    {
        $layout = VehicleSchedule::query()
            ->with([
                'route',
                'decks.departureFrom.ghat',
                'decks.departureTo.ghat',
                'boardingVias.ghat',
                'startFrom',
                'stopTo',
                'mappings.cabinType',
                'vehicle',
                'merchant',
            ])
            ->where('id', $id)
            ->get()
            ->map(fn ($trip) => $this->tripService->formatTriplayout($trip, $request->floor))
            ->first();

        if (! $layout) {
            return response()->json([
                'success' => false,
                'message' => __('Trip not found'),
            ], 404);
        }

        $agent = auth()->user();
        if ($agent instanceof Agent) {
            $layout['agent_quota'] = $this->agentQuota->summaryForTrip($agent, $id);
        }

        return response()->json([
            'success' => true,
            'data' => $layout,
        ]);
    }

    public function suggest(Request $request, ?string $term = null, ?string $accept = null): JsonResponse
    {
        return $this->transport->suggest($request, $term, $accept);
    }

    public function lock(Request $request): JsonResponse
    {
        $agent = auth()->user();
        if ($agent instanceof Agent) {
            $itemIds = $request->has('item_ids')
                ? array_values(array_filter((array) $request->input('item_ids')))
                : [ $request->input('item_id') ];

            foreach ($itemIds as $itemId) {
                if (! $itemId) {
                    continue;
                }
                $mapping = ScheduleCabinMapping::query()->find((int) $itemId);
                if (! $mapping) {
                    return response()->json([
                        'success' => false,
                        'message' => __('Item not found'),
                    ], 422);
                }
                $allowed = $this->agentQuota->assertCanAdd($agent, $mapping);
                if ($allowed !== true) {
                    return response()->json([
                        'success' => false,
                        'message' => $allowed,
                    ], 422);
                }
            }
        }

        return $this->transport->lock($request);
    }

    public function unlock(Request $request): JsonResponse
    {
        return $this->transport->unlock($request);
    }

    public function paymentMethods(): JsonResponse
    {
        $agent = auth()->user();
        $agentModel = $agent instanceof Agent ? $agent : null;

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $this->counterPayments->availableMethods($agentModel),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $agent = auth()->user();
        $agentModel = $agent instanceof Agent ? $agent : null;
        $method = $this->counterPayments->normalize(
            (string) $request->input('payment_method', AgentCounterPaymentService::METHOD_FUND)
        );
        $allowed = $this->counterPayments->allowedCodes($agentModel);

        if ($method === 'cash') {
            return response()->json([
                'success' => false,
                'message' => __('Cash payment is not available for agent bookings. Use fund or a digital payment method.'),
            ], 422);
        }

        if (! in_array($method, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid payment method'),
            ], 422);
        }

        $request->merge([
            'platform' => $request->input('platform', 'agent_app'),
            'payment_method' => $method,
        ]);
        if ($request->filled('paid_amount')) {
            $request->merge(['paid_amount' => $request->input('paid_amount')]);
        }

        $liveGateway = $agentModel
            && $this->counterPayments->isLiveGateway($method, $request->input('gateway_id'));

        if ($liveGateway && ! $request->filled('gateway_id')) {
            return response()->json([
                'success' => false,
                'message' => __('gateway_id is required for digital payment'),
            ], 422);
        }

        if (! $request->filled('customer_mobile') || ! $request->filled('customer_name')) {
            return response()->json([
                'success' => false,
                'message' => __('Customer name and mobile are required'),
            ], 422);
        }

        $items = $request->input('items');
        if (! is_array($items)) {
            $items = json_decode(str_replace('\\', '', (string) $request->items), true) ?? [];
        }
        if (empty($items)) {
            return response()->json([
                'success' => false,
                'message' => __('Items are required'),
            ], 422);
        }

        if ($this->counterPayments->isFund($method) && $agentModel) {
            $balance = (float) app(\App\Services\BalanceService::class)->getMyBalance($agentModel->id);
            if ($balance <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => __('Insufficient fund balance'),
                ], 422);
            }
        }

        $cartItems = array_map(function ($item) {
            $item = (array) $item;

            return (object) array_merge([
                'item_id' => $item['item_id'] ?? null,
                'type' => $item['type'] ?? 'seat',
                'for_self' => $item['for_self'] ?? true,
                'passengers' => $item['passengers'] ?? [],
                'discount' => $item['discount'] ?? 0,
                'boardingPoint' => $item['boardingPoint'] ?? null,
                'meta' => $item['meta'] ?? [],
            ], $item);
        }, $items);

        if ($agentModel) {
            $allowed = $this->agentQuota->assertCanConfirm($agentModel, $cartItems);
            if ($allowed !== true) {
                return response()->json([
                    'success' => false,
                    'message' => $allowed,
                ], 422);
            }
        }

        $data = [];
        try {
            $this->bookingService->confirm($cartItems, $data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if (! empty($data['order_id'])) {
            $booking = Booking::query()->with(['bookingItems', 'customer'])->find($data['order_id']);
            if ($booking) {
                $data['booking'] = \App\Support\AgentApiPresenter::booking($booking);
            }
        }

        if ($liveGateway && $agentModel && ! empty($data['order_id'])) {
            $pay = $this->agentPayments->initiate(
                $agentModel,
                (int) $data['order_id'],
                (int) $request->input('gateway_id'),
                $request
            );
            $data['requires_payment'] = true;
            $data['payment'] = $pay;
            if (! empty($pay['paymentURL'])) {
                $data['paymentURL'] = $pay['paymentURL'];
                $data['paymentID'] = $pay['paymentID'] ?? null;
                $data['message'] = __('Complete payment to confirm booking');
            } elseif (empty($pay['success'])) {
                $data['message'] = $pay['message']
                    ?? __('Booking created but payment could not be started. Retry payment.');
            }
        }

        return response()->json(array_merge([
            'success' => ! empty($data['success']),
            'message' => $data['message'] ?? '',
        ], $data));
    }
}
