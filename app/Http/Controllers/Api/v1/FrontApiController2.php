<?php
namespace App\Http\Controllers\Api\v1;

use App\Constants\AppConst;
use App\Models\Cabin;
use App\Models\CabinType;
use App\Models\Coupon;
use App\Models\DeckFare;
use App\Models\Ghat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\VehicleSchedule;
use App\Models\Merchant;
use App\Models\Vehicle;
use App\Models\Option;
use App\Models\Page;
use App\Services\TripService;
use App\Models\Sponsor;

class FrontApiController2 extends Controller
{
    private $status;
    private $success;
    private $trip;

    public function __construct( TripService $tripService )
    {
        $this->trip = $tripService;
        $this->status = 200;
        $this->success = 200;
    }

    public function init()
    {
        $data['options'] = Option::whereIn('tab', ['general', 'booking', 'customer', 'cancellation', 'vatcharge', 'facts'])->pluck('value', 'field');
        $data['suggestions'] = Ghat::select('name', 'id')->orderBy('name', 'asc')->distinct()->get();
        $partners = Merchant::select('merchant_name', 'logo', 'id')->orderBy('merchant_name', 'asc')->where('status', 1)->limit(10)->get();
        $data['partners'] = $partners->map(function ($item) {
            $item->logo = ($item->logo) ? asset('images/' . $item->logo) : asset('default/avatar.png');

            return $item;
        });
        $offers = Coupon::select('poster')->where('is_offer', 1)->take(6)->get();
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
        return response()->json(['success' => true, 'data' => $data], $this->success);
    }

    public function mobileInit()
    {
        $data['options'] = Option::whereIn('tab', ['general', 'booking', 'customer', 'cancellation', 'vatcharge', 'facts'])->pluck('value', 'field');
        if(empty($data['options']) ) {
           $data['options'] = null;
        }
        return response()->json(['success' => true, 'data' => $data], $this->success);
    }

