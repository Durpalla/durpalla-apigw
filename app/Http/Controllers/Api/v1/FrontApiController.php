<?php
namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use App\Models\CabinType;
use App\Models\Coupon;
use App\Models\Ghat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\Services;
use App\Models\VehicleSchedule;
use App\Models\Merchant;
use App\Models\Vehicle;
use App\Models\Option;
use App\Models\Page;
use App\Services\TripService;
use App\Models\Sponsor;

class FrontApiController extends Controller
{
    private $status;
    private $success;
    private $trip;
    private $services;

    public function __construct(
        TripService $tripService,
        Services $services
    )
    {
        $this->trip = $tripService;
        $this->services = $services;
        $this->status = 200;
        $this->success = 200;
    }

    public function init(): JsonResponse
    {
        $data['options'] = Option::whereIn('tab', ['general', 'booking', 'customer', 'cancellation', 'vatcharge', 'facts'])->pluck('value', 'field');
        $data['suggestions'] = Ghat::select('name', 'id')
            ->orderBy('name', 'asc')->distinct()->get()
            ->map(function ($item, $key) {
                return [
                    'label' => $item->name,
                    'value' => $item->id
                    ];
            });
        $partners = Merchant::select('merchant_name', 'logo', 'id')->orderBy('merchant_name', 'asc')->where('status', 1)->limit(10)->get();
        $data['partners'] = $partners->map(function ($item) {
            $item->logo = ($item->logo) ? asset('images/' . $item->logo) : asset('default/avatar.png');

            return $item;
        });
        $offers = Coupon::select('poster')->where('offer_end', '>=', date('Y-m-d'))->where(['is_offer' => 1, 'status' => 1])->take(6)->get();
        // ->where('poster', '!=', null)
        $data['offers'] = $offers->map(function ($item) {
            $item->thumbnail = asset($item->poster);
            return $item;
        });
        $sponsors = Sponsor::where('status', 1)->get();
        $data['sponsors'] = $sponsors->map(function ($item) {
            $item->attachment = asset($item->attachment);
            return $item;
        });
        $data['cabin_types'] = CabinType::where('type', 'cabin')->pluck('name', 'id');
        $data['seat_types'] = CabinType::where('type', 'seat')->pluck('name', 'id');
        $data['services'] = $this->services->getServiceStatuses();
        return response()->json(['success' => true, 'data' => $data], $this->success);
    }

    public function mobileInit(): JsonResponse
    {
        $data['options'] = Option::whereIn('tab', ['general', 'booking', 'customer', 'cancellation', 'vatcharge', 'facts'])->pluck('value', 'field');
        if(empty($data['options']) ) {
            $data['options'] = null;
        }
        return response()->json(['success' => true, 'data' => $data], $this->success);
    }

