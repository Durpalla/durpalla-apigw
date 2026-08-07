<?php


namespace App\Services;


use App\Constants\AppConst;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Events\NewNotification;
use App\Models\BookingCancellationItem;
use App\Notifications\BookingCancelRequest;
use App\Repository\Interfaces\CancellationRepositoryInterface;

class CancellationService
{
    private $cancellationRepository;
    protected $calculation;
    public function __construct(CancellationRepositoryInterface $cancellationRepository, CalculationService $calculationService)
    {
        $this->cancellationRepository = $cancellationRepository;
        $this->calculation = $calculationService;
    }

    public function cancelBooking(array $params)
    {
        if (! filter_var(getOption('is_cancellation_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            throw new \Exception(__('Sorry! cancellation is not eligible at this moment'));
        }

        $user = auth()->user();
        $booking = Booking::with(['bookingItems.trip', 'cancellations'])->find($params['booking_id']);
        if (! $booking) {
            throw new \Exception('Booking not found.');
        }

        $staffTypes = [
            AppConst::SUPERVISOR_ROLE,
            AppConst::AGENT_TYPE,
            AppConst::TYPE_MERCHANT,
            AppConst::TYPE_JOLZAN,
            AppConst::PARTNER_TYPE,
        ];
        $isStaff = in_array((string) ($user->type ?? ''), $staffTypes, true);
        if (! $isStaff && (int) $booking->customer_id !== (int) $user->id) {
            throw new \Exception('You are not allowed to cancel this booking.');
        }

        $cancellations = [];
        if ($booking->cancellations) {
            foreach ($booking->cancellations as $cancellation) {
                foreach (explode(',', (string) $cancellation->items) as $id) {
                    $id = (int) trim($id);
                    if ($id > 0) {
                        $cancellations[] = $id;
                    }
                }
            }
            $cancellations = array_values(array_unique($cancellations));
        }
        $items = [];
        $requestItems = $params['items'];
        $inCancellation = false;
        $cancellationItems = [];
        $bookingItems = collect($booking->bookingItems);
        $totalRefundable = 0;
        $itemValidity = true;
        $context = app(CancellationPolicyContext::class);
        $policySnapshot = ['items' => [], 'computed_at' => now()->toIso8601String()];
        $percentSum = 0.0;
        $percentCount = 0;
        $headerVat = null;
        $headerCharge = null;
        $serviceType = 'transport';
        if( is_array( $requestItems ) ) {
            foreach( $requestItems as $item ) {
                if ($cancellations && in_array((int) $item, $cancellations, true)) {
                    $inCancellation = true;
                }
                array_push($items, $item);
                $bookingItem = $bookingItems->firstWhere('id', $item);
                if(!$bookingItem) {
                    throw new \Exception('Booking item ' . $item . ' is not valid.');
                }
                $itemArray = $bookingItem->toArray();
                $serviceType = $context->resolveServiceType($itemArray);
                if (! $this->calculation->isItemCancellableByPolicy($itemArray, $serviceType)) {
                    $itemValidity = false;
                }
                $merchantId = $context->itemMerchantId($itemArray);
                $vatRefundable = $context->isVatRefundable($merchantId);
                $chargeRefundable = $context->isChargeRefundable($merchantId);
                $baseAmount = (float) $this->calculation->calculateRefundableAmount($itemArray, $chargeRefundable);
                $refundPercent = $this->calculation->policyRefundPercent($itemArray, $serviceType);
                $refundableAmount = $this->calculation->calculatePolicyRefundableAmount(
                    $itemArray,
                    $chargeRefundable,
                    $serviceType
                );
                $totalRefundable += $refundableAmount;
                $percentSum += $refundPercent;
                $percentCount++;
                $headerVat = $headerVat === null ? $vatRefundable : ($headerVat && $vatRefundable);
                $headerCharge = $headerCharge === null ? $chargeRefundable : ($headerCharge && $chargeRefundable);
                $eventAt = $context->itemEventAt($itemArray, $serviceType);
                $policySnapshot['items'][] = [
                    'booking_item_id' => $bookingItem->id,
                    'merchant_id' => $merchantId,
                    'service_type' => $serviceType,
                    'event_at' => $eventAt?->toIso8601String(),
                    'base_amount' => $baseAmount,
                    'refund_percent' => $refundPercent,
                    'refundable_amount' => $refundableAmount,
                    'vat_refundable' => $vatRefundable,
                    'charge_refundable' => $chargeRefundable,
                ];
                array_push($cancellationItems, [
                    'booking_item_id' => $bookingItem->id,
                    'customer_id' => $booking->customer_id,
                    'officer_id' => $user->id,
                    'base_amount' => $baseAmount,
                    'refund_percent' => $refundPercent,
                    'refundable_amount' => $refundableAmount,
                    'vat_refundable' => (int) $vatRefundable,
                    'charge_refundable' => (int) $chargeRefundable,
                ]);
            }
        }
        if($itemValidity == true) {
            if ($inCancellation == false) {
                // booking_cancellations.type is total/partial: t = all active items, p = subset
                $requestedType = strtolower((string) ($params['type'] ?? ''));
                if (in_array($requestedType, ['t', 'p'], true)) {
                    $cancelType = $requestedType;
                } else {
                    $selectedIds = collect($requestItems)->map(fn ($id) => (int) $id)->unique()->values();
                    $activeIds = $bookingItems
                        ->filter(fn ($item) => (int) $item->status === AppConst::BOOKING_ITEM_ACTIVE)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values();
                    $cancelType = $activeIds->diff($selectedIds)->isEmpty() ? 't' : 'p';
                }

                $cancellation = $this->cancellationRepository->create([
                    'booking_id' => $params['booking_id'],
                    'type' => $cancelType,
                    'service_type' => $serviceType,
                    'customer_id' => $booking->customer_id,
                    'user_id' => Auth::user()->id,
                    'transaction_id' => uniqid(),
                    'items' => implode(',', $requestItems),
                    'vat_refundable' => (int) ($headerVat ?? false),
                    'charge_refundable' => (int) ($headerCharge ?? false),
                    'total_refundable' => $totalRefundable,
                    'refund_percent_applied' => $percentCount > 0 ? round($percentSum / $percentCount, 2) : 0,
                    'policy_snapshot' => $policySnapshot,
                ]);
                collect($cancellationItems)->each(function ($item, $key) use ($cancellation) {
                    $item['booking_cancellation_id'] = $cancellation->id;
                    BookingCancellationItem::create($item);
                });

                // Notifications must not roll back a successful cancel request.
                try {
                    if ($cancellation->customer) {
                        $cancellation->customer->notify(new BookingCancelRequest($cancellation));
                        event(new NewNotification($cancellation->customer, [
                            'type' => 'Cancellation request',
                            'message' => 'Your booking cancellation request sent',
                        ]));
                    }
                } catch (\Throwable $notificationError) {
                    report($notificationError);
                }
            } else {
                throw new \Exception('Your items is in cancellations list');
            }
        } else {
            throw new \Exception('Some of your items is not cancellable');
        }
    }

    public function confirm($cancellation)
    {
        return $cancellation->update(['status' => AppConst::CANCELLATION_REFUNDED]);
    }

    public function reject($cancellation)
    {
        return $cancellation->update(['status' => AppConst::CANCELLATION_REJECTED]);
    }

    public function updateRefundAmount(BookingCancellation $cancellation)
    {
        return true;
    }

    public function getMyCancellations()
    {
        $cancellation_statuses = Config::get('constants.cancellation');
        $user = auth()->user();
        $lists = $this->cancellationRepository->getOfficerCancellations($user->id);

        if(request()->pnr) {
            $pnr = request()->pnr;
            $lists = $lists->filter(function($item, $key) use($pnr) {
                return $item->booking_id == $pnr;
            });
        } else {
            if(request()->date_from) {
                $lists = $lists->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime(request()->date_from)));
            }
            if(request()->date_to) {
                $lists = $lists->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime(request()->date_to)));
            }
            if(request()->journey_date) {
                $journey_date = date('Y-m-d', strtotime(request()->journey_date));
                $lists = $lists->filter(function($item, $key) use($journey_date) {
                    return $item->cancellationItems->filter(function($item, $key) use($journey_date) {
                        return $item->bookingItem['trip_date'] == $journey_date;
                    });
                });
            }
            if (request()->status) {
                if (request()->status == 'processing') {
                    $lists = $lists->where('status', 0);
                } elseif (request()->status == 'approved') {
                    $lists = $lists->where('status', 1);
                } elseif (request()->status == 'refund') {
                    $lists = $lists->where('status', 3);
                } elseif (request()->status == 'declined') {
                    $lists = $lists->where('status', 9);
                }
            }
        }
        $page = (request()->page)? request()->page : 1;
        $lists = $lists->forPage($page, 15);
        $results = [];
        $lists->map(function($item, $key) use($cancellation_statuses, &$results){
            $info = ['cabin' => 0, 'seat' => 0, 'deck' => 0];
            $items = ['cabin' => [], 'seat' => [], 'deck' => 0];
            $item->cancellationItems->each(function($item, $key) use(&$info, &$items){
                if($item->bookingItem->booking_type == 'deck') {
                    $passenger = json_decode($item->bookingItem->passenger);
                    $info['deck'] += ($passenger) ? $passenger->person : 1;
                    $items['deck'] += ($passenger) ? $passenger->person : 1;
                } else {
                    $cabinNo = ($item->bookingItem['item']['cabinType']) ? $item->bookingItem['item']['cabinType']['letter'] . '-' : '';
                    $cabinNo .= $item->bookingItem['item']['cabin_no'];
                    $items[$item->bookingItem->booking_type][] = $cabinNo;
                    $info[$item->bookingItem->booking_type] += 1;
                }
            });
            $booking = $item->booking ?? \App\Models\Booking::query()->find($item->booking_id);
            array_push($results, [
                'id' => $item->id,
                'pnr' => $booking ? $booking->publicReference() : (string) $item->booking_id,
                'booking_id' => $item->booking_id,
                'booking_date' => date('Y-m-d H:i:s', strtotime($item->booking['created_at'])),
                'booking_info' => $info,
                'customer_name' => $item->customer['name'],
                'customer_mobile' => $item->customer['mobile'],
                'paid_amount' => $item->booking['payment']['paid_amount'],
                'refundable_amount' => $item->total_refundable,
                'refunded_amount' => $item->refund_amount,
                'status' => ($item->status == 0) ? 'processing' : $cancellation_statuses[$item->status],
                'items' => $items
            ]);
        });

        return $results;
    }

    public function details(int $id)
    {
        $item = $this->cancellationRepository->get($id);
        if($item) {
            $booking = $item->booking ?? \App\Models\Booking::query()->find($item->booking_id);
            $response = [
                'id' => $item->id,
                'pnr' => $booking ? $booking->publicReference() : (string) $item->booking_id,
                'refund_amount' => $item->refund_amount,
                'cancellation_time' => date('Y-m-d H:i:s', strtotime($item->created_at)),
                'last_update' => date('Y-m-d H:i:s', strtotime($item->updated_at)),
                'items' => []
            ];
            $item->cancellationItems->each(function($item, $key) use(&$response) {
                array_push($response['items'], [
                    'cabin_no' =>$item['bookingItem']['cabin_no'],
                    'ticket_id' => $item['booking_item_id'],
                    'type' => $item['bookingItem']['booking_type']
                ]);
            });

            return $response;
        }
        return [];
    }

    /**
     * Preview refund amounts for selected booking items before confirming cancel.
     *
     * @param  list<int|string>  $itemIds
     * @return array{
     *   booking_id:int,
     *   items:list<array<string,mixed>>,
     *   total_base:float,
     *   total_refundable:float,
     *   vat_refundable:bool,
     *   charge_refundable:bool,
     *   policy_lines:list<string>,
     *   cancellable:bool
     * }
     */
    public function quoteCancellation(int $bookingId, array $itemIds): array
    {
        $user = auth()->user();
        $booking = Booking::with(['bookingItems.trip', 'bookingItems.hotel'])->find($bookingId);
        if (! $booking) {
            throw new \Exception('Booking not found.');
        }

        if ((int) $booking->customer_id !== (int) ($user->id ?? 0)) {
            throw new \Exception('You are not allowed to quote this booking.');
        }

        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        if ($itemIds === []) {
            throw new \Exception('Select at least one item.');
        }

        $already = [];
        foreach ($booking->cancellations ?? [] as $cancellation) {
            foreach (explode(',', (string) $cancellation->items) as $id) {
                $id = (int) trim($id);
                if ($id > 0) {
                    $already[$id] = true;
                }
            }
        }

        $context = app(CancellationPolicyContext::class);
        $itemsOut = [];
        $totalBase = 0.0;
        $totalRefundable = 0.0;
        $headerVat = null;
        $headerCharge = null;
        $allCancellable = true;
        $merchantIdForLines = null;
        $serviceTypeForLines = 'transport';

        foreach ($itemIds as $itemId) {
            $bookingItem = $booking->bookingItems->firstWhere('id', $itemId);
            if (! $bookingItem) {
                throw new \Exception('Booking item '.$itemId.' is not valid.');
            }
            if (isset($already[$itemId])) {
                throw new \Exception('Item '.$itemId.' is already in a cancellation request.');
            }

            $itemArray = $bookingItem->toArray();
            $serviceType = $context->resolveServiceType($itemArray);
            $serviceTypeForLines = $serviceType;
            $merchantId = $context->itemMerchantId($itemArray);
            $merchantIdForLines = $merchantId;
            $vatRefundable = $context->isVatRefundable($merchantId);
            $chargeRefundable = $context->isChargeRefundable($merchantId);
            $cancellable = $this->calculation->isItemCancellableByPolicy($itemArray, $serviceType);
            if (! $cancellable) {
                $allCancellable = false;
            }

            $baseAmount = (float) $this->calculation->calculateRefundableAmount($itemArray, $chargeRefundable);
            $refundPercent = $this->calculation->policyRefundPercent($itemArray, $serviceType);
            $refundableAmount = $this->calculation->calculatePolicyRefundableAmount(
                $itemArray,
                $chargeRefundable,
                $serviceType
            );
            $eventAt = $context->itemEventAt($itemArray, $serviceType);
            $vatInBase = $vatRefundable ? (float) $this->calculation->calculateItemVat($itemArray) : 0.0;
            $chargeInBase = $chargeRefundable ? (float) $this->calculation->calculateItemCharge($itemArray) : 0.0;

            $totalBase += $baseAmount;
            $totalRefundable += $refundableAmount;
            $headerVat = $headerVat === null ? $vatRefundable : ($headerVat && $vatRefundable);
            $headerCharge = $headerCharge === null ? $chargeRefundable : ($headerCharge && $chargeRefundable);

            $itemsOut[] = [
                'booking_item_id' => $bookingItem->id,
                'base_amount' => $baseAmount,
                'refund_percent' => $refundPercent,
                'refundable_amount' => $refundableAmount,
                'vat_refundable' => $vatRefundable,
                'charge_refundable' => $chargeRefundable,
                'vat_amount_in_base' => $vatInBase,
                'charge_amount_in_base' => $chargeInBase,
                'event_at' => $eventAt?->toIso8601String(),
                'cancellable' => $cancellable,
                'service_type' => $serviceType,
            ];
        }

        $policyLines = app(MerchantCancellationPolicyResolver::class)
            ->invoicePolicyLines($merchantIdForLines, $serviceTypeForLines);

        return [
            'booking_id' => $booking->id,
            'items' => $itemsOut,
            'total_base' => round($totalBase, 2),
            'total_refundable' => round($totalRefundable, 2),
            'vat_refundable' => (bool) ($headerVat ?? false),
            'charge_refundable' => (bool) ($headerCharge ?? false),
            'policy_lines' => $policyLines,
            'cancellable' => $allCancellable,
        ];
    }

    public function afterApproved(BookingCancellation $bookingCancellation)
    {
        $hasActiveItem = false;
        $booking = $bookingCancellation->booking;
        foreach( $bookingCancellation->bookingItems as $item ) {
            if( in_array( $item->id, explode(',', $bookingCancellation->items) ) ) {
                $item->update(['status' => AppConst::BOOKING_ITEM_CANCELLED]);
            } else {
                if($item->status == AppConst::BOOKING_ITEM_ACTIVE) {
                    $hasActiveItem = true;
                }
            }
        }
        $refundAmount = 0;
        $dues = (float) data_get($booking, 'payment.dues', 0);
        if ($dues == 0) {
            $refundAmount += $bookingCancellation->total_refundable;
        } elseif ($dues < $bookingCancellation->total_refundable) {
            $refundAmount += $bookingCancellation->total_refundable - $dues;
        }

        if($refundAmount > 0) {
            DB::table('booking_cancellations')
                ->where('id', $bookingCancellation->id)->update([
                'refund_amount' => $refundAmount
            ]);
        }

        if( $hasActiveItem == false ) {
            $booking->update([
                'status' => AppConst::BOOKING_CANCELLED
            ]);
        }
    }
}