    public function downloadLink( Request $request )
    {
        $data = ['success' => false, 'message' => 'Sorry! cannot send download link'];
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
            $data['message'] = 'Download link has successfully sent to your mobile number';
        }
        return response()->json($data, $this->success);
    }

    public function page( Request $request, $slug )
    {
        $slug = (string) $slug;
        $page = Page::where('slug', $slug)->firstOrFail();

        return response()->json(['success' => true, 'data' => $page], $this->success);
    }

    public function offers()
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

    public function vehicles()
    {
        $vehicles = Vehicle::whereHas('merchant', function ($q) {
            $q->where('status', '1');
        })->pluck('name', 'id');

        return response()->json(['success' => true, 'data' => $vehicles], $this->success);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $schedules = $this->trip->getSearchTrip($request);

        return response()->json(['success' => true, 'data' => $schedules], $this->success);
    }

    public function searchAvailable(Request $request)
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
            // activity()->log($log);
            \LogActivity::addToLog($log);
        }

        $returnArray = [];

        if ($results) {
//            dd($results);
            foreach ($results as $result) {
                $row['trip_id'] = $result->id;
                $row['route_id'] = $result->route_id;
                $row['route_name'] = $result->route['route_name'];
                $routeArr = explode('-', $result->route['route_name']);
                if( $result->schedule_type == 'reverse' && (count($routeArr) > 1)) {
                    $row['route_name'] = $routeArr[1] . '-' . $routeArr[0];
                }
                $row['vehicle_id'] = $result->vehicle_id;
                $row['vehicle_name'] = $result->launch['name'];
                $row['vehicle_photo'] = ($result->launch['photo'] != null) ? asset('vehicles/' . $result->launch['photo']) : asset('default/launch.png');
                $row['schedule_date'] = $result->schedule_date;
                $row['schedule_type'] = $result->schedule_type;
                $row['leaving_at'] = date('Y-m-d H:i:s', strtotime($result->leaving_at));
                $row['leaving_time'] = date('h:i A', strtotime($result->leaving_at));
                $row['total_cabins'] = (int) $result->cabinMappings->count();
                $row['cabin_available'] = $row['total_cabins'];
                $row['total_seats'] = (int) $result->seatMappings->count();
                $row['seat_available'] = (int) $result->seatMappings->count();
                $row['total_tickets'] = $result->launch['passengers_capacity'];
                $row['ticket_available'] = $row['total_tickets'];
                $row['starting_point'] = $result->startingPoint['ghat']['name'];
                $row['ending_point'] = $result->endingPoint['ghat']['name'];
                $row['stoppages'] = [];

                array_push($row['stoppages'], [
                    'id' => $result->startingPoint['id'],
                    'name' => $result->startingPoint['ghat']['name'],
                    'type' => $result->startingPoint['type']
                ]);
                foreach ($result->boardingVias as $stoppage) {
                    $prop['id'] = $stoppage['id'];
                    $prop['name'] = $stoppage['ghat']['name'];
                    $prop['type'] = $stoppage['type'];
                    array_push($row['stoppages'], $prop);
                }
                array_push($row['stoppages'], [
                    'id' => $result->endingPoint['id'],
                    'name' => $result->endingPoint['ghat']['name'],
                    'type' => $result->endingPoint['type']
                ]);

                if( $result->schedule_type == 'reverse' ) {
                    krsort( $row['stoppages'] );
                    $row['stoppages'] = array_values( $row['stoppages'] );
                }

                $books = [];
                if( $result->bookingItems ) {
                    foreach( $result->bookingItems as $item ) {
                        array_push($books, $item['cabin_id']);
                    }
                }

                $locks = [];
                if( $result->locks ) {
                    foreach( $result->locks as $lock ) {
                        array_push($locks, $lock['cabin_id']);
                    }
                }

                if ($result->cabinMappings) {
                    foreach ($result->cabinMappings as $cabin) {
                        if( in_array($cabin['cabin_id'], $books) || in_array($cabin['cabin_id'], $locks) || ($cabin['ownership'] != AppConst::OWNER) || ($cabin['is_reserved'])) {
                            $row['cabin_available'] -= 1;
                        }
                    }
                }

                if ($result->seatMappings) {
                    // dd( $result->seatMappings );
                    foreach ($result->seatMappings as $seat) {
                        if( in_array($seat['cabin_id'], $books) || in_array($seat['cabin_id'], $locks)  || ($seat['ownership'] != AppConst::OWNER) || ($seat['is_reserved'])) {
                            $row['seat_available'] -= 1;
                        }
                    }
                }

                array_push($returnArray, $row);
            }
        }

        return response()->json(['success' => true, 'data' => $returnArray], $this->success);
    }

    /**
     * Display a tip details
     * Parameter is trip id
     * @return \Illuminate\Http\JsonResponse
     */
    public function trip(Request $request, $id)
    {
        $trip = VehicleSchedule::with(['bookingItems' => function($q) use($id) {
            $q->where('trip_id', $id);
        }, 'locks' => function($q) use($id) {
            $q->where('trip_id', $id);
        },'launch.merchant', 'route.startingPoint.ghat', 'route.endingPoint.ghat', 'route.boardingVias.ghat', 'mappings'])->findOrFail($id);

        $returnArray = [
            'id' => $trip->id,
            'vehicle_id' => $trip->vehicle_id,
            'merchant_id' => $trip->launch['merchant_id'],
            'route_id' => $trip->route_id,
            'vehicle_name' => $trip->launch['name'],
            'launch_route' => $trip->route['route_name'],
            'schedule_date' => date('Y-m-d H:i:s', strtotime($trip->leaving_at)),
            'scheduled_date' => date('D M, Y', strtotime($trip->leaving_at)),
            'date' => date('d', strtotime($trip->schedule_date)),
            'month' => date('M', strtotime($trip->schedule_date)),
            'cabin_rows' => 3,
            'rowClass' => 'col-sm-4 col-xs-4',
            'cabins' => [],
            'seats' => [],
            'decks' => [],
            'cabin_types' => [],
            'seat_types' => [],
            'stoppages' => [],
            'vat_amount' => getOption('vat_amount', 0),
            'vat_applicable_to' => $trip['launch']['merchant']['vat_applicable_to'],
            'vat_visibility' => $trip['launch']['merchant']['vat_visibility']
        ];

        if( $trip->schedule_type == 'reverse' ) {
            $routeName = explode('-', $returnArray['launch_route']);
            $returnArray['launch_route'] = trim($routeName[1]) . ' - ' . trim($routeName['0']);
        }

        $floor = ( int )($request->floor) ? $request->floor : 1;

        $query = Cabin::with(['cabinType'])->where(['vehicle_id' => $trip->vehicle_id, 'floor' => $floor]);

        $cabins = $query->orderBy('cabin_row', 'asc')->orderBy('cabin_position', 'asc')->get();

        $tripMappings = [];
        if( $trip->mappings ) {
            foreach( $trip->mappings as $mapping ) {
                array_push($tripMappings, $mapping->cabin_id);
            }
        }

        $books = [];
        if( $trip->bookingItems ) {
            foreach( $trip->bookingItems as $item ) {
                array_push($books, $item['cabin_id']);
            }
        }


        $locks = [];
        if( $trip->locks ) {
            foreach( $trip->locks as $lock ) {
                array_push($locks, $lock['cabin_id']);
            }
        }
        // return response()->json($locks);

        $mappings = new Collection($trip->mappings);

        if ($cabins) {
            foreach ($cabins as $cabin) {
                $row['trip_id'] = $trip->id;
                $row['trip_date'] = date('Y-m-d H:i:s', strtotime($trip->leaving_at));
                $row['route_id'] = $trip->route_id;
                $row['vehicle_id'] = $cabin['vehicle_id'];
                $row['vehicle_name'] = $trip->launch['name'];
                $row['merchant_id'] = $trip->launch['merchant_id'];
                $row['cabin_id'] = $cabin['id'];
                $row['cabin_type_id'] = $cabin['type_id'];
                $row['cabin_type'] = $cabin['type'];
                $row['cabin_floor'] = $cabin['floor'];
                $row['cabin_no'] = ($cabin['type'] == 'cabin') ? $cabin['cabinType']['letter'] . '-' . $cabin['cabin_no'] : $cabin['cabin_no'];
                $row['fare'] = $cabin['fare'];
                $row['cabin_is_ac'] = $cabin['cabinType']['is_ac'];
                $row['capacity'] = $cabin['passenger_capacity'];
                $row['cabin_row'] = $cabin['cabin_row'];
                $row['cabin_position'] = $cabin['cabin_position'];
                $row['description'] = ($cabin->type == 'cabin') ? $cabin['cabinType']['name'] . ' - ' . $cabin['cabinType']['letter'] . '-' . $cabin['cabin_no'] : $cabin['cabin_no'];
                $row['status'] = 1;
                $row['cabin_class'] = 'cabin-active';
                if( in_array($cabin['id'], $tripMappings) ) {
                    $mapping = $mappings->where('cabin_id', $cabin['id'])->first();
                    if( ($mapping->ownership != AppConst::OWNER) || ($mapping->is_reserved == 1)) {
                        $row['status'] = 0;
                        $row['cabin_class'] = 'cabin-disable';
                    }

                    if( in_array($cabin['id'], $books) || in_array($cabin['id'], $locks) ) {
                        $row['status'] = 0;
                        $row['cabin_class'] = 'cabin-disable';
                    }
                    if( $request->cabin_type > 0 ) {
                        if($row['cabin_type'] == 'cabin' && $row['cabin_type_id'] != $request->cabin_type ) {
                            $row['cabin_class'] = 'cabin-disable';
                        }
                    }
                    if( $request->seat_type > 0 ) {
                        if($row['cabin_type'] == 'seat' && $row['cabin_type_id'] != $request->seat_type ) {
                            $row['cabin_class'] = 'cabin-disable';
                        }
                    }

                    if ($cabin['type'] == 'cabin') {
                        array_push($returnArray['cabins'], $row);
                        $returnArray['cabin_types'][$cabin['type_id']] = $cabin['cabinType']['name'];
                    } elseif ($cabin['type'] == 'seat') {
                        array_push($returnArray['seats'], $row);
                        $returnArray['seat_types'][$cabin['type_id']] = $cabin['cabinType']['name'];
                    }
                }
            }
        }

        //fetch deck fares
        $deckFares = new Collection(DeckFare::with(['departureFrom.ghat', 'departureTo.ghat'])->where('route_id', $trip->route_id)->get());
        $launchDefined = $deckFares->where('vehicle_id', $trip->vehicle_id);

        if ($launchDefined) {
            foreach ($launchDefined as $deckfare) {
                $deck['id'] = $deckfare['id'];
                $deck['from'] = ($trip->schedule_type == 'reverse') ? $deckfare['departureTo']['ghat']['name'] : $deckfare['departureFrom']['ghat']['name'];
                $deck['to'] = ($trip->schedule_type == 'reverse') ? $deckfare['departureFrom']['ghat']['name'] : $deckfare['departureTo']['ghat']['name'];
                $deck['fare'] = ($trip->schedule_type == 'reverse') ? $deckfare['reverse_fare'] : $deckfare['fare'];
                array_push($returnArray['decks'], $deck);
            }
        } else {
            foreach ($deckFares as $deckfare) {
                if ($deckfare->vehicle_id == '') {
                    $deck['from'] = ( $trip->schedule_type == 'reverse' ) ? $deckfare['departureTo']['ghat']['name'] : $deckfare['departureFrom']['ghat']['name'];
                    $deck['to'] = ( $trip->schedule_type == 'reverse' ) ?$deckfare['departureTo']['ghat']['name'] : $deckfare['departureTo']['ghat']['name'];
                    $deck['fare'] = ($trip->schedule_type == 'reverse') ? $deckfare['reverse_fare'] : $deckfare['fare'];
                    array_push($returnArray['decks'], $deck);
                }
            }
        }

        //push stoppages
        array_push($returnArray['stoppages'], [
            'id' => $trip->route['startingPoint']['id'],
            'name' => $trip->route['startingPoint']['ghat']['name'],
            'type' => $trip->route['startingPoint']['type']
        ]);
        if ($trip->route['boardingVias']) {
            foreach ($trip->route['boardingVias'] as $stoppage) {
                array_push($returnArray['stoppages'], ['id' => $stoppage['id'], 'name' => $stoppage['ghat']['name']]);
            }
        }
        array_push($returnArray['stoppages'], [
            'id' => $trip->route['endingPoint']['id'],
            'name' => $trip->route['endingPoint']['ghat']['name'],
            'type' => $trip->route['endingPoint']['type']
        ]);

        $cabins = ($returnArray['cabins']) ? _my_group_by($returnArray['cabins'], 'cabin_row') : null;
        $seats = ($returnArray['seats']) ? _my_group_by($returnArray['seats'], 'cabin_row') : null;
        $returnArray['cabins'] = _my_layout($cabins);
        $returnArray['seats'] = _my_layout($seats);
        if ($returnArray['cabins']) {
            ksort($returnArray['cabins']);
        }
        if ($returnArray['seats']) {
            ksort($returnArray['seats']);
        }
        if ($returnArray['cabin_types']) {
            $types = [];
            foreach ($returnArray['cabin_types'] as $key => $type) {
                array_push($types, ['id' => $key, 'name' => $type]);
            }
            $returnArray['cabin_types'] = $types;
        }
        if ($returnArray['seat_types']) {
            $types = [];
            foreach ($returnArray['seat_types'] as $key => $type) {
                array_push($types, ['id' => $key, 'name' => $type]);
            }
            $returnArray['seat_types'] = $types;
        }
        return response()->json(['success' => true, 'data' => $returnArray], $this->success);
    }

    public function suggest($term = '', $accept = '')
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