    public function downloadLink( Request $request ): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Sorry! cannot send download link')];
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11'
        ]);

        if( $validator->fails() == true ) {
            $data['message'] = $validator->errors()->first();
        } else {
            sendSMS([
                'mobile' => $request->mobile,
                'message' => 'Click to download ' . config('app.name') . ' App . ' . getOption('google_play_short', 'https://play.googld.com')
            ]);
            $data['success'] = true;
            $data['message'] = __('Download link has successfully sent to your mobile number');
        }
        return response()->json($data, $this->success);
    }

    public function offers(): JsonResponse
    {
        $offers = Coupon::select('poster')->where('is_offer', 1)->take(6)->get();
        // ->where('poster', '!=', null)
        $offers = $offers->map(function ($item) {
            $item->thumbnail = asset($item->poster);
            $item->poster = asset($item->poster);
            return $item;
        })->toArray();
        return response()->json(['success' => true, 'data' => $offers], $this->success);
    }

    public function vehicles(): JsonResponse
    {
        $vehicles = Vehicle::whereHas('merchant', function ($q) {
            $q->where('status', '1');
        })->pluck('name', 'id');

        return response()->json(['success' => true, 'data' => $vehicles], $this->success);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $schedules = $this->trip->getSearchTrip($request);

        return response()->json(['success' => true, 'data' => $schedules], $this->success);
    }

    public function searchAvailable(Request $request): JsonResponse
    {
        $results = null;
        $log = 'Search trip ';
        $query = VehicleSchedule::with(['route', 'startingPoint.ghat', 'endingPoint.ghat', 'boardingVias.ghat', 'startFrom', 'stopTo', 'cabinMappings', 'seatMappings', 'locks', 'bookingItems'])->where('status', 'ACTIVE')->where('schedule_date', '>=', date('Y-m-d'));

        if ($request->trip_date) {
            $date = (!empty($request->trip_date)) ? date('Y-m-d', strtotime($request->trip_date)) : date('Y-m-d');
            $log .= 'Date: ' . date('d/m/Y', strtotime($date));
            $query->where('schedule_date', $date);
            if( $date == date('Y-m-d') ) {
                $leaving_at = date('Y-m-d H:i:s');
                $query->where('leaving_at', '>=', $leaving_at);
            }
        }

        if( !empty( $request->trip_from ) ) {
            $log .= ' From: ' . ucfirst($request->trip_from);
            $query->whereHas('startFrom', function($q) use ( $request ) {
                $q->where('name', $request->trip_from);
            });
        }

        if( !empty( $request->trip_to ) ) {
            $log .= ' To: ' . ucfirst($request->trip_to);
            $query->whereHas('stopTo', function($q) use ( $request ) {
                $q->where('name', $request->trip_to);
            });
        }

        if (!empty($request->vehicle_name)) {
            $log .= ' Launch: ' . ucfirst($request->vehicle_name);
            $query->where('schedule_date', '>=', date('Y-m-d'));
            $query->whereHas('launch', function ($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->vehicle_name . '%');
            });
        }
        $onWay = $query->orderBy('schedule_date', 'asc');

        if( $request->trip_return_date ) {
            $query2 = VehicleSchedule::with(['route', 'startingPoint.ghat', 'endingPoint.ghat', 'boardingVias.ghat', 'startFrom', 'stopTo', 'cabinMappings', 'seatMappings', 'locks', 'bookingItems'])->where('status', 'ACTIVE')->where('schedule_date', '>=', date('Y-m-d'));

            if ($request->trip_return_date) {
                $reverse_date = date('Y-m-d', strtotime( $request->trip_return_date ) );
                $log .= ' Return date: ' . date('d/m/Y', strtotime($request->trip_return_date));
                $query2->where('schedule_date', $reverse_date);
            }

            if( !empty( $request->trip_from ) ) {
                $query2->whereHas('stopTo', function($q) use ( $request ) {
                    $q->where('name', $request->trip_from);
                });
            }

            if( !empty( $request->trip_to ) ) {
                $query2->whereHas('startFrom', function($q) use ( $request ) {
                    $q->where('name', $request->trip_to);
                });
            }

            if (!empty($request->vehicle_name)) {
                $query2->where('schedule_date', '>=', date('Y-m-d'));
                $query2->whereHas('launch', function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->vehicle_name . '%');
                });
            }

            $roundTrip = $query2->unionAll($onWay)->orderBy('schedule_date', 'asc')->get();
            $results = $roundTrip;
        } else {
            $results = $onWay->get();
        }

        if( Auth::check() ) {
//             activity()->log($log);
        }

        $trips = $results->map(function($trip, $key) {
            return $this->trip->formatTripList($trip);
        });

        return response()->json(['success' => true, 'data' => $trips], $this->success);
    }

    /**
     * Display a tip details
     * Parameter is trip id
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function trip(Request $request, $id): JsonResponse
    {
        $layout = collect(VehicleSchedule::with(['route', 'decks.departureFrom.ghat', 'decks.departureTo.ghat', 'boardingVias.ghat', 'startFrom', 'stopTo', 'mappings.cabinType', 'vehicle', 'merchant'])
            ->where('id', $id)
            ->get())
            ->map(function($trip, $key) use($request) {
                return $this->trip->formatTriplayout($trip, $request->floor);
            })->first();

        return response()->json(['success' => true, 'data' => $layout], $this->success);
    }

    public function suggest($term = '', $accept = ''): JsonResponse
    {
        $query = Ghat::select('name', 'id')->distinct();

        if ($term) {
            $query->where('name', 'LIKE', '%' . $term . '%');
        }

        if ($accept) {
            $query->whereNotIn('name', [$accept]);
        }

        return response()->json(['success' => true, 'data' => $query->get()], $this->success);
    }
}
