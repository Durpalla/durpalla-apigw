<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\NidVisibilityExtendRequest;
use App\Models\UserMeta;
use App\Models\VehicleSupervisor;

class SupervisorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot assign launch supervisor'];
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'bail|required|integer|exists:vehicles,id',
            'supervisor_id' => 'bail|required|integer|exists:users,id',
            'master_id' => 'bail|nullable|integer|exists:users,id'
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

        DB::beginTransaction();
        try {
            //proecss request
            $supervisor = VehicleSupervisor::firstOrNew([
                'supervisor_id' => $request->supervisor_id
            ]);
            $supervisor->vehicle_id = $request->vehicle_id;
            $supervisor->is_master = ($request->master_id) ? 0 : 1;
            $supervisor->master_id = $request->master_id;
            if ($supervisor->save()) {
                DB::commit();
                $data['content'] = 'Launch supervisor assigned successfully';
                $data['label'] = 'success';
                $data['status'] = true;
            }
        } catch (\Exception $e) {
            DB::rollback();
            $data['content'] = $e->getMessage();
        }


        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        } else {
            if ($data['status'] == true) {
                return redirect()->back()->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ]);
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function assignToVehicle(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot assign launch supervisor'];
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'bail|required|integer|exists:vehicles,id',
            'supervisor_id' => 'bail|required|integer|exists:users,id',
            'master_id' => 'bail|nullable|integer|exists:users,id'
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
            DB::transaction(function() use($request) {
                VehicleSupervisor::create($request->all() + [
                    'user_id' => auth()->user()->id, 'is_master' => ($request->master_id) ? 0 : 1
                    ]);
            }, 2);
            $data['content'] = 'Launch supervisor assigned successfully';
            $data['label'] = 'success';
            $data['status'] = true;
        } catch (\Exception $e) {
            $data['content'] = $e->getMessage();
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        } else {
            if ($data['status'] == true) {
                return redirect()->back()->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ]);
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
        //
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
        //
    }

    public function extendNidVisibility(NidVisibilityExtendRequest $request): JsonResponse
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot update visible time'];
        try {
            UserMeta::updateOrCreate(['user_id' => $request->supervisor_id], ['nid_visible_until' => date('Y-m-d H:i:s', strtotime("+" . $request->extended_hours . " hour"))]);
            $data['status'] = true;
            $data['label'] = 'success';
            $data['content'] = 'Successfully extend the visible hours';
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }
        return response()->json($data);
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
}
