<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Discount;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\VehicleSchedule;

class DiscountController extends Controller
{
    protected $success = 200;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');
            $query = Discount::with(['launch', 'merchant', 'schedule.route', 'user', 'disableBy']);

            // if( Auth::user()->type !== 'admin' ) {
            //     $query->where('type', Auth::user()->type);
            // }

            $query->whereHas('schedule', function ($q) use ($request) {
                $q->where('schedule_date', '>=', date('Y-m-d'));
            });

            if (isset($_GET['status'])) {
                $status = (int)$_GET['status'];
                $query->where('status', $status);
            }

            $count = $query->count();
            $query->offset($start);
            if ($limit < 0) {
                $limit = $count;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $discounts = $query->get();

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $count,
                'recordsFiltered' => $count,
                'data' => $discounts
            ];

            return response()->json($data);
        }
        return view('admin.discount.index')->withTitle('Manage discount');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.discount.create')->withTitle('Add new discount rule');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Discount cannot be saved'];
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'bail|required|integer|exists:vehicle_schedules,id',
            'amount' => 'bail|required|numeric',
            'type' => 'bail|required|string',
            'applicable_to' => 'bail|required'
        ]);

        if ($validator->fails() == True) {
            $data['content'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try {
                $schedule = VehicleSchedule::findOrFail($request->schedule_id);
                Discount::create([
                    'applicable_to' => $request->applicable_to,
                    'merchant_id' => $schedule->merchant_id,
                    'vehicle_id' => $schedule->vehicle_id,
                    'schedule_id' => $schedule->id,
                    'description' => $request->description,
                    'amount' => $request->amount,
                    'type' => $request->type,
                    'is_cabin' => ($request->is_cabin) ? 1 : 0,
                    'is_seat' => ($request->is_seat) ? 1 : 0,
                    'is_deck' => ($request->is_deck) ? 1 : 0,
                    'user_id' => Auth::user()->id
                ]);

                DB::commit();
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = ' Discount has been saved.';

            } catch (\Exception $e) {
                DB::rollback();
                $data['content'] = $e->getMessage();
            }
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        } else {
            if (isset($request->tab)) {
                return redirect()->route('dashboard.schedule.show', ['id' => $request->schedule_id, 'tab' => $request->tab])->with([
                    'message' => $data
                ])->withErrors($validator)->withInput($request->all());
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput($request->all());
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $discount = Discount::with(['schedule', 'launch', 'merchant', 'user'])->findOrFail($id);
        return view('admin.discount.edit', compact('discount'))->withTitle('Update discount');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Discount cannot be updated'];
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'bail|required|integer|exists:vehicle_schedules,id',
            'amount' => 'bail|required|numeric',
            'type' => 'bail|required|string'
        ]);

        if ($validator->fails() == True) {
            $data['content'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try {
                $schedule = VehicleSchedule::findOrFail($request->schedule_id);
                $discount = Discount::findOrFail($id);
                $discount->update([
                    'applicable_to' => $request->applicable_to,
                    'merchant_id' => $schedule->merchant_id,
                    'vehicle_id' => $schedule->vehicle_id,
                    'schedule_id' => $schedule->id,
                    'description' => $request->description,
                    'amount' => $request->amount,
                    'type' => $request->type,
                    'is_cabin' => ($request->is_cabin) ? 1 : 0,
                    'is_seat' => ($request->is_seat) ? 1 : 0,
                    'is_deck' => ($request->is_deck) ? 1 : 0,
                ]);

                DB::commit();
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = ' Discount has been updated.';
            } catch (\Exception $e) {
                DB::rollback();
//                Log::debug($e->getMessage());
                $data['content'] = $e->getMessage();
            }
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        } else {
            return redirect()->back()->with([
                'message' => $data
            ])->withErrors($validator)->withInput();
        }
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

    public function suggest()
    {
        $query = VehicleSchedule::with('launch', 'route', 'startingPoint.ghat', 'endingPoint.ghat')
            ->where('schedule_date', '>=', date('Y-m-d'));

        if (isset($_GET['term'])) {
            $term = $_GET['term'];
            $query->whereHas('launch', function ($q) use ($term) {
                $q->where('name', 'LIKE', '%' . $term . '%');
            });
        }

        $query = $query->paginate(15);

        $results = [];

        if ($query) {
            foreach ($query as $q) {
                $row['id'] = $q->id;
                $row['name'] = $q->launch['name'] . ' (' . $q->route['route_name'] . ' - ' . $q->schedule_date . ')';

                array_push($results, $row);
            }
        }

        return response()->json(['results' => $results], 200);
    }

    public function action(Request $request)
    {
        if (isset($request->type) && in_array($request->type, ['enable', 'disable', 'bulkenable', 'bulkdisable'])) {
            return call_user_func(array($this, $request->type . 'Discount'), $request);
        } else {
            return response()->json(['status' => false, 'content' => 'Your action type not valid', 'label' => 'error']);
        }
    }

    public function bulkenableDiscount($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Action not taken'];
        $ids = explode(',', $request->ids);
        $discounts = Discount::whereIn('id', $ids)->get();

        try {
            DB::transaction(function () use ($discounts, &$data, $request) {
                $user = Auth::user();
                if ($discounts) {
                    foreach ($discounts as $discount) {
                        $discount->update(['enabled_by' => $user->id, 'status' => 1]);
                    }

                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Discounts are successfully enabled';
                }
            }, 2);
        } catch (\Exception $e) {
            $data['content'] = 'Error occured';
        }

        if ($request->ajax() === True) {
            return response()->json($data);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    public function bulkdisableDiscount($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Action not taken'];
        $ids = explode(',', $request->ids);
        $discounts = Discount::whereIn('id', $ids)->get();

        try {
            DB::transaction(function () use ($discounts, &$data, $request) {
                $user = Auth::user();
                if ($discounts) {
                    foreach ($discounts as $discount) {
                        $discount->update(['disabled_by' => $user->id, 'status' => 2]);
                    }

                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Discounts are successfully disabled';
                }
            }, 2);
        } catch (\Exception $e) {
            $data['content'] = 'Error occured';
        }

        if ($request->ajax() === True) {
            return response()->json($data);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function enableDiscount($request)
    {
        $discount = Discount::find($request->id);
        if ($discount->update(['enabled_by' => Auth::user()->id, 'status' => 1])) {
            return response()->json(['status' => true, 'content' => 'Discount has been enabled.', 'label' => 'success']);
        } else {
            return response()->json(['status' => false, 'content' => 'Discount has been enabled.', 'label' => 'error']);
        }
    }

    private function disableDiscount($request)
    {
        $discount = Discount::find($request->id);
        if ($discount->update(['disabled_by' => Auth::user()->id, 'status' => 2])) {
            return response()->json(['status' => true, 'content' => 'Discount has been enabled.', 'label' => 'success']);
        } else {
            return response()->json(['status' => false, 'content' => 'Discount has been enabled.', 'label' => 'error']);
        }
    }
}
