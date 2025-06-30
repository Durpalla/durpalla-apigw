<?php

namespace App\Http\Controllers\Dashboard;

use App\Constants\AppConst;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use App\Models\BookingItem;
use App\Models\Cabin;
use App\Models\CabinType;
use App\Models\DeckFare;
use App\Models\Ghat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\VehicleCreateRequest;
use App\Http\Requests\VehicleUpdateRequest;
use App\Services\UploadService;
use App\Models\VehicleRoute;
use App\Models\VehicleSchedule;
use App\Models\Vehicle;
use App\Services\VehicleService;
use App\Services\ReportService;
use App\Models\User;
use Modules\Vehicle\Events\VehicleInactiveEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class VehicleController extends Controller
{
    protected $success = 200;
    private $vehicleService;
    private $reportService;
    private $upload;

    public function __construct(
        VehicleService $vehicleService,
        ReportService $reportService,
        UploadService $upload
    )
    {
        $this->vehicleService = $vehicleService;
        $this->reportService = $reportService;
        $this->upload = $upload;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = isset($_GET['type']) ? $_GET['type'] : 'launch';
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = Vehicle::with(['user', 'cabins.cabinType', 'seats.cabinType', 'merchant', 'supervisors'])->where('vehicle_type', $type);

            //conditions for merchant users
            if (Auth::user()->type == 'merchant') {
                if (Auth::user()->hasRole('merchant')) {
                    $query->where('merchant_id', Auth::user()->id);
                } elseif (Auth::user()->hasRole('supervisor')) {
                    $query->whereHas('supervisors', function ($q) {
                        $q->where('supervisor_id', Auth::user()->id);
                    });
                } else {
                    $query->where('merchant_id', Auth::user()->merchant_id);
                }
            }

            //filter by keyword
            if (isset($_GET['keyword']) && $_GET['keyword'] != null) {
                $keyword = $_GET['keyword'];
                $query->where('name', 'LIKE', "%$keyword%");
            }

            if (isset($_GET['route']) && $_GET['route'] != null) {
                $route = ( int )$_GET['route'];
                $query->where('route_id', $route);
            }

            if (isset($_GET['merchant']) && $_GET['merchant'] != null) {
                $merchant = ( int )$_GET['merchant'];
                $query->where('merchant_id', $merchant);
            }

            if (isset($_GET['status']) && $_GET['status'] != null) {
                $status = ( int )$_GET['status'];
                if ($status == 9) {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }

            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $query->orderByDesc('created_at');
            $vehicles = $query->get();

            //sanitize data
            $returnArr = array();
            if ($vehicles) {
                foreach ($vehicles as $vehicle) {
                    $row['id'] = $vehicle->id;
                    $row['name'] = $vehicle->name;
                    $row['route_id'] = $vehicle->route['route_name'];
                    $row['registration_no'] = $vehicle->registration_no;
                    $row['registration_expiry_date'] = $vehicle->registration_expiry_date;
                    $row['capacity'] = $vehicle->passengers_capacity;
                    if ($vehicle->cabins) {
                        foreach ($vehicle->cabins as $cabin) {
                            $row['capacity'] += ($cabin['cabinType']) ? round($cabin['cabinType']['capacity']) : 1;
                        }
                    }
                    if ($vehicle->seats) {
                        foreach ($vehicle->seats as $seat) {
                            $row['capacity'] += ($seat['cabinType']) ? round($seat['cabinType']['capacity']) : 1;
                        }
                    }
                    $row['merchant_name'] = $vehicle->merchant['merchant_name'];
                    $row['merchant_id'] = $vehicle->merchant['id'];
                    $row['joining_date'] = date('d M, Y', strtotime($vehicle->created_at));
                    $row['deleted_at'] = ($vehicle->deleted_at) ? true : false;
                    $row['status'] = $vehicle->status;
                    $row['photo'] = ($vehicle->photo != null) ? asset('vehicles/' . $vehicle->photo) : asset('default/launch.png');
                    array_push($returnArr, $row);
                }
            }

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $returnArr
            ];

            return response()->json($data);
        }
        return view('admin.launch.index', compact('type'))->withTitle(ucwords(str_replace('-', ' ', $type)));
    }

    public function cabins(Request $request, $vehicle_id)
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');
            $type = (isset($_GET['type'])) ? $_GET['type'] : 'cabin';
            $query = Cabin::with(['cabinType', 'ghat', 'vehicle'])->where(['vehicle_id' => $vehicle_id, 'type' => $type]);

            //filter by floor
            if (isset($_GET['floor']) && $_GET['floor'] != null) {
                $floor = $_GET['floor'];
                $query->where('floor', $floor);
            }
            //filter by row
            if (isset($_GET['row']) && $_GET['row'] != null) {
                $cabinRow = $_GET['row'];
                $query->where('cabin_row', $cabinRow);
            }
            //filter by row
            if (isset($_GET['no']) && $_GET['no'] != null) {
                $cabinNo = $_GET['no'];
                $query->where('cabin_no', $cabinNo);
            }
            //filter by row
            if (isset($_GET['owner']) && $_GET['owner'] != null) {
                $cabinOwner = $_GET['owner'];
                $query->where('ownership', $cabinOwner);
            }
            //filter by row
            if (isset($_GET['cabin_type']) && $_GET['cabin_type'] != null) {
                $cabinType = (int)$_GET['cabin_type'];
                $query->where('type_id', $cabinType);
            }
            //filter by row
            if (isset($_GET['reservation']) && $_GET['reservation'] != null) {
                $cabinReserved = (int)$_GET['reservation'];
                $query->where('is_reserved', $cabinReserved);
            }

            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $cabins = $query->get();

            //sanitize data
            $returnArr = array();
            if ($cabins) {
                foreach ($cabins as $cabin) {
                    $row['id'] = $cabin->id;
                    $row['cabin_no'] = ($cabin->cabinType) ? strtoupper($cabin->cabinType['letter']) . '-' . $cabin->cabin_no : $cabin->cabin_no;
                    $row['fare'] = $cabin->fare;
                    $row['capacity'] = $cabin->passenger_capacity;
                    $row['type_id'] = $cabin->type_id;
                    $row['type_name'] = ($cabin->cabinType) ? $cabin->cabinType['name'] : '';
                    $row['is_ac'] = ($cabin->cabinType && $cabin->cabinType['is_ac']) ? 'AC' : 'None AC';
                    switch ($cabin->floor) {
                        case '1':
                            $row['floor'] = ($cabin->vehicle->vehicle_type == 'bus') ? 'Lower' : '1st floor';
                            break;
                        case '2':
                            $row['floor'] = ($cabin->vehicle->vehicle_type == 'bus') ? 'Lower' : '2nd floor';
                            break;
                        case '3':
                            $row['floor'] = '3rd floor';
                            break;
                        case '4':
                            $row['floor'] = '4th floor';
                            break;
                        case '5':
                            $row['floor'] = '5th floor';
                            break;
                    }
                    $row['service_charge'] = $cabin->service_charge;
                    $row['position'] = $cabin->cabin_position;
                    $row['row'] = $cabin->cabin_row;
                    $row['ownership'] = ucfirst($cabin->ownership);
                    $row['counter'] = ($cabin->ghat != null) ? $cabin->ghat['name'] : 'N/A';
                    $row['is_reserved'] = ($cabin->is_reserved) ? 'Y' : 'N';
                    array_push($returnArr, $row);
                }
            }

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $returnArr
            ];

            return response()->json($data);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create($id = '')
    {
        $routes = VehicleRoute::get();
        $type = (isset($_GET['type'])) ? $_GET['type'] : 'launch';
        return view('admin.launch.create', compact('routes', 'id', 'type'))->withTitle('Add new ' . $type);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return RedirectResponse
     */
    public function store(VehicleCreateRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request) {
                if($request->hasFile('photo')) {
                    $request->merge([
                        'photo' => $this->upload->upload($request->photo)
                    ]);
                }
                $this->vehicleService->create($request->validated());
            }, 2);
            return redirect()->route('dashboard.vehicle.index', ['type' => $request->vehicle_type]);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage() . ' on line-' . $e->getLine());
        }

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        $user = Auth::user();
        $vehicle = Vehicle::with(['merchant', 'cabins.cabinType', 'seats', 'route.startingPoint', 'route.endingPoint', 'route.boardingVias', 'supervisors.user', 'supervisors.assignator'])->findOrFail($id);

        $schedules = VehicleSchedule::with(['route.boardingPoints', 'route.startingPoint', 'route.endingPoint', 'cabins'])
            ->withCount(['cabinBookings', 'seatBookings', 'cabinMappings', 'seatMappings'])
            ->where(['vehicle_id' => $vehicle->id])
            ->where('schedule_date', '>=', date('Y-m-d'))
            ->where('status', AppConst::SCHEDULE_ACTIVE)
            ->orderBy('leaving_at', 'asc')
            ->get();

        $deckFares = DeckFare::with(['departureFrom', 'departureTo'])->where(['route_id' => $vehicle->route_id, 'merchant_id' => $vehicle->merchant_id])->get();

        $routes = VehicleRoute::where('service_type', $vehicle->vehicle_type)->get();
        $cabin_types = CabinType::get();
        $ghats = Ghat::where('service_type', $vehicle->vehicle_type)->pluck('name', 'id');
        $vehicle->cabins_count = count($vehicle->cabins);
        $vehicle->seats_count = count($vehicle->seats);
        $vehicle->cabins = _my_group_by($vehicle->cabins, 'cabin_row');
        $vehicle->seats = _my_group_by($vehicle->seats, 'cabin_row');

        //get this merchant supervisor lists exclude this launch supervisors
        $excludes = [];
        if ($vehicle->supervisors) {
            foreach ($vehicle->supervisors as $supervisor) {
                array_push($excludes, $supervisor['supervisor_id']);
            }
        }
        $supervisorQuery = User::whereHas('roles', function ($q) {
            $q->where('name', 'supervisor');
        })->where('type', $user->type);
        if ($user->type == 'merchant') {
            $merchant_id = ($user->merchant_id) ? $user->merchant_id : $user->id;
            $supervisorQuery->where('merchant_id', $merchant_id);
        }
        if ($excludes) {
            $supervisorQuery->whereNotIn('id', $excludes);
        }
        $supervisors = $supervisorQuery->get();

        $title = $vehicle->name;
        $title .= ($vehicle->route) ? ' [' . $vehicle->route['route_name'] . ']' : '';
        return view('admin.launch.show', compact('vehicle', 'ghats','routes', 'schedules', 'deckFares', 'cabin_types', 'supervisors'))->withTitle($title);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $routes = VehicleRoute::get();
        return view('admin.launch.edit', compact('vehicle', 'routes', 'id'))->withTitle('Update ' . $vehicle->vehicle_type);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(VehicleUpdateRequest $request, $id)
    {
        try {
            DB::transaction(function () use ($request, $id) {
                if($request->hasFile('photo')) {
                    $request->merge([
                        'photo' => $this->upload->upload($request->photo)
                    ]);
                }
                $this->vehicleService->update($request->validated(), $id);
            }, 2);
            return redirect()->route('dashboard.vehicle.show', ['id' => $id, 'tab' => 'info']);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage() . ' on line-' . $e->getFile() . $e->getLine());
        }

        return redirect()->back();
    }

    public function action(Request $request)
    {
        $customer_id = $request->id;
        if (isset($request->action)) {
            call_user_func(array($this, $request->action), $request);
        }
    }

    private function restore($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Launch cannot restore'];
        $vehicle = Vehicle::withTrashed()->find($request->id);;

        if ($vehicle) {
            if ($vehicle->restore()) {
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = 'Launch successfully restored';
            }
        } else {
            $data['content'] = 'Launch not found';
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function active($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Launch cannot activate'];
        $vehicle = Vehicle::findOrFail($request->id);
        $vehicle->status = 1;
        if ($vehicle->save()) {
            DB::table('vehicle_schedules')->where('schedule_date', '>=', date('Y-m-d'))->where('status', 'PAUSE')->update(['status' => 'ACTIVE']);
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Launch has been successfully activated';
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function reactive($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Launch cannot activate'];
        $vehicle = Vehicle::findOrFail($request->id);
        $vehicle->status = 1;
        $vehicle->enabled_by = Auth::user()->id;
        if ($vehicle->save()) {
            DB::table('vehicle_schedules')->where('schedule_date', '>=', date('Y-m-d'))->where('status', 'PAUSE')->update(['status' => 'ACTIVE']);
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Launch has been successfully activated';
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function inactive($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Vehicle cannot inactivate'];
        $vehicle = Vehicle::findOrFail($request->id);
        if ($vehicle->update(['status' => AppConst::VEHICLE_INACTIVE, 'disabled_by' => auth()->user()->id])) {
            event(new VehicleInactiveEvent($vehicle));
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Launch has been successfully activated';
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function delete($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Vehicle cannot delete'];
        $vehicle = Vehicle::findOrFail($request->id);
        if ($vehicle->delete()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'The launch has been successfully deleted';
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Remove the specified resource .
     *
     * @query  string  $term
     * @return Response
     */
    public function suggest()
    {
        $query = Vehicle::with(['merchant']);

        $query->whereHas('merchant', function ($q) {
            $q->where('status', 1);
        });

        if (isset($_GET['term'])) {
            $term = $_GET['term'];
            $query->where('name', 'LIKE', '%' . $term . '%');
        }

        if (isset($_GET['merchant_id']) && $_GET['merchant_id'] != '') {
            $merchant = (int)$_GET['merchant_id'];
            $query->where('merchant_id', $merchant);
        }

        if (isset($_GET['service_type']) && $_GET['service_type'] != '') {
            $service_type = $_GET['service_type'];
            $query->where('vehicle_type', $service_type);
        }

        $query = $query->paginate(15);

        $results = [];

        if ($query) {
            foreach ($query as $q) {
                $row['id'] = $q->id;
                $row['name'] = $q->name;

                array_push($results, $row);
            }
        }

        return response()->json(['results' => $results], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function routes($id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function schedules(Request $request, $id)
    {
        if ($request->ajax() === True) {
            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ];

            return response()->json($data);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function bookings(Request $request, int $id): JsonResponse
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = BookingItem::with(['mapping.cabinType', 'trip', 'booking.payment'])->where('vehicle_id', $id);
            if ($request->get('date_from')) {
                $date = \DateTime::createFromFormat('d/m/Y', $request->date_from);
                $query->where('trip_date', '>=', $date->format('Y-m-d'));
            }

            if ($request->get('date_to')) {
                $date = \DateTime::createFromFormat('d/m/Y', $request->date_to);
                $query->where('trip_date', '<=', $date->format('Y-m-d'));
            }

            if ($request->get('route_id')) {
                $route_id = (int)$request->route_id;
                $query->whereHas('trip', function ($q) use ($route_id) {
                    $q->where('route_id', $route_id);
                });
            }

            if ($request->get('type')) {
                $type = (string)$request->type;
                $query->where('booking_type', $type);
            }

            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $items = $query->get();

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $items->toArray()
            ];

            return response()->json($data, $this->success);
        }
    }

    public function deckFares(Request $request, $id)
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = DeckFare::with(['route', 'departureFrom.ghat', 'departureTo.ghat'])->where('vehicle_id', $id);
            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $items = $query->get();

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $items->toArray()
            ];

            return response()->json($data, $this->success);
        }
    }

    public function scheduleChart(Request $request, $id)
    {
        // if( $request->ajax() == true ) {
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t');
        if ($request->date_from) {
            $date_from = \DateTime::createFromFormat('d/m/Y', $request->date_from)->format('Y-m-d');
        }

        if ($request->date_to) {
            $date_to = \DateTime::createFromFormat('d/m/Y', $request->date_to)->format('Y-m-d');
        }

        $query = VehicleSchedule::with(['mappings', 'cabinMappings', 'seatMappings', 'bookingItems.item.cabinType'])->where('vehicle_id', $id)->where('schedule_date', '>=', $date_from)->where('schedule_date', '<=', $date_to);

        if ($request->route_id) {
            $query->where('route_id', $request->route_id);
        }

        $type = 'All';

        if ($request->type) {
            $type = (string)ucfirst($request->type);
            // $query->whereHas('mappings', function($q) use ($type) {
            //     $q->where('ownership', strtolower($type));
            // });
            $query->with([
                'bookingItems' => function ($q) use ($request) {
                    $q->where('booking_party', $request->type);
                },
                'cabinMappings' => function ($q) use ($request) {
                    $q->where('ownership', $request->type);
                },
                'seatMappings' => function ($q) use ($request) {
                    $q->where('ownership', $request->type);
                }
            ]);
        }

        $total = $query->count();
        $schedules = $query->get();

        $returnArr = [];
        $finalArr = [];
        if ($schedules) {
            foreach ($schedules as $schedule) {
                $returnArr[$schedule->route['route_name']][] = $schedule;
            }
        }

        $series = [
            'cabin' => [],
            'seat' => [],
            'deck' => []
        ];
        $categories = [];

        if ($returnArr) {
            foreach ($returnArr as $key => $schdls) {
                $cat['name'] = $key;
                $cat['categories'] = [];
                if ($schdls) {
                    foreach ($schdls as $schedule) {
                        foreach ($schedule['bookingItems'] as $item) {
                            array_push($cat['categories'], date('d-F', strtotime($item['trip_date'])));
                            $passenger = json_decode($item['passenger']);
                            if (array_key_exists($item['trip_date'], $series['cabin'])) {
                                $series['cabin'][$item['trip_date']] += ($item['booking_type'] == 'cabin') ? 1 : 0;
                            } else {
                                $series['cabin'][$item['trip_date']] = ($item['booking_type'] == 'cabin') ? 1 : 0;;
                            }
                            if (array_key_exists($item['trip_date'], $series['seat'])) {
                                $series['seat'][$item['trip_date']] += ($item['booking_type'] == 'seat') ? 1 : 0;
                            } else {
                                $series['seat'][$item['trip_date']] = ($item['booking_type'] == 'seat') ? 1 : 0;;
                            }
                            if (array_key_exists($item['trip_date'], $series['deck'])) {
                                $series['deck'][$item['trip_date']] += ($item['booking_type'] == 'deck') ? (int)$passenger->person : 0;
                            } else {
                                $series['deck'][$item['trip_date']] = ($item['booking_type'] == 'deck') ? (int)$passenger->person : 0;;
                            }
                        }
                    }
                    $cat['categories'] = array_values(array_unique($cat['categories']));
                }
                array_push($categories, $cat);
            }
            $series['cabin'] = array_values($series['cabin']);
            $series['seat'] = array_values($series['seat']);
            $series['deck'] = array_values($series['deck']);
        }
        return response()->json(['status' => true, 'series' => $series, 'categories' => $categories]);
        // }
    }

    public function scheduleStatistics(Request $request, $id)
    {
        if ($request->ajax() == true) {
            $date_from = date('Y-m-01');
            $date_to = date('Y-m-t');
            if ($request->date_from) {
                $date_from = \DateTime::createFromFormat('d/m/Y', $request->date_from)->format('Y-m-d');
            }

            if ($request->date_to) {
                $date_to = \DateTime::createFromFormat('d/m/Y', $request->date_to)->format('Y-m-d');
            }

            $query = VehicleSchedule::with(['merchant', 'mappings', 'cabinMappings', 'seatMappings', 'bookingItems.item.cabinType', 'bookingItems.payment'])->where(['vehicle_id' => $id, 'status' => 'ACTIVE'])->whereBetween('schedule_date', [$date_from, $date_to]);

            if ($request->route_id) {
                $query->where('route_id', $request->route_id);
            }

            $type = 'All';

            if ($request->type) {
                $type = (string)ucfirst($request->type);
                // $query->whereHas('bookingItems', function($q) use ($type) {
                //     $q->where('booking_party', strtolower($type));
                // });
                $query->with([
                    'bookingItems' => function ($q) use ($request) {
                        $q->where('booking_party', $request->type);
                    },
                    'cabinMappings' => function ($q) use ($request) {
                        $q->where('ownership', $request->type);
                    },
                    'seatMappings' => function ($q) use ($request) {
                        $q->where('ownership', $request->type);
                    }
                ]);
            }

            $schedules = $query->get();

            //sanitize data
            $returnArr = array();
            $vat_visibility = true;
            if ($schedules) {
                foreach ($schedules as $schedule) {
                    $vat_visibility = ($schedule->merchant && $schedule->merchant['vat_visibility']) ? true : false;
                    $row['schedule_id'] = $schedule->id;
                    $row['trip_route'] = $schedule->startingPoint['ghat']['name'] . '-' . $schedule->endingPoint['ghat']['name'];
                    if ($schedule->schedule_type == 'reverse') {
                        $row['trip_route'] = $schedule->endingPoint['ghat']['name'] . '-' . $schedule->startingPoint['ghat']['name'];
                    }
                    $row['trip_date'] = date('d M, Y', strtotime($schedule->schedule_date));
                    $row['no_of_passengers'] = 0;
                    $row['total_ticket_sell_amount'] = 0;
                    $row['total_vat_amount'] = 0;
                    $row['total_waiver'] = 0;
                    $row['total_service_charge'] = 0;
                    $row['total_bank_charge'] = 0;
                    $row['no_of_discount_applied'] = 0;
                    $row['discount_amount'] = 0;
                    $row['coupon_amount'] = 0;
                    $row['no_of_coupon_applied'] = 0;
                    $row['no_of_ticket_sell'] = 0;
                    $row['cabins_total'] = 0;
                    $row['cabins_booking'] = 0;
                    $row['cabin_sell_amount'] = 0;
                    $row['cabin_sell_vat'] = 0;
                    $row['cabin_sell_vat_customer'] = 0;
                    $row['cabin_sell_vat_merchant'] = 0;
                    $row['cabin_sell_vat_vendor'] = 0;
                    $row['seats_total'] = 0;
                    $row['seats_booking'] = 0;
                    $row['seat_sell_amount'] = 0;
                    $row['seat_sell_vat'] = 0;
                    $row['seat_sell_vat_customer'] = 0;
                    $row['seat_sell_vat_merchant'] = 0;
                    $row['seat_sell_vat_vendor'] = 0;
                    $row['decks_total'] = 0;
                    $row['decks_booking'] = 0;
                    $row['deck_sell_amount'] = 0;
                    $row['deck_sell_vat'] = 0;
                    $row['deck_sell_vat_customer'] = 0;
                    $row['deck_sell_vat_merchant'] = 0;
                    $row['deck_sell_vat_vendor'] = 0;
                    $row['cabins_total'] += $schedule->cabinMappings->count();
                    $row['seats_total'] += $schedule->seatMappings->count();
                    $row['decks_total'] += $schedule->launch['passengers_capacity'];
                    if ($schedule['bookingItems']) {
                        $discounts = [];
                        $coupons = [];
                        $payments = [];
                        foreach ($schedule->bookingItems as $item) {
                            $payments[$item['booking_id']] = $item['payment'];
                            if ($item['discount_type'] == 'coupon') {
                                if (abs($item['discount']) > 0) {
                                    array_push($coupons, $item['booking_id']);
                                }
                            } else {
                                if (abs($item['discount']) > 0) {
                                    array_push($discounts, $item['booking_id']);
                                }
                            }
                            $passenger = json_decode($item['passenger']);
                            $vat = abs($item['price'] * ($item['vat_amount'] / 100));
                            $customer_vat = 0;
                            if ($item['vat_applicable_to'] == 'customer') {
                                $customer_vat = $vat;
                                $row[$item['booking_type'] . '_sell_vat_customer'] += $vat;
                            } elseif ($item['vat_applicable_to'] == 'merchant') {
                                $row[$item['booking_type'] . '_sell_vat_merchant'] += $vat;
                            } else {
                                $row[$item['booking_type'] . '_sell_vat_vendor'] += $vat;
                            }
                            $charge = abs($item['price'] * ($item['charge_amount'] / 100));
                            $discount = $item['discount'];
                            if ($item['booking_type'] == 'deck') {
                                $row['decks_booking'] += round($passenger->person);
                                $row['deck_sell_amount'] += abs($item['price'] + $customer_vat + $charge - $discount);
                                $row['deck_sell_vat'] += $vat;
                                $row['no_of_ticket_sell'] += abs($passenger->person);
                            } elseif ($item['booking_type'] == 'cabin') {
                                $row['cabins_booking'] += 1; //$item['item']['cabinType']['capacity']
                                $row['cabin_sell_amount'] += abs($item['price'] + $customer_vat + $charge - $discount);
                                $row['cabin_sell_vat'] += $vat;
                                $row['no_of_ticket_sell'] += 1;
                            } else {
                                $row['seats_booking'] += 1;
                                $row['seat_sell_amount'] += abs($item['price'] + $customer_vat + $charge - $discount);
                                $row['seat_sell_vat'] += $vat;
                                $row['no_of_ticket_sell'] += 1;
                            }

                            $row['total_ticket_sell_amount'] += abs($item['price'] + $customer_vat + $charge - $discount);
                            $row['no_of_passengers'] += $passenger->person;
                            $row['total_vat_amount'] += $vat;
                            if ($item['discount_type'] == 'coupon') {
                                $row['coupon_amount'] += abs($item['discount']);
                            } else {
                                $row['discount_amount'] += abs($item['discount']);
                            }
                            $row['total_waiver'] += abs($item['discount']);
                            $row['total_service_charge'] += abs($charge);
                        }
                        if ($payments) {
                            foreach ($payments as $key => $payment) {
                                $row['total_bank_charge'] += abs($payment['paid_amount'] - $payment['store_amount']);
                            }
                        }
                        $row['no_of_discount_applied'] += count(array_values(array_unique($discounts)));
                        $row['no_of_coupon_applied'] += count(array_values(array_unique($coupons)));
                    }
                    array_push($returnArr, $row);
                }
            }
            $title = $type . ' (' . date('d/m/Y', strtotime($date_from)) . ' to ' . date('d/m/Y', strtotime($date_to)) . ')';
            $str = '<div class="row">
                <div class="col-9"><h2>Account: ' . $title . '</h2></div>
                <div class="col-md-3 text-right">
                      <button type="button" class="btn btn-primary" onclick="printJS(\'launchStat\', \'html\')"><i class="fa fa-print"></i> Print</button>
                    <button type="button" class="btn btn-success" onclick="tableToExcel(\'launchStatistics\', \'launch-statistics\', \'statistics.xls\')"><i class="fa fa-file-excel"></i> Excel</button>
                </div>
                </div>';
            $str .= '<table class="table table-striped table-bordered" id="launchStat" style="width:100%;">
                        <thead>
                          <tr>
                            <th></th>
                            <th>Trip Route</th>
                            <th>Trip Date</th>
                            <th>No of Passengers</th>
                            <th>Total Ticket sell amount</th>';
            if ($vat_visibility) {
                $str .= '<th>Total Vat amount</th>';
            }
            $str .= '<th>Waiver</th>
                            <th>No of discount applied</th>
                            <th>Discount amount</th>
                            <th>No of coupon applied</th>
                            <th>Coupon amount</th>
                            <th>Service charge</th>
                            <th>Bank Charge</th>
                          </tr>
                        </thead>
                        <tbody>';
            if ($returnArr) {
                foreach ($returnArr as $item) {
                    $str .= '
                            <tr>
                                <td>
                                  <span class="toggleRow" onclick="toggleRow(this)" data-id="' . $item['schedule_id'] . '"><i class="fa fa-plus"></i></span>
                                </td>
                                <td>' . $item['trip_route'] . '</td>
                                <td>' . $item['trip_date'] . '</td>
                                <td>' . $item['no_of_passengers'] . '</td>
                                <td>' . number_format($item['total_ticket_sell_amount'], 2) . '</td>';
                    if ($vat_visibility) {
                        $str .= '<td>' . number_format($item['total_vat_amount'], 2) . '</td>';
                    }
                    $str .= '<td>' . number_format($item['total_waiver'], 2) . '</td>
                                <td>' . $item['no_of_discount_applied'] . '</td>
                                <td>' . number_format($item['discount_amount'], 2) . '</td>
                                <td>' . $item['no_of_coupon_applied'] . '</td>
                                <td>' . number_format($item['coupon_amount'], 2) . '</td>
                                <td>' . number_format($item['total_service_charge'], 2) . '</td>
                                <td>' . number_format($item['total_bank_charge'], 2) . '</td>
                            </tr>
                            <tr class="collapse-' . $item['schedule_id'] . ' d-none">
                                <td rowspan="2"></td>
                                <td>No of Ticket sell</td>
                                <td>Cabin (b/c)</td>
                                <td>Sell amount</td>';
                    if ($vat_visibility) {
                        $str .= '<td>Vat (M/V/C)</td>';
                    }
                    $str .= '<td>Seat (b/c)</td>
                                <td>Sell amount</td>';
                    if ($vat_visibility) {
                        $str .= '<td>Vat (M/V/C)</td>';
                    }
                    $str .= '<td>Deck (b/c)</td>
                                <td>Sell amount</td>';
                    if ($vat_visibility) {
                        $str .= '<td>Vat (M/V/C)</td>';
                    } else {
                        $str .= '<td></td>
                                        <td></td>';
                    }
                    $str .= '<td></td>
                                <td></td>
                            </tr>
                            <tr class="collapse-' . $item['schedule_id'] . ' d-none">
                                <td>' . $item['no_of_ticket_sell'] . '</td>
                                <td>' . $item['cabins_booking'] . '/' . $item['cabins_total'] . '</td>
                                <td>' . number_format($item['cabin_sell_amount'], 2) . '</td>';
                    if ($vat_visibility) {
                        $str .= '<td>' . number_format($item['cabin_sell_vat_merchant'], 2) . '/' . number_format($item['cabin_sell_vat_vendor'], 2) . '/' . number_format($item['cabin_sell_vat_customer'], 2) . '</td>';
                    }
                    $str .= '<td>' . $item['seats_booking'] . '/' . $item['seats_total'] . '</td>
                                <td>' . number_format($item['seat_sell_amount'], 2) . '</td>';
                    if ($vat_visibility) {
                        $str .= '<td>' . number_format($item['seat_sell_vat_merchant'], 2) . '/' . number_format($item['seat_sell_vat_vendor'], 2) . '/' . number_format($item['seat_sell_vat_customer'], 2) . '</td>';
                    }
                    $str .= '<td>' . $item['decks_booking'] . '/' . $item['decks_total'] . '</td>
                                <td>' . number_format($item['deck_sell_amount'], 2) . '</td>';
                    if ($vat_visibility) {
                        $str .= '<td>' . number_format($item['deck_sell_vat_merchant'], 2) . '/' . number_format($item['deck_sell_vat_vendor'], 2) . '/' . number_format($item['deck_sell_vat_customer'], 2) . '</td>';
                    } else {
                        $str .= '<td></td><td></td>';
                    }
                    $str .= '<td></td>
                                <td></td>
                            </tr>';
                }
            } else {
                if ($vat_visibility) {
                    $str .= "<tr><td colspan='13'>No data found</td></tr>";
                } else {
                    $str .= "<tr><td colspan='12'>No data found</td></tr>";
                }
            }
            $str .= '</tbody></table>';

            header('Content-Type: text/plain');
            echo $str;
        }
    }

    public function officerStatistics(Request $request, $id)
    {
        if ($request->ajax() == true) {
            $date_from = date('Y-m-01');
            $date_to = date('Y-m-t');
            if ($request->date_from) {
                $date_from = \DateTime::createFromFormat('d/m/Y', $request->date_from)->format('Y-m-d');
            }
            if ($request->date_to) {
                $date_to = \DateTime::createFromFormat('d/m/Y', $request->date_to)->format('Y-m-d');
            }
            $route_id = (request()->route_id) ? request()->route_id : '';
            $party = (request()->type) ? request()->type : '';

//            $reports = $this->reportService->launchReport(['vehicle_id' => $id, 'date_from' => $date_from, 'date_to' => $date_to, 'route_id' => $route_id, 'party' => $party]);
//            dd($reports);
            $reports = $this->vehicleService->getOfficersReport($id, $date_from, $date_to, $route_id, $party);
            $title = 'Booking Report : (' . date('d/m/Y', strtotime($date_from)) . ' to ' . date('d/m/Y', strtotime($date_to)) . ')';
            $str = '<div class="row">
                <div class="col-9"><h2>' . $title . '</h2></div>
                <div class="col-md-3 text-right">
                      <button type="button" class="btn btn-primary" onclick="printJS(\'launchOfficersStatistics\', \'html\')"><i class="fa fa-print"></i> Print</button>
                    <button type="button" class="btn btn-success" onclick="tableToExcel(\'launchOfficersStatistics\', \'launch-officers-statistics\', \'statistics.xls\')"><i class="fa fa-file-excel"></i> Excel</button>
                </div>
                </div>';
            $str .= '<table class="table table-striped table-bordered" id="officersStat" style="width:100%;">
                        <thead>
                          <tr>
                            <th rowspan="2">+/-</th>
                            <th rowspan="2">Officer group</th>
                            <th rowspan="2">Route</th>
                            <th colspan="2" class="text-center">Officer</th>
                            <th colspan="4" class="text-center">Sell item</th>
                            <th colspan="4" class="text-center">Sell Amount</th>
                            <th colspan="5" class="text-center">Received Amount</th>
                            <th rowspan="2">Refund</th>
                          </tr>
                          <tr>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Cabin</th>
                            <th>Seat</th>
                            <th>Deck</th>
                            <th>Total</th>
                            <th>Cabin</th>
                            <th>Seat</th>
                            <th>Deck</th>
                            <th>Total</th>
                            <th>Cash</th>
                            <th>Bkash</th>
                            <th>Rocket</th>
                            <th>Nagad</th>
                            <th>Total</th>
                          </tr>
                        </thead>
                        <tbody>';
            $groups = collect($reports['bookings'])->groupBy('role');
            foreach ($groups as $group => $routes) {
                $cabin = collect($routes)->where('booking_type', 'cabin');
                $seat = collect($routes)->where('booking_type', 'seat');
                $deck = collect($routes)->where('booking_type', 'deck');
                $totalCabin = $cabin->count();
                $totalSeat = $seat->count();
                $totalDeck = $deck->count();
                $totalCabinSell = $cabin->sum('total_amount');
                $totalSeatSell = $seat->sum('total_amount');
                $totalDeckSell = $deck->sum('total_amount');
                $collectionInfo = collect($reports['collections'])->where('role', $group);
                $refundInfo = collect($reports['refunds'])->where('role', $group);
                $str .= '<tr>
                    <td><span class="toggleRow" onclick="toggleRow(this)" data-id="' . $group . '"><i class="fa fa-plus"></i></span></td>
                    <td colspan="2">' . ucfirst($group) . '</td>
                    <td></td>
                    <td></td>
                    <td>' . $totalCabin . '</td>
                    <td>' . $totalSeat . '</td>
                    <td>' . $totalDeck . '</td>
                    <td>' . ($totalCabin + $totalSeat + $totalDeck) . '</td>
                    <td>' . $totalCabinSell . '</td>
                    <td>' . $totalSeatSell . '</td>
                    <td>' . $totalDeckSell . '</td>
                    <td>' . ($totalCabinSell + $totalSeatSell + $totalDeckSell) . '</td>
                    <td>' . $collectionInfo->flatten(1)->where('payment_type', 'cash')->sum('amount') . '</td>
                    <td>' . $collectionInfo->flatten(1)->where('payment_type', 'bkash')->sum('amount') . '</td>
                    <td>' . $collectionInfo->flatten(1)->where('payment_type', 'rocket')->sum('amount') . '</td>
                    <td>' . $collectionInfo->flatten(1)->where('payment_type', 'nagad')->sum('amount') . '</td>
                    <td>' . $collectionInfo->flatten(1)->sum('amount') . '</td>
                    <td>' . $refundInfo->flatten(1)->sum('refundable_amount') . '</td>
                </tr>';
                foreach (collect($routes)->groupBy('route') as $route => $bookings) {
                    $cabin = collect($bookings)->where('booking_type', 'cabin');
                    $seat = collect($bookings)->where('booking_type', 'seat');
                    $deck = collect($bookings)->where('booking_type', 'deck');
                    $totalCabin = $cabin->count();
                    $totalSeat = $seat->count();
                    $totalDeck = $deck->count();
                    $totalCabinSell = $cabin->sum('total_amount');
                    $totalSeatSell = $seat->sum('total_amount');
                    $totalDeckSell = $deck->sum('total_amount');
                    $collectionInfo = collect($reports['collections'])->where('role', $group)->where('route', $route);
                    $refundInfo = collect($reports['refunds'])->where('role', $group)->where('route', $route);
                    $str .= '<tr  class="collapse-' . $group . ' d-none">
                        <td></td>
                        <td><span class="toggleRow" onclick="toggleRow(this)" data-id="' . str_replace(' ', '', $route) . '-' . $group . '"><i class="fa fa-plus"></i></span></td>
                        <td>' . ucfirst($route) . ' (' . collect($bookings)->first()['trip_date'] . ')</td>
                        <td></td>
                        <td></td>
                        <td>' . $totalCabin . '</td>
                        <td>' . $totalSeat . '</td>
                        <td>' . $totalDeck . '</td>
                        <td>' . ($totalCabin + $totalSeat + $totalDeck) . '</td>
                        <td>' . $totalCabinSell . '</td>
                        <td>' . $totalSeatSell . '</td>
                        <td>' . $totalDeckSell . '</td>
                        <td>' . ($totalCabinSell + $totalSeatSell + $totalDeckSell) . '</td>
                        <td>' . $collectionInfo->flatten(1)->where('payment_type', 'cash')->sum('amount') . '</td>
                        <td>' . $collectionInfo->flatten(1)->where('payment_type', 'bkash')->sum('amount') . '</td>
                        <td>' . $collectionInfo->flatten(1)->where('payment_type', 'rocket')->sum('amount') . '</td>
                        <td>' . $collectionInfo->flatten(1)->where('payment_type', 'nagad')->sum('amount') . '</td>
                        <td>' . $collectionInfo->flatten(1)->sum('amount') . '</td>
                        <td>' . $refundInfo->flatten(1)->sum('refund_amount') . '</td>
                    </tr>';
                    $items = collect($bookings)->groupBy('officer');
                    foreach ($items as $operator => $lists) {
                        $cabin = collect($lists)->where('booking_type', 'cabin');
                        $seat = collect($lists)->where('booking_type', 'seat');
                        $deck = collect($lists)->where('booking_type', 'deck');
                        $totalCabin = $cabin->count();
                        $totalSeat = $seat->count();
                        $totalDeck = $deck->count();
                        $totalCabinSell = $cabin->sum('total_amount');
                        $totalSeatSell = $seat->sum('total_amount');
                        $totalDeckSell = $deck->sum('total_amount');
                        $collectionInfo = collect($reports['collections'])->where('role', $group)->where('route', $route)->where('officer', $operator);
                        $refundInfo = collect($reports['refunds'])->where('role', $group)->where('route', $route)->where('officer', $operator);
                        $str .= '<tr class="collapse-' . str_replace(' ', '', $route) . '-' . $group . ' d-none">
                            <td colspan="3"></td>
                            <td>' . ucfirst($operator) . '</td>
                            <td>' . collect($lists)->first()['officer_mobile'] . '</td>
                            <td>' . $totalCabin . '</td>
                            <td>' . $totalSeat . '</td>
                            <td>' . $totalDeck . '</td>
                            <td>' . ($totalCabin + $totalSeat + $totalDeck) . '</td>
                            <td>' . $totalCabinSell . '</td>
                            <td>' . $totalSeatSell . '</td>
                            <td>' . $totalDeckSell . '</td>
                            <td>' . ($totalCabinSell + $totalSeatSell + $totalDeckSell) . '</td>
                            <td>' . $collectionInfo->flatten(1)->where('payment_type', 'cash')->sum('amount') . '</td>
                            <td>' . $collectionInfo->flatten(1)->where('payment_type', 'bkash')->sum('amount') . '</td>
                            <td>' . $collectionInfo->flatten(1)->where('payment_type', 'rocket')->sum('amount') . '</td>
                            <td>' . $collectionInfo->flatten(1)->where('payment_type', 'nagad')->sum('amount') . '</td>
                            <td>' . $collectionInfo->flatten(1)->sum('amount') . '</td>
                            <td>' . $refundInfo->flatten(1)->sum('refund_amount') . '</td>
                        </tr>';
                    }
                }
            }
            $cabin = collect($reports['bookings'])->where('booking_type', 'cabin');
            $seat = collect($reports['bookings'])->where('booking_type', 'seat');
            $deck = collect($reports['bookings'])->where('booking_type', 'deck');
            $str .= '
                <tr>
                    <th colspan="5" class="text-right">Total</th>
                    <th>' . $cabin->count() . '</th>
                    <th>' . $seat->count() . '</th>
                    <th>' . $deck->count() . '</th>
                    <th>' . collect($reports['bookings'])->count() . '</th>
                    <th>' . $cabin->sum('total_amount') . '</th>
                    <th>' . $seat->sum('total_amount') . '</th>
                    <th>' . $deck->sum('total_amount') . '</th>
                    <th>' . collect($reports['bookings'])->sum('total_amount') . '</th>
                    <th>' . collect($reports['collections'])->where('payment_type', 'cash')->sum('amount') . '</th>
                    <th>' . collect($reports['collections'])->where('payment_type', 'bkash')->sum('amount') . '</th>
                    <th>' . collect($reports['collections'])->where('payment_type', 'rocket')->sum('amount') . '</th>
                    <th>' . collect($reports['collections'])->where('payment_type', 'nagad')->sum('amount') . '</th>
                    <th>' . collect($reports['collections'])->sum('amount') . '</th>
                    <th>' . collect($reports['refunds'])->sum('refund_amount') . '</th>
                </tr>
            </table>';

            header('Content-Type: text/plain');
            echo $str;
        }
    }
}
