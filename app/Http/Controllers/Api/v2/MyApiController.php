<?php

namespace App\Http\Controllers\Api\v2;

use Illuminate\Http\JsonResponse;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingItem;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Notifications\ProfileUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Image;
use App\Services\SupervisorService;

class MyApiController extends Controller
{
    private $status;
    private $success;
    private $supervisor;

    public function __construct(SupervisorService $supervisorService){
        $this->supervisor = $supervisorService;
        $this->status = 200;
        $this->success = 200;
    }

    public function profile()
    {
        $user = Auth::user();
        if($user->hasRole('customer') && $user->meta && $user->meta['nid_no']) {
            $user->nid = [
                'nid_no' => $user->meta['nid_no'],
                'front' => ($user->meta['nid_photo']) ? asset('nid/' . $user->meta['nid_photo']) : '',
                'back' => ($user->meta['nid_back_side']) ? asset('nid/' . $user->meta['nid_back_side']) : ''
            ];
        }
        return response()->json(['success' => true, 'user' => $user ], $this->success );
    }

    public function updateDeviceId( Request $request )
    {
        $validator = Validator::make( $request->all(), [
            'device_id' => 'required|string'
        ]);

        if( $validator->fails() == True ) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);
        }

        $user = Auth::user();
        $user->device_id = (string) $request->device_id;
        if( $user->save() ) {
            return response()->json(['success' => true, 'message' => 'Success'], $this->success);
        }
    }

    public function getBookings( Request $request )
    {
        $user = Auth::user();
        $query = Booking::with(['bookingItems.trip.route', 'cancellations', 'bookingItems.item.cabinType', 'bookingItems.trip.launch', 'payment'])
            ->where('customer_id', $user->id)->orderBy('created_at', 'desc');

        if( $request->date_from ) {
            $date = \DateTime::createFromFormat('Y-m-d', $request->date_from);
            $query->where('booking_date', '>=', $date->format('Y-m-d'));
        }

        if( $request->date_to ) {
            $date = \DateTime::createFromFormat('Y-m-d', $request->date_to);
            $query->where('booking_date', '<=', $date->format('Y-m-d'));
        }

        $bookings = $query->paginate(15);

        $responseArr = [];
        $recentDate = '';
        foreach( $bookings as $key => $booking ) {
            if( $key == 0 ) {
                $recentDate = date('Y-m-d', strtotime($booking->created_at));
            }
            $row['id'] = $booking->id;
            $row['pnr'] = $booking->id;
            $row['booking_date'] = date('Y-m-d H:i:s', strtotime( $booking->created_at ) );
            $row['booking_date_formated'] = date('d M, Y h:i A', strtotime( $booking->created_at ) );
            $row['payment_status'] = $booking->payment['status'];
            $row['transaction_id'] = $booking->payment['transaction_id'];
            $row['total_amount'] = round($booking->total_amount, 2);
            $row['total_discount'] = round($booking->total_discount, 2);
            $row['vat_total'] = round($booking->vat_total, 2);
            $row['charge_total'] = round($booking->charge_total, 2);
            $row['total_payable'] = round(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount), 2);
            $row['items'] = [];
            $row['cancellable'] = false;
            $row['downloadable'] = false;
            $row['status'] = $booking->status;
            $row['total_dues'] = $booking->payment['dues'];

            $cancellations = [];
            if( $booking->cancellations ) {
                foreach( $booking->cancellations as $cancellation ) {
                    $cancellations = array_merge_recursive( $cancellations, explode(',', $cancellation->items) );
                }
            }

            foreach( $booking->bookingItems as $item ) {
                $irow = [
                    'id' => $item['id'],
                    'cabin_id' => $item['cabin_id'],
                    'cabin_no' => ( $item['cabin_type'] != 'deck' && $item['item']) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                    'cabin_type' => $item['booking_type'],
                    'fare' => $item['price'],
                    'is_ac' => ( $item['cabin_type'] != 'deck' && $item['item']) ? $item['item']['cabinType']['is_ac'] : 0,
                    'vehicle_name' => ($item['trip'] && $item['trip']['launch']) ? $item['trip']['launch']['name'] : '',
                    'route_name' => ($item['trip'] && $item['trip']['route']) ? $item['trip']['route']['route_name'] : '',
                    'schedule_date' => $item['trip_date'],
                    'leaving_time' => ($item['trip']) ? $item['trip']['leaving_at'] : date('Y-m-d H:i:s', 0),
                    'leaving_time_formated' => ($item['trip']) ? date('h:i A', strtotime($item['trip']['leaving_at'])) : '',
                    'boarding_point' => json_decode($item['boarding_point']),
                    'passenger' => json_decode($item['passenger']),
                    'status' => $item['status'],
                    'cancellable' => ($item['trip_date'] >= date('Y-m-d')) ? (( in_array($item['id'], $cancellations) ) ? false : true) : false
                ];
                if($item['trip'] && $item['trip']['schedule_type'] == 'reverse' ) {
                    $irow['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                }
                array_push($row['items'], $irow);
                if( $item['status'] == 1 && $item['trip_date'] >= date('Y-m-d') ) {
                    $row['cancellable'] = true;
                    $row['downloadable'] = true;
                }
            }
            if( !getOption('is_cancellation_enabled') ) {
                $row['cancellable'] = false;
            }
            $responseArr[] = $row;
        }

        // return $responseArr;

        $data = [
            'total' => $bookings->total(),
            'per_page' => 10,
            'last_page' => $bookings->lastPage(),
            'current_page' => $request->page ?? 1,
            'data' => $responseArr
        ];
        return response()->json(['success' => true, 'bookings' => $data ], $this->success );
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function bookings( Request $request )
    {
        $user = Auth::user();
        $query = Booking::with(['bookingItems.trip.route', 'cancellations', 'bookingItems.item.cabinType', 'bookingItems.trip.launch', 'payment'])
            ->where('customer_id', $user->id)->orderBy('created_at', 'desc');

        if( $request->date_from ) {
            $date = \DateTime::createFromFormat('Y-m-d', $request->date_from);
            $query->where('booking_date', '>=', $date->format('Y-m-d'));
        }

        if( $request->date_to ) {
            $date = \DateTime::createFromFormat('Y-m-d', $request->date_to);
            $query->where('booking_date', '<=', $date->format('Y-m-d'));
        }

        $bookings = $query->paginate(10);

        $responseArr = [];
        foreach( $bookings as $key => $booking ) {
            $row['id'] = $booking->id;
            $row['pnr'] = $booking->id;
            $row['booking_date'] = date('Y-m-d H:i:s', strtotime( $booking->created_at ) );
            $row['booking_date_formated'] = date('d M, Y h:i A', strtotime( $booking->created_at ) );
            $row['payment_status'] = $booking->payment['status'];
            $row['transaction_id'] = $booking->payment['transaction_id'];
            $row['total_amount'] = round($booking->total_amount, 2);
            $row['total_discount'] = round($booking->total_discount, 2);
            $row['vat_total'] = round($booking->vat_total, 2);
            $row['charge_total'] = round($booking->charge_total, 2);
            $row['total_payable'] = round(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount), 2);
            $row['items'] = [];
            $row['cancellable'] = false;
            $row['downloadable'] = false;
            $row['status'] = $booking->status;

            $cancellations = [];
            if( $booking->cancellations ) {
                foreach( $booking->cancellations as $cancellation ) {
                    $cancellations = array_merge_recursive( $cancellations, explode(',', $cancellation->items) );
                }
            }

            foreach( $booking->bookingItems as $item ) {
                $irow = [
                    'id' => $item['id'],
                    'cabin_id' => $item['cabin_id'],
                    'cabin_no' => ( $item['cabin_type'] != 'deck' && $item['item']) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                    'cabin_type' => $item['booking_type'],
                    'fare' => $item['price'],
                    'is_ac' => ( $item['cabin_type'] != 'deck' && $item['item']) ? $item['item']['cabinType']['is_ac'] : 0,
                    'vehicle_name' => ($item['trip'] && $item['trip']['launch']) ? $item['trip']['launch']['name'] : '',
                    'route_name' => ($item['trip'] && $item['trip']['route']) ? $item['trip']['route']['route_name'] : '',
                    'schedule_date' => $item['trip_date'],
                    'leaving_time' => ($item['trip']) ? $item['trip']['leaving_at'] : date('Y-m-d H:i:s', 0),
                    'leaving_time_formated' => ($item['trip']) ? date('h:i A', strtotime($item['trip']['leaving_at'])) : '',
                    'boarding_point' => json_decode($item['boarding_point']),
                    'passenger' => json_decode($item['passenger']),
                    'status' => $item['status'],
                    'cancellable' => ($item['trip_date'] >= date('Y-m-d')) ? (( in_array($item['id'], $cancellations) ) ? false : true) : false
                ];
                if($item['trip'] && $item['trip']['schedule_type'] == 'reverse' ) {
                    $irow['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                }
                array_push($row['items'], $irow);
                if( $item['status'] == 1 && $item['trip_date'] >= date('Y-m-d') ) {
                    $row['cancellable'] = true;
                    $row['downloadable'] = true;
                }
            }
            if( !getOption('is_cancellation_enabled') ) {
                $row['cancellable'] = false;
            }
            $responseArr[] = $row;
        }

        $data = [
            'total' => $bookings->total(),
            'per_page' => 10,
            'last_page' => $bookings->lastPage(),
            'current_page' => $request->page ?? 1,
            'data' => $responseArr
        ];
        return response()->json(['success' => true, 'bookings' => $data ], $this->success );
    }

    public function bookingAndroid( Request $request, $id )
    {
        $user = Auth::user();
        $booking = Booking::with(['customer', 'bookingItems.trip.route', 'cancellations', 'bookingItems.item.cabinType', 'bookingItems.trip.launch', 'payment'])
            ->where('customer_id', $user->id)->orderBy('booking_date', 'desc')->findOrFail($id);

        $responseArr = [];
        if( $booking ) {
            $responseArr['order_id'] = $booking->id;
            $responseArr['id'] = $booking->id;
            $responseArr['pnr'] = $booking->id;
            $responseArr['qr'] = asset('qrs/' . $booking->id . '.png');
            $responseArr['booking_date'] = date('Y-m-d H:i:s', strtotime( $booking->created_at ) );
            $responseArr['payment_status'] = $booking->payment['status'];
            $responseArr['total_amount'] = $booking->total_amount;
            $responseArr['total_discount'] = $booking->total_discount;
            $responseArr['vat_amount'] = $booking->vat_amount;
            $responseArr['vat_total'] = $booking->vat_total;
            $responseArr['charge_amount'] = $booking->charge_amount;
            $responseArr['charge_total'] = $booking->charge_total;
            $responseArr['total_payable'] = abs(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount));
            $responseArr['payment'] = $booking->payment;
            $responseArr['transaction_id'] = $booking->payment['transaction_id'];
            $responseArr['cancellable'] = false;
            $responseArr['status'] = $booking->status;
            $responseArr['items'] = [];
            $responseArr['customer'] = [
                'id' => $booking->customer_id,
                'name' => $booking->customer['name'],
                'mobile' => $booking->customer['mobile']
            ];

            $cancellations = [];
            if( $booking->cancellations ) {
                foreach( $booking->cancellations as $cancellation ) {
                    $cancellations = array_merge_recursive( $cancellations, explode(',', $cancellation->items) );
                }
            }

            // $responseArr['status'] = $booking->status;

            foreach( $booking->bookingItems as $item ) {
                $row = [
                    'id' => $item['id'],
                    'cabin_no' => ( $item['item'] ) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                    'cabin_type' => $item['booking_type'],
                    'fare' => $item['price'],
                    'cabin_position' => $item['cabin_position'],
                    'discount' => $item['discount'],
                    'is_ac' => ($item['item']) ? $item['item']['cabinType']['is_ac'] : null,
                    'vehicle_name' => $item['trip']['launch']['name'],
                    'route_name' => $item['trip']['route']['route_name'],
                    'schedule_date' => date('d F Y', strtotime( $item['trip_date'] ) ),
                    'leaving_time' => $item['trip']['leaving_at'],
                    'boarding_point' => json_decode($item['boarding_point']),
                    'passenger' => json_decode($item['passenger']),
                    'from' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                    'to' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                    'cancellable' => ($item['trip_date'] >= date('Y-m-d')) ? (( in_array($item['id'], $cancellations) ) ? false : true) : false,
                    'status' => $item['status']
                ];
                if( $item['trip']['schedule_type'] == 'reverse' ) {
                    $row['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                }
                if( $item['status'] == 1 && $item['trip_date'] >= date('Y-m-d') ) {
                    $responseArr['cancellable'] = true;
                }
                array_push($responseArr['items'], $row);
            }

            if( !getOption('is_cancellation_enabled') ) {
                $responseArr['cancellable'] = false;
            }
        }

        return response()->json(['success' => true, 'booking' => $responseArr ], $this->success );
    }

    public function booking( Request $request, $id )
    {
        $user = Auth::user();
        $booking = Booking::with(['bookingItems.trip.route', 'cancellations', 'bookingItems.item.cabinType', 'bookingItems.trip.launch', 'payment'])
            ->where('customer_id', $user->id)->orderBy('booking_date', 'desc')->findOrFail($id);

        $responseArr = [];
        if( $booking ) {
            $responseArr['id'] = $booking->id;
            $responseArr['pnr'] = $booking->id;
            $responseArr['qr'] = asset('qrs/' . $booking->id . '.png');
            $responseArr['booking_date'] = date('Y-m-d H:i:s', strtotime( $booking->created_at ) );
            $responseArr['booking_date_formated'] = date('d M, Y h:i A', strtotime( $booking->created_at ) );
            $responseArr['payment_status'] = $booking->payment['status'];
            $responseArr['total_amount'] = $booking->total_amount;
            $responseArr['total_discount'] = $booking->total_discount;
            $responseArr['vat_amount'] = $booking->vat_amount;
            $responseArr['vat_total'] = $booking->vat_total;
            $responseArr['charge_amount'] = $booking->charge_amount;
            $responseArr['charge_total'] = $booking->charge_total;
            $responseArr['total_dues'] = $booking->payment['dues'];
            $responseArr['total_payable'] = number_format(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount),2);
            $responseArr['payment'] = $booking->payment;
            $responseArr['cancellable'] = false;
            $responseArr['items'] = [];

            $cancellations = [];
            if( $booking->cancellations ) {
                foreach( $booking->cancellations as $cancellation ) {
                    $cancellations = array_merge_recursive( $cancellations, explode(',', $cancellation->items) );
                }
            }

            // $responseArr['status'] = $booking->status;

            foreach( $booking->bookingItems as $item ) {
                $row = [
                    'id' => $item['id'],
                    'cabin_no' => ( $item['item'] ) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                    'cabin_type' => $item['booking_type'],
                    'price' => $item['price'],
                    'cabin_position' => $item['cabin_position'],
                    'discount' => $item['discount'],
                    'is_ac' => ($item['item']) ? $item['item']['cabinType']['is_ac'] : null,
                    'vehicle_name' => $item['trip']['launch']['name'],
                    'route_name' => $item['trip']['route']['route_name'],
                    'schedule_date' => date('d F Y', strtotime( $item['trip_date'] ) ),
                    'leaving_time' => $item['trip']['leaving_at'],
                    'leaving_time_formated' => date('h:i A', strtotime($item['trip']['leaving_at'])),
                    'boarding_point' => json_decode($item['boarding_point']),
                    'passenger' => json_decode($item['passenger']),
                    'from' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                    'to' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                    'cancellable' => ($item['trip_date'] >= date('Y-m-d')) ? (( in_array($item['id'], $cancellations) ) ? false : true) : false,
                    'status' => $item['status']
                ];
                if( $item['trip']['schedule_type'] == 'reverse' ) {
                    $row['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                }
                if( $item['status'] == 1 && $item['trip_date'] >= date('Y-m-d') ) {
                    $responseArr['cancellable'] = true;
                }
                array_push($responseArr['items'], $row);
            }

            if( !getOption('is_cancellation_enabled') ) {
                $responseArr['cancellable'] = false;
            }

            $responseArr['items'] = ( $responseArr['items'] ) ? _my_group_by($responseArr['items'], 'schedule_date' ) : [];

            $tickets = [];
            foreach( $responseArr['items'] as $key => $items ) {
                array_push($tickets, ['date' => $key, 'tickets' => $items]);
            }

            $responseArr['items'] = $tickets;
        }

        return response()->json(['success' => true, 'booking' => $responseArr ], $this->success );
    }

    public function favouriteVehicles()
    {
        $user = Auth::user();
        $vehicles = Vehicle::with(['activeTrips',
                'bookingItems' => function($q) use($user){
                    $q->where('customer_id', $user->id);
                }
            ])
            ->whereHas('activeTrips', function($q){
                $q->where('schedule_date', '>=', date('Y-m-d'))->orderBy('leaving_at', 'ASC');
            })
            ->has('bookingItems', '>', 0)
            ->has('activeTrips', '>', 0)
            ->withCount(['bookingItems' => function($q) use($user) {
                    $q->where('customer_id', $user->id);
                }
            ])
            ->orderByDesc('booking_items_count')->limit(5)->get();
        $vehicles = $vehicles->where('booking_items_count', '>', 0)
            ->sortByDesc('booking_items_count')
            ->map(function($launch, $key) {
                $nearestTrip = collect($launch->activeTrips)->first();
                return [
                    'vehicle_id' => $launch->id,
                    'trip_id' => $nearestTrip->id,
                    'trip_date' => $nearestTrip->schedule_date,
                    'leaving_at' => date('Y-m-d H:i:s', strtotime($nearestTrip->leaving_at)),
                    'total_booked' => $launch->booking_items_count,
                    'vehicle_name' => $launch->name,
                    'vehicle_photo' => ($launch->photo) ? asset('vehicles/'. $launch->photo) : asset('default/launch.png')
                ];
            });
        return response()->json(['success' => true, 'vehicles' => $vehicles, 'message' => ''], $this->success);
    }

    public function notifications(Request $request)
    {
        $user = Auth::user();
        $notifications = Auth::user()->unreadNotifications;

        $responseArr = [];
        if( $notifications ) {
            foreach( $notifications as $notification ) {
                $description = '';
                $title = 'New notification';
                switch ($notification->data['type']) {
                    case 'coupon':
                        $title = 'New coupon received';
                        $description .= 'Your have received new coupon.';
                        break;
                    case 'cancellation_request':
                        $title = 'New cancellation request received';
                        $description .= 'Your booking cancellation request has been placed for approval.';
                        break;
                    case 'cancellation_approved':
                        $title = 'Cancellation request approved';
                        $description .= 'Your booking cancellation request has been approved.';
                        break;
                    case 'cancellation_declined':
                        $title = 'Cancellation request declined';
                        $description .= 'Your booking cancellation request has been declined.';
                        break;
                    case 'cancellation_processing':
                        $title = 'Cancellation request processing';
                        $description .= 'Your booking cancellation request is now under processing.';
                        break;
                    case 'cancellation_refunded':
                        $title = 'Booking cancellation refunded';
                        $description .= 'Your booking cancellation amount has been refunded.';
                        break;
                }
                array_push($responseArr, [
                    'id' => $notification->id,
                    'label' => $notification->data['label'],
                    'time' => date('Y-m-d H:i:s', strtotime($notification->created_at)),
                    'title' => $title,
                    'message' => $description
                ]);
            }
        }

        return response()->json(['success' => true, 'notifications' => $responseArr], $this->success);
    }

    public function deleteNotifications( Request $request )
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string'
        ]);
        if( $validator->fails() == True ) {
            return response()->json( ['success' => true, 'message' => $validator->errors()->first()], $this->success );
        }
        DB::table('notifications')
            ->where('id', $request->id)
            ->update(['read_at' => now()]);

        return response()->json( ['success' => true, 'message' => __('Notification has been marked as read')], $this->success );
    }

    public function readAllNotification( Request $request )
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json( ['success' => true, 'message' => 'All unread notifications maked as read'], $this->success );
    }

    public function cancellations(Request $request ): JsonResponse
    {
        $user = Auth::user();
        $query = BookingCancellation::with(['bookingItems.launch', 'booking', 'bookingItems.trip.route', 'bookingItems.item.cabinType', 'bookingItems.trip.launch'])
            ->where('customer_id', $user->id)->orderBy('created_at', 'desc');

        if( $request->date_from ) {
            $date = \DateTime::createFromFormat('Y-m-d', $request->date_from);
            $query->where('created_at', '>=', $date->format('Y-m-d 00:00:00'));
        }

        if( $request->date_to ) {
            $date = \DateTime::createFromFormat('Y-m-d', $request->date_to);
            $query->where('created_at', '<=', $date->format('Y-m-d 23:59:59'));
        }

        $cancellations = $query->paginate(15);

        $responseArr = [];
        foreach( $cancellations as $cancellation ) {
            $row['id'] = $cancellation->id;
            $row['pnr'] = $cancellation->booking_id;
            $row['request_date'] = date('Y-m-d H:i:s', strtotime( $cancellation->created_at ) );
            $row['total_amount'] = 0;
            $row['total_discount'] = 0;
            $row['vat_total'] = 0;
            $row['charge_total'] = 0;
            $row['total_refundable'] = 0;
            $row['items'] = [];
            $row['status'] = 'Pending';
            switch ($cancellation->status) {
                case '1':
                    $row['status'] = 'Approved';
                    break;
                case '2':
                    $row['status'] = 'Declined';
                    break;
            }
            $items = explode(',', $cancellation->items);

            foreach( $cancellation->bookingItems as $item ) {
                if( in_array($item['id'], $items)) {
                    $irow = [
                        'id' => $item['id'],
                        'cabin_id' => $item['cabin_id'],
                        'cabin_no' => ( $item['cabin_type'] != 'deck' && $item['item']) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                        'cabin_type' => $item['booking_type'],
                        'fare' => $item['price'],
                        'is_ac' => ( $item['cabin_type'] != 'deck' && $item['item']) ? $item['item']['cabinType']['is_ac'] : 0,
                        'vehicle_name' => $item['trip']['launch']['name'],
                        'route_name' => $item['trip']['route']['route_name'],
                        'schedule_date' => $item['trip_date'],
                        'leaving_time' => $item['trip']['leaving_at'],
                        'leaving_time_formated' => date('h:i A', strtotime($item['trip']['leaving_at'])),
                        'boarding_point' => json_decode($item['boarding_point']),
                        'passenger' => json_decode($item['passenger']),
                        'status' => $item['status']
                    ];
                    $vat = abs($item['price']*($item['vat_amount']/100));
                    $charge = abs($item['price']*($item['charge_amount']/100));
                    $row['total_discount'] += $item['discount'];
                    $row['vat_total'] += $vat;
                    $row['charge_total'] += $charge;
                    $row['total_amount'] += abs($item['price'] + $vat + $charge - $item['discount']);
                    $row['total_refundable'] += abs($item['price'] - $item['discount']);
                    if( getOption('is_vat_refundable', 1) ) {
                        $row['total_refundable'] += abs($vat);
                    }
                    if( getOption('is_charge_refundable', 1) ) {
                        $row['total_refundable'] += abs($charge);
                    }
                    if( $item['trip']['schedule_type'] == 'reverse' ) {
                        $irow['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                    }
                    array_push($row['items'], $irow);
                }
            }
            array_push($responseArr, $row);
        }

        $data = [
            'total' => $cancellations->total(),
            'per_page' => $cancellations->perPage(),
            'last_page' => $cancellations->lastPage(),
            'current_page' => $cancellations->currentPage(),
            'data' => $responseArr
        ];
        return response()->json(['success' => true, 'cancellations' => $data ], $this->success );
    }

    public function activities()
    {
        $user = Auth::user();
        $activities = ActivityLog::where('user_id', $user->id)->orderBy('created_at', 'desc')->paginate(15);

        $data = [
            'total' => $activities->total(),
            'per_page' => $activities->perPage(),
            'last_page' => $activities->lastPage(),
            'current_page' => $activities->lastPage(),
            'data' => $activities->toArray()
        ];

        return response()->json(['success' => true, 'activities' => $activities->toArray()], $this->success);
    }

    public function cancelBooking( Request $request )
    {
        $data = ['success' => false, 'message' => __('Your request not success')];

        //validation rules
        $validator = Validator::make($request->all(), [
            'booking_id' => 'bail|required|integer|exists:bookings,id',
            'type' => 'bail|required|string',
            'items' => 'bail|required|array'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try{
                $booking = Booking::find($request->booking_id);

                // return $booking;
                $cancellation = BookingCancellation::create([
                    'booking_id' => $request->booking_id,
                    'type' => $request->type,
                    'customer_id' => $booking->customer_id,
                    'user_id' => Auth::user()->id,
                    'transaction_id' => $request->transaction_id,
                    'items' => implode(',', $request->items),
                    'vat_refundable' => getOption('is_vat_refundable', 1),
                    'charge_redundable' => getOption('is_charge_refundable', 1)
                ]);
                DB::commit();
                $data['success'] = true;
                $data['label'] = 'success';
                $data['content'] = __('Your cancellation request has been sent successfully.');
            } catch( \Exception $e ) {
                DB::rollback();
//                Log::debug($e->getMessage() );
            }
        }

        return response()->json($data, $this->success);
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function journey( Request $request )
    {
        $user = Auth::user();
        $journies = BookingItem::with(['item', 'trip.route', 'trip.launch'])
            ->where('customer_id', $user->id)->orderBy('trip_date', 'desc')->paginate(15);

        $responseArr = [];
        if( $journies ) {
            $grouped = [];
            foreach ( $journies as $journey ) {
                $grouped[$journey->trip_date][] = [
                    'route_name' => $journey->trip['route']['route_name'],
                    'booking_type' => $journey->booking_type,
                    'booking_fare' => $journey->price,
                    'vehicle_name' => $journey->trip['launch']['name'],
                    'vehicle_id' => $journey->trip['vehicle_id']
                ];
            }

            foreach( $grouped as $key => $v ) {
                $row['trip_date'] = $key;
                $row['trip_date_formated'] = date('d M, Y', strtotime( $key ));
                $row['items'] = $v;
                array_push( $responseArr, $row );
            }
        }

        $data = [
            'total' => $journies->total(),
            'per_page' => 10,
            'last_page' => $journies->lastPage(),
            'current_page' => $request->page,
            'data' => $responseArr
        ];

        return response()->json(['success' => true, 'data' => $data ], $this->success );
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function viewJourney( Request $request, $id )
    {
        return response()->json(['success' => true, 'data' => [] ], $this->success );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function updateProfile(Request $request)
    {
        //validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . Auth::user()->id,
            'mobile' => 'required|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|unique:users,mobile,' . Auth::user()->id
        ]);

        //validation fails
        if ( $validator->fails() )
            return response()->json(['success'=> false, 'message' => $validator->errors()->first()], $this->success );

        //update user
        $user = Auth::user();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->mobile = $request->mobile;

        if( $user->save() ) {
            $user->notify(new ProfileUpdate());
            return response()->json(['success'=> true, 'user' => ['name' => $user->name, 'mobile' => $user->mobile, 'email' => $user->email], 'message' => __('Profile successfully updated')], $this->success );
        } else {
            return response()->json(['success'=> false, 'message' => __('Ops! something went wrong.')], $this->success );
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(Request $request)
    {
        //validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        //validation fails
        if ( $validator->fails() )
            return response()->json(['success'=> false, 'message' => $validator->errors()->first()], $this->success );

        //update user
        $user = Auth::user();
        $user->name = $request->name;

        if( $user->save() ) {
            $user->notify(new ProfileUpdate());
            return response()->json(['success'=> true, 'name' => $user->name, 'message' => __('Profile successfully updated')], $this->success );
        } else {
            return response()->json(['success'=> false, 'message' => __('Ops! something went wrong.')], $this->success );
        }
    }

    //change email
    public function changeEmail(Request $request)
    {
        $data = ['success' => false, 'message' => __('Cannot change email')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . Auth::user()->id
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            //update user
            $user = Auth::user();
            $user->email = $request->email;

            if( $user->save() ) {
                $user->notify(new ProfileUpdate());
                $data['email'] = $user->email;
                $data['success'] = true;
                $data['message'] = __('Email successfully changed');
            } else {
                $data['message'] = __('Ops! something went wrong.');
            }
        }

        return response()->json($data, $this->success );
    }

    //change email
    public function changeMobile(Request $request)
    {
        $data = ['success' => false, 'message' => __('Cannot change mobile')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|unique:users,mobile,' . Auth::user()->id
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            //update user
            $user = Auth::user();
            $user->mobile = $request->mobile;

            if( $user->save() ) {
                $user->notify(new ProfileUpdate());
                $data['mobile'] = $user->mobile;
                $data['success'] = true;
                $data['message'] = __('Mobile successfully changed');
            } else {
                $data['message'] = __('Ops! something went wrong.');
            }
        }

        return response()->json($data, $this->success );
    }

    //change password
    public function changePassword(Request $request)
    {
        $data = ['success' => false, 'message' => __('Cannot change password')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'password' => 'required|max:14|min:6',
            'confirm_password' => 'required|same:password'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            //update user
            $user = Auth::user();
            $user->password = Hash::make($request->password);

            if( $user->save() ) {
                $data['success'] = true;
                $data['message'] = __('Password successfully changed');
            } else {
                $data['message'] = __('Ops! something went wrong.');
            }
        }

        return response()->json($data, $this->success );
    }

    public function upload( Request $request )
    {
        //validation rules
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|string'
        ]);

        //validation fails
        if ( $validator->fails() )
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);

        $user = Auth::user();

        $filename = $user->name . '-' . time() . '.png';

        $img = Image::make(file_get_contents($request->avatar))->save(public_path() . '/uploads/temp/' . $filename);
        if(file_exists(public_path() . '/uploads/temp/' . $filename) ) {
            $img->resize(300, 300, function ($constraint) {
                $constraint->aspectRatio();
            })->save(public_path() . '/uploads/avatar/' .$filename);
            unlink(public_path() . '/uploads/temp/' . $filename);
        }
        $user->profile_pic = "uploads/avatar/" . $filename;

        if( $user->save() ) :
            return response()->json(['success' => true, 'avatar' => asset( "uploads/avatar/" . $filename ), 'message' => __('Your profile picture successfully uploaded')], $this->success);
        else :
            return response()->json(['success' => false, 'message' => __('Sorry! upload fail.') ] );
        endif;
    }

    public function uploadProcedural( Request $request )
    {
        //validation rules
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|max:10000|mimes:jpg,jpeg,png,gif'
        ]);

        //validation fails
        if ( $validator->fails() )
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);

        $user = Auth::user();

        if( $request->avatar ) {
            $image = $request->file('avatar');
            $filename = $user->id . '_' . time().'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/uploads/avatar');
            $img = Image::make($image->getRealPath());
            $img->resize(460, 340, function ($constraint) {
                $constraint->aspectRatio();
            })->save($destinationPath.'/'.$filename);
            $user->profile_pic = 'uploads/avatar/' . $filename;
        }

        if( $user->save() ) :
            return response()->json(['success' => true, 'avatar' => asset( $user->profile_pic ), 'message' => __('Your profile picture successfully uploaded')], $this->success);
        else :
            return response()->json(['success' => false, 'message' => __('Sorry! upload fail.') ] );
        endif;
    }

    public function wallet(Request $request): JsonResponse
    {
        $wallet = $this->supervisor->getWallet($request->all());

        return response()->json(['success' => true, 'data' => $wallet], $this->success);
    }
}
