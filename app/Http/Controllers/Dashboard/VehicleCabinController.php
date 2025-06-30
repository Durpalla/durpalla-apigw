<?php

namespace App\Http\Controllers\Dashboard;

use App\Constants\AppConst;
use App\Models\Cabin;
use App\Models\CabinType;
use App\Http\Controllers\Controller;
use App\Imports\SeatCabinImport;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

class VehicleCabinController extends Controller
{
    protected $success = 200;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index( Request $request )
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Route cannot be created.'];
        $validator = Validator::make($request->all(), [
            'ownership' => 'bail|required|string',
            'cabin_no'=>'bail|required|alpha_dash',
            'vehicle_id' => 'bail|required|integer|exists:vehicles,id',
            'type_id'=>'bail|integer|exists:cabin_types,id',
            'floor' => 'bail|integer',
            'fare' => 'bail|required',
            'cabin_row' => 'bail|required',
            'passenger_capacity' => 'bail|integer',
            'ghat_id' => 'bail|nullable|numeric|exists:ghats,id'
        ]);

        $type = ( in_array( $request->tab, ['cabin', 'seat'] ) ) ? $request->tab : 'cabin';

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
            if( $request->ajax() === True ) {
                return response()->json($data, $this->success );
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        $launch = Vehicle::findOrFail( $request->vehicle_id );

        DB::beginTransaction();
        try{
            $type = ( in_array( $request->tab, ['cabin', 'seat'] ) ) ? $request->tab : 'cabin';
            $cabin = Cabin::firstOrNew([
                'vehicle_id' => $launch->id,
                'cabin_no' => $request->cabin_no,
                'type' => $type
            ]);
            $cabin->ownership = $request->ownership;
            $cabin->cabin_no = $request->cabin_no;
            $cabin->vehicle_id = $launch->id;
            $cabin->marchant_id = $launch->merchant_id;
            $cabin->type_id = $request->type_id;
            $cabin->fare = $request->fare;
            $cabin->cabin_row = (int) ( $request->cabin_row ) ? $request->cabin_row : 1;
            $cabin->floor = ( $request->floor ) ? $request->floor : 1;
            $cabin->cabin_position = (int) ( $request->cabin_position ) ? $request->cabin_position : 0;
            $cabin->passenger_capacity = (int) ( $request->passenger_capacity ) ? $request->passenger_capacity : 1;
            $cabin->created_by = Auth::user()->id;
            $cabin->type = $type;
            $cabin->is_reserved = ( $request->is_reserved ) ? 1 : 0;
            $cabin->ghat_id = ($request->ghat_id) ? $request->ghat_id : 0;
            $cabin->service_charge = $request->service_charge;
            switch ($request->ownership) {
                case 'other':
                    $cabin->status = 0;
                    break;
                case AppConst::OWNER:
                    $cabin->status = 1;
                    break;
                case 'merchant':
                    $cabin->status = 2;
                    break;

                default:
                    $cabin->status = 2;
                    break;
            }

            if( $cabin->save() ) {
                DB::commit();
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = 'Launch ' . $request->tab . ' has been added.';
            }

        }  catch(\Exception $e) {
            DB::rollback();
            $data['content'] = $e->getMessage();
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.vehicle.show', ['id' => $launch->id, 'tab' => $type])->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ]);
            }
        }
    }

    public function batchStore( Request  $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Route cannot be created.'];
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'bail|required|integer|exists:vehicles,id',
            'type' => 'bail|required|string',
            'attachment' => 'bail|required|max:50000|mimes:xlsx,xls,ods'
        ]);

        $type = ( in_array( $request->type, ['cabin', 'seat'] ) ) ? $request->type : 'cabin';

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
            return redirect()->route('dashboard.vehicle.show', ['tab' => 'cabin', 'id' => $request->vehicle_id, 'type' => 'batch'])->with([
                'message' => $data
            ])->withErrors($validator)->withInput();
        }
        try{
            DB::transaction(function() use($request, &$data, $type) {
                $launch = Vehicle::with(['cabins', 'seats'])->find( $request->vehicle_id);
                Excel::import(new SeatCabinImport($launch, $type), $request->attachment);
                $data['content'] = 'Import success';
                $data['status'] = true;
                $data['label'] = 'success';
            }, 2);
        } catch (\Exception $exception) {
            dd($exception);
            $data['content'] = $exception->getMessage();
        }
        return redirect()->route('dashboard.vehicle.show', ['tab' => $type, 'id' => $request->vehicle_id, 'type' => 'batch'])->with([
            'message' => $data
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $cabin = Cabin::findOrFail( $id );
        $cabin_types = CabinType::where('type', $cabin->type)->get();
        return view('admin.launch.cabin.edit', compact('cabin', 'cabin_types'))->withTitle('Edit ' . $cabin->type);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Route cannot be created.'];
        $validator = Validator::make($request->all(), [
            'cabin_no'=>'bail|required|alpha_dash',
            'vehicle_id' => 'bail|required|integer|exists:vehicles,id',
            'type_id'=>'bail|integer|exists:cabin_types,id',
            'floor' => 'bail|integer',
            'fare' => 'bail|required',
            'cabin_row' => 'bail|required',
            'passenger_capacity' => 'bail|integer',
            'ghat_id' => 'bail|nullable|numeric|exists:ghats,id'
        ]);

        $type = ( in_array( $request->tab, ['cabin', 'seat'] ) ) ? $request->tab : 'cabin';

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
            if( $request->ajax() === True ) {
                return response()->json($data, $this->success );
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        $launch = Vehicle::findOrFail( $request->vehicle_id );

        DB::beginTransaction();
        try{
            $cabin = Cabin::findOrFail( $id );
            $cabin->cabin_no = $request->cabin_no;
            $cabin->type_id = $request->type_id;
            $cabin->fare = $request->fare;
            $cabin->cabin_row = (int) ( $request->cabin_row ) ? $request->cabin_row : 1;
            $cabin->floor = ( $request->floor ) ? $request->floor : 1;
            $cabin->cabin_position = (int) ( $request->cabin_position ) ? $request->cabin_position : 0;
            $cabin->passenger_capacity = (int) ( $request->passenger_capacity ) ? $request->passenger_capacity : 1;
            $cabin->ownership = $request->ownership;
            $cabin->is_reserved = ( $request->is_reserved ) ? 1 : 0;
            $cabin->ghat_id = ( $request->ghat_id ) ? $request->ghat_id : 0;
            $cabin->service_charge = $request->service_charge;

            if( $cabin->save() ) {
                DB::commit();
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = 'Launch ' . $request->tab . ' has been updated.';
            }

        }  catch(Exception $e) {
            DB::rollback();
            $data['content'] = $e->getMessage();
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.vehicle.show', ['id' => $launch->id, 'tab' => $type])->with([
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
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
