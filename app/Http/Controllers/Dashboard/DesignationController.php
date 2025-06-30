<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Designation;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DesignationController extends Controller
{
    protected $success = 200;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.designation.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.designation.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Designation cannot be created.'];
        $validator = Validator::make($request->all(), [
            'name'=>'bail|required|max:191|unique:designations,name'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
        }

        DB::beginTransaction();
        try{
            $designation = new Designation;
            $designation->name = $request->name;
            $designation->user_id = Auth::user()->id;

            if( $designation->save() ) {
                DB::commit();
                $data['item'] = $designation;
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = 'Designation  has been created.';
            }

        }  catch(\Exception $e) {
            DB::rollback();
//            Log::debug($e->getMessage());
            $data['content'] = $e->getMessage();
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.cabintype.index')->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput( $request->all());
            }
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $designation = Designation::findOrFail($id);
        return view('admin.designation.edit', compact('designation'))->withTitle('Update designation');
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
        $data = ['status' => false, 'label' => 'error', 'content' => 'Designation cannot be created.'];
        $validator = Validator::make($request->all(), [
            'name'=>'bail|required|max:191|unique:designations,name,' . $id
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
        }

        DB::beginTransaction();
        try{
            $designation = Designation::findOrFail($id);
            $designation->name = $request->name;

            if( $designation->save() ) {
                DB::commit();
                $data['item'] = $designation;
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = 'Designation has been updated.';
            }

        }  catch(\Exception $e) {
            DB::rollback();
//            Log::debug($e->getMessage());
            $data['content'] = $e->getMessage();
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.cabintype.index')->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput( $request->all());
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
        $data = ['status' => false, 'label' => 'error', 'content' => 'Could not delete designation'];
        if( Designation::delete($id) ) {
            $data['status'] = true;
            $data['label'] = 'success';
            $data['content'] = 'Designation has been successfully deleted';
        }

        return response()->json($data, $this->success);
    }
}
