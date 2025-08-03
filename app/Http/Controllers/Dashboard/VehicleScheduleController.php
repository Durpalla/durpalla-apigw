<?php

namespace App\Http\Controllers\Dashboard;

use App\Constants\AppConst;
use Illuminate\Http\JsonResponse;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingItem;
use App\Exports\ScheduleReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleScheduleCreateRequest;
use App\Models\VehicleSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ScheduleCabinMapping;
use App\Services\ReportService;
use Illuminate\Support\Facades\Validator;
use App\Services\TripService;
use Maatwebsite\Excel\Facades\Excel;

class VehicleScheduleController extends Controller
{
    protected $success = 200;
    protected $reportService;
    private $tripService;

    public function __construct(ReportService $reportService, TripService $tripService)
    {
        $this->reportService = $reportService;
        $this->tripService = $tripService;
    }

    public function index(Request $request)
    {
        if ($request->ajax() === True) {
            $user = Auth::user();
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = VehicleSchedule::query()
            ->with([
                'route',
                'launch.merchant',
                'boardingVias.ghat',
                'startingPoint.ghat',
                'endingPoint.ghat',
                'ticketBookings'])
                ->withCount([
                    'cabinBookings',
                    'seatBookings',
                    'cabinMappings',
                    'seatMappings',
                    'user',
                    'discounts' => function ($q) use ($user) {
                        $type = ($user->type == 'merchant') ? 'merchant' : 'jolzan';
                        $q->where('applicable_to', $type);
                        $q->orWhere('applicable_to', 'both');
                    }
                ]);

            //filter by keyword
            if (isset($_GET['keyword']) && $_GET['keyword'] !== null) {
                $keyword = $_GET['keyword'];
                $query->whereHas('launch', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE', "%$keyword%");
                });
            }

            if (request()->get('route') !== null) {
                $route = ( int )$_GET['route'];
                $query->where('route_id', $route);
            }

            if (request()->get('vehicle') !== null) {
                $vehicle = ( int )$_GET['vehicle'];
                $query->where('vehicle_id', $vehicle);
            }

            if (request()->get('merchant') !== null) {
                $merchant = ( int )$_GET['merchant'];
                $query->whereHas('launch', function ($q) use ($merchant) {
                    $q->whereHas('merchant', function ($q) use ($merchant) {
                        $q->where('merchant_id', $merchant);
                    });
                });
            }

            if ($user->type == 'merchant') {
                $merchant_id = ($user->merchant_id) ? $user->merchant_id : $user->id;
                $query->whereHas('launch', function ($q) use ($merchant_id) {
                    $q->whereHas('merchant', function ($q) use ($merchant_id) {
                        $q->where('merchant_id', $merchant_id);
                    });
                });
            }

            if(request()->get('service_type') !== null) {
                $service_type = $_GET['service_type'];
                $query->whereHas('launch', function($q) use ($service_type) {
                   $q->where('vehicle_type', $service_type);
                });
            }

            $date = (isset($_GET['date']) && $_GET['date'] != null) ? \DateTime::createFromFormat('d/m/Y', $_GET['date'])->format('Y-m-d') : null;
            $currentTime = date('Y-m-d H:i:s');
            $startTime = date('Y-m-d H:i:s', strtotime("-3 hour"));
            $status = $request->status;
            switch ($status) {
                case 'active' :
                    $query->where('operation_timeline', '>=', date('Y-m-d H:i:s'))
                        ->where('status', AppConst::SCHEDULE_ACTIVE);
                    if ($date) {
                        $query->where('schedule_date', $date);
                    }
                    break;
                case 'inactive' :
                    $query->where('status', '!=', AppConst::SCHEDULE_ACTIVE);
                    if ($date) {
                        $query->where('schedule_date', $date);
                    }
                    $query->orderByDesc('leaving_at');
                    break;
                case 'current' :
                    $query->where('leaving_at', '<=', $startTime)->where('operation_timeline', '>=', $currentTime)
                        ->where('status', AppConst::SCHEDULE_ACTIVE);
                    break;
                case 'past' :
                    $query->where('operation_timeline', '<=', $currentTime)
                        ->where('status', AppConst::SCHEDULE_ACTIVE);
                    if ($date) {
                        $query->where('schedule_date', $date);
                    }
                    $query->orderByDesc('leaving_at');
                    break;
                case 'pause':
                    $query->where('status', AppConst::SCHEDULE_PAUSED);
                    if ($date) {
                        $query->where('schedule_date', $date);
                    }
                    break;
            }

            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $query->orderBy('leaving_at', 'asc');
            $schedules = $query->get();

            //sanitize data
            $returnArr = array();
            if ($schedules) {
                foreach ($schedules as $schedule) {
                    $row['id'] = $schedule->id;
                    $row['vehicle_id'] = $schedule->vehicle_id;
                    $row['vehicle_name'] = $schedule->launch['name'];
                    $row['route_name'] = $schedule->route['route_name'];
                    if ($schedule->schedule_type == 'reverse') {
                        $row['route_name'] = $schedule->endingPoint['ghat']['name'] . ' - ' . $schedule->startingPoint['ghat']['name'];
                    }
                    $row['schedule_date'] = date('d M, Y', strtotime($schedule->schedule_date));
                    $row['leaving_at'] = date('h:i a', strtotime($schedule->leaving_at));
                    $row['schedule_type'] = ucfirst($schedule->schedule_type);
                    $row['starting_point'] = $schedule->startingPoint['ghat']['name'];
                    $row['ending_point'] = $schedule->endingPoint['ghat']['name'];
                    $row['total_cabin'] = $schedule->cabin_mappings_count;
                    $row['total_seat'] = $schedule->seat_mappings_count;
                    $row['total_deck'] = $schedule->launch['passengers_capacity'];
                    $row['cabin_booking'] = $schedule->cabin_bookings_count;
                    $row['seat_booking'] = $schedule->seat_bookings_count;
                    $row['deck_booking'] = 0;
                    if ($schedule->ticketBookings) {
                        foreach ($schedule->ticketBookings as $item) {
                            $passenger = json_decode($item['passenger']);
                            $row['deck_booking'] += abs($passenger->person);
                        }
                    }
                    $row['discounts'] = $schedule->discounts->count();
                    $row['discount_list'] = '';
                    if ($schedule->discounts) {
                        foreach ($schedule->discounts as $key => $discount) {
                            $row['discount_list'] .= ($key == 0) ? '' : ',';
                            $row['discount_list'] .= ' ' . $discount->applicable_to . '(' . $discount['amount'];
                            $row['discount_list'] .= ($discount['type'] == 'p') ? '%)' : 'Tk.)';
                        }
                    }
                    $row['created_at'] = date('d M, Y h:i a', strtotime($schedule->created_at));
                    $row['created_by'] = ($schedule->user) ? $schedule->user['name'] : '';
                    $row['status'] = $schedule->status;
                    $row['action_btn_visible'] = ($schedule->operation_timeline > date('Y-m-d H:i:s')) ? true : false;
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

        return view('admin.launch.schedule.index')->withTitle('Manage shcedule');
    }

    public function store(VehicleScheduleCreateRequest $request)
    {
        try {
            $this->tripService->create($request->validated());
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
        $previousUrl = app('url')->previous();

        if(! app('request')->has('tab'))
        {
            $previousUrl  = $previousUrl . '?' . http_build_query(['tab' => 'schedule']);
        }
        return redirect()->to($previousUrl);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        $schedule = VehicleSchedule::with(['launch.merchant', 'cabinMappings.cabin', 'seatMappings.cabin.cabinType', 'bookingItems', 'locks', 'cabinBookings', 'ticketBookings', 'startingPoint', 'endingPoint'])
            ->findOrFail($id);

        $title = $schedule->launch['name'] . ' (' . date('d/m/Y', strtotime($schedule->schedule_date)) . ')';
        // $title .= ( $schedule->type == 'reverse' ) ? ' [' . $schedule->endingPoint['name'] . ' - ' . $schedule->startingPoint['name'] . ']' :  ' [' . $schedule->startingPoint['name'] . ' - ' . $schedule->endingPoint['name'] . ']';
        // dd( $schedule );

        return view('admin.launch.schedule.show', compact('schedule'))->withTitle('Schedule of : ' . $title);
    }

    public function extendOperationHour(Request $request, VehicleSchedule $schedule)
    {
        $operation_hour = $schedule->operation_hour + $request->operation_hour;
        $schedule->update([
            'operation_hour' => $operation_hour,
            'operation_timeline' => date('Y-m-d H:i:s', (strtotime($schedule->leaving_at) + ($operation_hour * 60 * 60)))
        ]);
        return redirect()->route('dashboard.schedule.show', ['id' => $schedule->id, 'tab' => $request->tab]);
    }

    public function report(VehicleSchedule $schedule)
    {
        $reports = $this->reportService->tripReport(['trip_id' => $schedule->id, 'trip_date' => $schedule->schedule_date]);
        $title = $schedule->launch->name . ' [';
        $title .= ($schedule->schedule_type == 'reverse') ?
            $schedule->endingPoint['ghat']['name'] . ' - ' . $schedule->startingPoint['ghat']['name'] :
            $schedule->startingPoint['ghat']['name'] . ' - ' . $schedule->endingPoint['ghat']['name'];

        $title .= ' (' . date('d/m/Y', strtotime($schedule->schedule_date)) . ')]';
        return view('admin.launch.schedule.report', compact('reports', 'schedule'))->withTitle($title);
    }

    public function reportExport(VehicleSchedule $schedule)
    {
        $reports = $this->reportService->tripReport(['trip_id' => $schedule->id, 'trip_date' => $schedule->schedule_date]);
        $title = $schedule->launch->name . ' [';
        $title .= ($schedule->schedule_type == 'reverse') ?
            $schedule->endingPoint['ghat']['name'] . ' - ' . $schedule->startingPoint['ghat']['name'] :
            $schedule->startingPoint['ghat']['name'] . ' - ' . $schedule->endingPoint['ghat']['name'];

        $title .= ' (' . date('d-m-Y', strtotime($schedule->schedule_date)) . ')]';
        return Excel::download(new ScheduleReportExport($reports, $title), $title . '.xlsx');
    }

    public function cabins(Request $request, $schedule_id)
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');
            $type = (isset($_GET['type'])) ? $_GET['type'] : 'cabin';
            $query = ScheduleCabinMapping::with(['schedule', 'ghat', 'cabin.cabinType', 'books' => function ($query) use ($schedule_id) {
                $query->where('trip_id', $schedule_id);
            }])
                ->whereHas('cabin', function ($q) use ($request) {
                    //filter by keyword
                    $q->where('type', $request->type);
                    if ($request->get('floor') != null) {
                        $floor = (int)$request->get('floor');
                        $q->where('floor', $floor);
                    }
                    if ($request->get('cabin_type') != null) {
                        $cabin_type = (int)$request->get('cabin_type');
                        $q->where('type_id', $cabin_type);
                    }
                    if ($request->get('cabin_no') != null) {
                        $cabin_no = (int)$request->get('cabin_no');
                        $q->where('cabin_no', $cabin_no);
                    }
                });
            if ($request->get('owner') != null) {
                $query->where('ownership', $request->owner);
            }
            if ($request->get('is_reserved') != null) {
                $query->where('is_reserved', $request->is_reserved);
            }
            if ($request->get('is_booked') != null) {
                $query->where('booked', $request->is_booked);
            }
            $query->where('schedule_id', $schedule_id);

            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $cabins = $query->get();
            $edit_permission = auth()->user()->hasRole('admin') || auth()->user()->hasAnyPermission('schedule-mapping');
            //sanitize data
            $returnArr = array();
            if ($cabins) {
                foreach ($cabins as $cabin) {
                    $row['id'] = $cabin->id;
                    $row['cabin_id'] = $cabin->cabin_id;
                    $row['cabin_no'] = strtoupper(($cabin->cabinType) ? $cabin->cabinType['letter'] . '-' : '') . $cabin->cabin_no;
                    $row['fare'] = $cabin->fare;
                    $row['capacity'] = $cabin->passenger_capacity;
                    $row['type_id'] = $cabin->type_id;
                    $row['type_name'] = ($cabin->cabinType) ? $cabin->cabinType['name'] : '';
                    $row['is_ac'] = ($cabin->cabinType && $cabin->cabinType['is_ac']) ? 'AC' : 'None AC';
                    switch ($cabin->cabin['floor']) {
                        case '1':
                            $row['floor'] = '1st floor';
                            break;
                        case '2':
                            $row['floor'] = '2nd floor';
                            break;
                        case '3':
                            $row['floor'] = '3rd floor';
                            break;
                    }
                    $row['can_edit'] = $edit_permission && $cabin->schedule['schedule_date'] >= date('Y-m-d');
                    $row['position'] = $cabin->cabin_position;
                    $row['row'] = $cabin->cabin_row;
                    $row['ownership'] = ucfirst($cabin->ownership);
                    $row['counter'] = ($cabin->ghat != null) ? $cabin->ghat['name'] : 'N/A';
                    $row['booked'] = ($cabin->booked) ? 'Yes' : 'No';
                    $row['is_reserved'] = ($cabin->is_reserved) ? 'Y' : 'N';
                    $row['honorium'] = ($cabin->honorium) ? 'Y' : 'N';
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

    public function bookings(Request $request, $id)
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = BookingItem::with(['item', 'booking.payment', 'booking.customer'])->where('trip_id', $id);

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
                $query->where('trip_id', $route_id);
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

    public function honorium(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot process you request'];
        $validator = Validator::make($request->all(), [
            'ids' => 'bail|required'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
        } else {
            $ids = explode(',', $request->ids);
            $mappings = ScheduleCabinMapping::with(['cabin.cabinType'])->whereIn('id', $ids)->get();
            try {
                DB::transaction(function () use ($request, $mappings, &$data) {
                    foreach ($mappings as $cabin) {
                        $cabin->honorium = 1;
                        $cabin->save();
                    }
                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'You have successfully set honorium items';
                }, 5);
            } catch (\Exception $e) {
                $data['content'] = $e->getMessage();
            }
        }
        return response()->json($data, $this->success);
    }

    public function cancel($id, $launchId)
    {
        $schedule = VehicleSchedule::with(['launch.merchant', 'cabins', 'seats', 'bookingItems', 'cabinBookings', 'ticketBookings', 'startingPoint', 'endingPoint'])->findOrFail($id);
        $title = $schedule->launch['name'] . ' (' . date('d/m/Y', strtotime($schedule->schedule_date)) . ')';
        return view('admin.launch.schedule.cancle', compact('schedule'))->withTitle('Cancel schedule of : ' . $title);
    }

    public function reschedule($id, $launchId)
    {
        $schedule = VehicleSchedule::with(['launch.merchant', 'startingPoint', 'endingPoint'])->findOrFail($id);

        $title = $schedule->launch['name'] . ' (' . date('d/m/Y', strtotime($schedule->schedule_date)) . ')';

        return view('admin.launch.schedule.reschedule', compact('schedule'))->withTitle('Schedule : ' . $title);
    }

    public function rescheduleConfirm(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Reschedule not success'];
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'bail|required|integer|exists:vehicle_schedules,id',
            'schedule_date' => 'bail|required|string',
            'schedule_time' => 'bail|required|string'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput($request->all());
            }
        }

        try {
            DB::transaction(function () use ($request, &$data) {
                $schedule_date = \DateTime::createFromFormat('d/m/Y', $request->schedule_date);
                $schedule = VehicleSchedule::findOrFail($request->schedule_id);
                $schedule->schedule_date = $schedule_date->format('Y-m-d');
                $schedule->leaving_at = $schedule->schedule_date . ' ' . date("H:i:s", strtotime($request->schedule_time));
                if ($schedule->save()) {
                    DB::commit();
                    $data['vehicle_id'] = $schedule->vehicle_id;
                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Launch schedule has been rescheduled.';
                }
            }, 5);
        } catch (\Exception $e) {
            $data['content'] = $e->getMessage();
        }

        if ($data['status'] == true) {
            return redirect()->route('dashboard.vehicle.show', ['id' => $data['vehicle_id'], 'tab' => 'schedule'])->with([
                'message' => $data
            ]);
        } else {
            return redirect()->back()->with([
                'message' => $data
            ]);
        }
    }

    private function cancelBookings($rescheduleId, $type, $newScheduleId = null)
    {
        try {
            DB::transaction(function () use ($rescheduleId, $type, $newScheduleId) {
                $schedule = VehicleSchedule::findOrFail($rescheduleId);
                $schedule->status = $type;
                if (VehicleSchedule::RESCHEDULE) $schedule->vehicle_schedule_id = $newScheduleId;
                $schedule->save();

                $bookingItem = BookingItem::where('trip_id', $schedule->id)->first();
                if ($bookingItem) {
                    $booking = Booking::find($bookingItem->booking_id);

                    $allBookingItems = $booking->bookingItems;
                    $bookingItems = BookingItem::where('trip_id', $schedule->id)->get();

                    $ids = "";
                    foreach ($bookingItems as $index => $item) {
                        if ($index == sizeof($bookingItems) - 1)
                            $ids .= $item->id;
                        else
                            $ids .= $item->id . ",";
                    }

                    $bookingCancelation = new BookingCancellation();
                    $bookingCancelation->booking_id = $booking->id;
                    $bookingCancelation->customer_id = $booking->customer->id;
                    $bookingCancelation->type = (sizeof($allBookingItems) == sizeof($bookingItems)) ? "t" : "p";
                    $bookingCancelation->items = $ids;
                    $bookingCancelation->status = 1;
                    $bookingCancelation->save();
                }
            });
            return true;
        } catch (\Exception $exception) {
            \Log::error('Error while canceling schedule: ' . $exception->getMessage());
            dd($exception->getMessage());
            return false;
        }

        return false;
    }

    public function cancelConfirm(Request $request, $id)
    {
        $cancelSchedule = $this->cancelBookings($id, VehicleSchedule::CANCEL);

        if ($cancelSchedule) {
            return redirect()->route('dashboard.schedule.show', $id)->with([
                'message' => "Booking canceled"
            ]);
        } else {
            return redirect()->back()->with([
                'message' => "Failed"
            ]);
        }
    }

    public function action(Request $request)
    {
        $customer_id = $request->id;
        if (isset($request->action)) {
            call_user_func(array($this, $request->action), $request);
        }
    }

    private function active($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Launch schedule cannot activate'];
        $schedule = VehicleSchedule::findOrFail($request->id);
        if ($schedule->update(['status' => AppConst::SCHEDULE_ACTIVE])) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Launch schedule has been successfully activated';
        }

        if ($request->ajax() === True) {
            echo json_encode($data);
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function pause($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Launch schedule cannot activate'];
        $schedule = VehicleSchedule::findOrFail($request->id);
        if ($schedule->update(['status' => AppConst::SCHEDULE_PAUSED])) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Launch schedule has been successfully activated';
        }

        if ($request->ajax() === True) {
            echo json_encode($data);
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function delete($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Launch schedule cannot delete'];
        $schedule = VehicleSchedule::findOrFail($request->id);
        if ($schedule->delete()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Launch schedule has been successfully deleted';
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
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function pauseSchedules(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot pause schedules'];
        $validator = Validator::make($request->all(), [
            'ids' => 'required|string'
        ]);

        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
        } else {
            $ids = explode(',', $request->ids);

            if (is_array($ids)) {
                $schedules = VehicleSchedule::whereIn('id', $ids)->where('status', AppConst::SCHEDULE_ACTIVE)->get();

                if ($schedules->count() !== count($ids)) {
                    $data['content'] = 'Some schedules are missing.';
                } else {
                    try {
                        DB::transaction(function () use ($schedules, &$data) {
                            foreach ($schedules as $schedule) {
                                $schedule->update(['status' => AppConst::SCHEDULE_PAUSED]);
                            }
                            $data['status'] = true;
                            $data['label'] = 'success';
                            $data['content'] = 'Your selected schedules successfully paused';
                        }, 5);
                    } catch (\Exception $e) {
                        $data['content'] = 'Something went wrong';
                    }
                }
            }
        }

        return response()->json($data);
    }

    public function dropdown()
    {
        dd(request()->all());
    }

    public function suggestions(): JsonResponse
    {
        $data = [];
        if (request()->wantsJson()) {
            $data = $this->tripService->getSuggestions(request()->all());
        }
        return response()->json(['results' => $data]);
    }
}
