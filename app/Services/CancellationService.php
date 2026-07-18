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
        $user = auth()->user();
        $booking = Booking::find($params['booking_id']);
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
        if( $booking->cancellations ) {
            foreach( $booking->cancellations as $cancellation ) {
                $cancellations = array_merge_recursive( $cancellations, explode(',', $cancellation->items) );
            }
        }
        $items = [];
        $requestItems = $params['items'];
        $inCancellation = false;
        $cancellationItems = [];
        $bookingItems = collect($booking->bookingItems);
        $totalRefundable = 0;
        $itemValidity = true;
        $currentTime = time();
        if( is_array( $requestItems ) ) {
            foreach( $requestItems as $item ) {
                if($cancellations && in_array($item, $cancellations)) {
                    $inCancellation = true;
                }
                array_push($items, $item);
                $bookingItem = $bookingItems->firstWhere('id', $item);
                if(!$bookingItem) {
                    throw new \Exception('Booking item ' . $item . ' is not valid.');
                }
                if (! ($user instanceof \App\Models\Customer)) {
                    if ((strtotime($bookingItem['trip']['leaving_at']) + ($bookingItem['trip']['operation_hour'] * 60 * 60)) < $currentTime) {
                        $itemValidity = false;
                    }
                } else {
                    if ((strtotime($bookingItem['trip']['leaving_at']) + (3 * 60 * 60)) < $currentTime) {
                        $itemValidity = false;
                    }
                }
                $refundableAmount = $this->calculation->calculateRefundableAmount($bookingItem->toArray());
                $totalRefundable += $refundableAmount;
                array_push($cancellationItems, [
                    'booking_item_id' => $bookingItem->id,
                    'customer_id' => $booking->customer_id,
                    'officer_id' => $user->id,
                    'refundable_amount' => $refundableAmount
                ]);
            }
        }
        if($itemValidity == true) {
            if ($inCancellation == false) {
                $cancellation = $this->cancellationRepository->create([
                    'booking_id' => $params['booking_id'],
                    'type' => $bookingItem->type,
                    'customer_id' => $booking->customer_id,
                    'user_id' => Auth::user()->id,
                    'transaction_id' => uniqid(),
                    'items' => implode(',', $requestItems),
                    'vat_refundable' => (int)getOption('is_vat_refundable'),
                    'charge_refundable' => (int)getOption('is_charge_refundable'),
                    'total_refundable' => $totalRefundable
                ]);
                collect($cancellationItems)->each(function ($item, $key) use ($cancellation) {
                    $item['booking_cancellation_id'] = $cancellation->id;
                    BookingCancellationItem::create($item);
                });

                $cancellation->customer->notify(new BookingCancelRequest($cancellation));
                event(new NewNotification($cancellation->customer, ['type' => 'Cancellation request', 'message' => "Your booking cancellation request sent"]));
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
            array_push($results, [
                'id' => $item->id,
                'pnr' => $item->booking_id,
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
            $response = [
                'id' => $item->id,
                'pnr' => $item->booking_id,
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
        if($booking['payment']['dues'] == 0 ) {
            $refundAmount += $bookingCancellation->total_refundable;
        } elseif($booking['payment']['dues'] < $bookingCancellation->total_refundable) {
            $refundAmount += $bookingCancellation->total_refundable - $booking['payment']['dues'];
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
