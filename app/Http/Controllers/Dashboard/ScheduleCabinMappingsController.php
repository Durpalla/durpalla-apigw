<?php

namespace App\Http\Controllers\Dashboard;

use App\Constants\AppConst;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Cabin;
use App\Models\CabinLock;
use App\Exports\ScheduleMappingBatchExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\MappingUpdateRequest;
use App\Imports\ScheduleMappingBatchImport;
use App\Repository\Interfaces\ScheduleCabinMappingRepositoryInterface;
use App\Models\VehicleSchedule;
use App\Models\ScheduleCabinMapping;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Exception;

class ScheduleCabinMappingsController extends Controller
{
    protected $success = 200;
    private $mapping;

    public function __construct(ScheduleCabinMappingRepositoryInterface $mapping)
    {
        $this->mapping = $mapping;
    }

    public function edit(ScheduleCabinMapping $mapping): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => true, 'data' => $mapping], $this->success);
    }

    public function update(MappingUpdateRequest $request, $id): \Illuminate\Http\JsonResponse
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot update mapping'];
        try {
            $this->mapping->update($request->validated(), $id);
            $data['status'] = true;
            $data['label'] = 'success';
            $data['content'] = "Cabin mapping updated successfully";
        } catch (Exception $exception) {
            $data['content'] = $exception->getMessage();
        }

        return response()->json($data, $this->success);
    }

    public function action( Request $request )
    {
        $customer_id = $request->id;
        if( isset( $request->action ) ) {
            call_user_func(array($this, $request->action), $request);
        }
    }

    public function changeOwnership($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot release'];
        $mapping = ScheduleCabinMapping::findOrFail($request->id);
        $ghatID = $mapping->ghat_id;
        if($request->owner == AppConst::OWNER) {
            $ghatID = 0;
        }
        if( $mapping->update(['ownership' => $request->owner, 'ghat_id' => $ghatID])) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Ownership changed Successfully';
        }

        if( $request->ajax() === True ) {
            echo json_encode( $data );
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    public function unlock($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot unlock'];
        $mapping = ScheduleCabinMapping::findOrFail($request->id);
        if( $mapping->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING])) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Successfully unlocked';
        }

        if( $request->ajax() === True ) {
            echo json_encode( $data );
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    public function release($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot release'];
        $mapping = ScheduleCabinMapping::findOrFail($request->id);
        $mapping->is_reserved = 0;
        if( $mapping->save()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Successfully released';
        }

        if( $request->ajax() === True ) {
            echo json_encode( $data );
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    public function reserve($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot reserve'];
        $mapping = ScheduleCabinMapping::with(['books' => function($q) use($request) {
            $q->where('trip_id', $request->id);
        }])->findOrFail($request->id);

        if( !$mapping->books || !$mapping->books->count() ) {
            $mapping->is_reserved = 1;
            if ($mapping->save()) {
                $data['label'] = 'success';
                $data['status'] = true;
                $data['content'] = 'Successfully reserved';
            }
        } else {
            $data['content'] = 'This item already booked';
        }

        if( $request->ajax() === True ) {
            echo json_encode( $data );
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    public function transferQuota( Request $request, $id)
    {
        $user = Auth::user();
        $schedule = VehicleSchedule::with(['route.boardingPoints.ghat',
            'mappings' => function($q) use($user) {
                $owner = ($user->type == 'admin') ? AppConst::OWNER : $user->type;
                $q->where(['ownership' => $owner, 'booked' => 0, 'is_reserved' => 0]);
                if($user->counter_id) {
                    $q->where('ghat_id', $user->counter_id);
                }
            }, 'mappings.cabin.cabinType'])->findOrFail($id);
        return view('admin.launch.schedule.transfer', compact('schedule'))->withTitle('Quota transfer');
    }

    public function transferQuotaUpdate( Request $request, $id)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Route cannot be created.'];
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'bail|required|integer|exists:vehicle_schedules,id',
            'tab' => 'bail|required|string',
            'ids' => 'bail|required|array',
            'quota_owner' => 'bail|required|in:merchant,' . AppConst::OWNER,
            'quota_counter' => 'bail|nullable|numeric'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
            return redirect()->back()->with([
                'message' => $data
            ])->withErrors($validator)->withInput($request->all());
        }

        try {
            foreach($request->ids as $key => $id) {
                ScheduleCabinMapping::where(['schedule_id' => $request->schedule_id, 'id' => $id])->update([
                    'ownership' => $request->quota_owner,
                    'ghat_id' => ($request->quota_owner == 'merchant' && $request->quota_counter > 0) ? $request->quota_counter : 0
                ]);
            }
            $data['status'] = true;
            $data['label'] = 'success';
            $data['content'] = 'Your quota has been successfully transferred';
        } catch (\Exception $exception) {
            $data['content'] = $exception->getMessage();
        }

        return redirect()->route('dashboard.schedule.show', ['tab' => 'info', 'id' => $request->schedule_id])->with([
            'message' => $data
        ]);
    }

    public function batchUpdate(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Route cannot be created.'];
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'bail|required|integer|exists:vehicle_schedules,id',
            'tab' => 'bail|required|string',
            'attachment' => 'bail|required|max:50000|mimes:xlsx,xls,ods'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
            return redirect()->route('dashboard.schedule.show', ['tab' => 'batch-update', 'id' => $request->schedule_id])->with([
                'message' => $data
            ])->withErrors($validator)->withInput($request->all());
        }
        try{
            DB::transaction(function() use($request, &$data) {
                Excel::import(new ScheduleMappingBatchImport(), $request->attachment);
                $data['content'] = 'Batch update success';
                $data['status'] = true;
                $data['label'] = 'success';
            }, 2);
        } catch (\Exception $exception) {
            $data['content'] = $exception->getMessage();
        }
        return redirect()->route('dashboard.schedule.show', ['tab' => 'batch-update', 'id' => $request->schedule_id])->with([
            'message' => $data
        ]);
    }

    public function exportMapping($id)
    {
        $type = (isset($_GET['type'])) ? $_GET['type'] : 'cabin';
        return Excel::download(new ScheduleMappingBatchExport($id, $type), $type . '_' . $id .'.xlsx');
    }

    public function bookNow(Request $request, $id)
    {
        $data = ['success' => false, 'message' => 'Your item cannot be locked'];
        $user = Auth::user();
        $mapping = ScheduleCabinMapping::findOrFail($id);
        if($mapping->booked || !$mapping->is_reserved) {
            return redirect()->back()->with('error', 'This item is not reserved or already booked');
        }
        $schedule = VehicleSchedule::with(['merchant', 'startingPoint', 'endingPoint', 'boardingVias', 'discounts'])->findOrFail( $mapping->schedule_id );
        $item = Cabin::with(['launch.merchant', 'cabinType', 'mapping' => function($query) use($schedule) {
            $query->where('schedule_id', $schedule->id);
        }, 'books' => function($query) use($schedule) {
            $query->where('trip_id', $schedule->id)->whereIn('status', [0,1]);
        }, 'locks' => function($query) use($schedule) {
            $query->where('trip_id', $schedule->id);
        }])
            ->findOrFail( $mapping->cabin_id );

        try {
            if( $item->books && $item->books->count() > 0 ) {
                $data['message'] = 'Your ' . $item->type . ' is already booked';
            } elseif ( $item->locks && $item->locks->count() > 0 ) {
                $data['message'] = 'Your ' . $item->type . ' is already been locked';
            } else {
                $lockItem = CabinLock::create([
                    'cabin_id' => $item->id,
                    'customer_token' => Hash::make($user->email),
                    'trip_id' => ( int )$mapping->schedule_id
                ]);
                $data['success'] = true;
                $data['message'] = 'The item has been successfully locked';
                $vat_applicable_to = $schedule->launch['merchant']['vat_applicable_to'];
                $vat_amount = abs(getOption('vat_amount', 0));
                $vat = 0;
                if ($vat_applicable_to == 'customer') {
                    $vat = abs($item->fare * ($vat_amount / 100));
                }

                $service_charge_counter = abs(getOption('service_charge_counter'));
                $service_charge = 0;
                if ($user->type != 'merchant') {
                    $service_charge = abs($item->fare * ($service_charge_counter / 100));
                }
                $discounted = 0;

                if ($schedule->discounts) {
                    if ($user->type == 'admin') {
                        $userType = AppConst::OWNER;
                    } else {
                        $userType = 'merchant';
                    }
                    foreach ($schedule->discounts as $discount) {
                        $calculated = ($discount->type == 'p') ? ($item->fare * ($discount->amount / 100)) : $discount->amount;
                        if (($userType == $discount->applicable_to) || $discount->applicable_to == 'both') {
                            switch ($item->type) {
                                case 'cabin':
                                    $discounted += ($discount->is_cabin) ? $calculated : 0;
                                    break;
                                case 'seat':
                                    $discounted += ($discount->is_seat) ? $calculated : 0;
                                    break;
                                case 'deck':
                                    $discounted += ($discount->is_deck) ? $calculated : 0;
                                    break;
                            }
                        }
                    }
                }
                $cartItem = [
                    'lock_id' => $lockItem->id,
                    'type' => $item->type,
                    'trip_id' => $lockItem->trip_id,
                    'trip_date' => date('Y-m-d H:i:s', strtotime($schedule->schedule_date)),
                    'vehicle_id' => $item->vehicle_id,
                    'launch_name' => $item->launch['name'],
                    'route_name' => ($item->route) ? $item->route['route_name'] : '',
                    'cabin_no' => ($item['type'] == 'cabin') ? $item['cabinType']['letter'] . '-' . $item['cabin_no'] : $item['cabin_no'],
                    'cabin_id' => $item->id,
                    'fare' => abs($item->fare),
                    'total_vat' => abs($vat),
                    'total_charge' => abs($service_charge),
                    'discount' => $discounted,
                    'vat_amount' => $vat_amount,
                    'charge_amount' => $service_charge_counter,
                    'vat_applicable_to' => $vat_applicable_to,
                    'cabin_is_ac' => $item->cabinType['is_ac'],
                    'status' => 2,
                    'passenger' => ['name' => '', 'mobile' => '', 'person' => $item->passenger_capacity],
                    'stoppages' => [],
                    'boardingPoint' => null,
                    'is_honorium' => 0,
                    'honorium_charge' => 0,
                    'honorium_type' => $schedule->merchant['honorium_type'],
                    'incentive' => 0,
                    'incentive_type' => 'percent'
                ];

                if ($user->hasRole('supervisor')) {
                    $mapping = collect($user->supervisorMappings)->where('vehicle_id', $item->vehicle_id)->first();
                    $cartItem['incentive'] = $mapping->supervisor_incentive;
                    $cartItem['incentive_type'] = ($mapping->incentive_type == 'percent') ? 'percent' : 'fixed';
                }

                if ($user->type == 'merchant' && $item->mapping['honorium']) {
                    $cartItem['is_honorium'] = 1;
                    $cartItem['honorium_charge'] = $item->launch['merchant']['honorium_service_charge'];
                }

                //push stoppages
                if ($schedule->schedule_type == 'reverse') {
                    $cartItem['boardingPoint'] = ['id' => $schedule->endingPoint['ghat']['id'], 'name' => $schedule->endingPoint['ghat']['name']];
                    array_push($cartItem['stoppages'], ['id' => $schedule->endingPoint['ghat']['id'], 'name' => $schedule->endingPoint['ghat']['name']]);
                } else {
                    $cartItem['boardingPoint'] = ['id' => $schedule->startingPoint['ghat']['id'], 'name' => $schedule->startingPoint['ghat']['name']];
                    array_push($cartItem['stoppages'], ['id' => $schedule->startingPoint['ghat']['id'], 'name' => $schedule->startingPoint['ghat']['name']]);
                }

                if ($schedule->boardingVias) {
                    foreach ($schedule->boardingVias as $stoppage) {
                        array_push($cartItem['stoppages'], ['id' => $stoppage['id'], 'name' => $stoppage['name']]);
                    }
                }

                session()->put('user.carts', []);
                session()->push('user.carts', $cartItem);
                session()->save();
            }
        } catch (\Exception $exception) {
            $data['content'] = $exception->getMessage();
        }

        return redirect()->route('dashboard.other.quickbook', [
            'schedule_id' => $mapping->schedule_id,
            'route_id' => $schedule->route_id,
            'type' => $schedule->schedule_type,
            'trip_date' => date('d/m/Y', strtotime($schedule->schedule_date))
        ])->withMessage($data);
    }
}
