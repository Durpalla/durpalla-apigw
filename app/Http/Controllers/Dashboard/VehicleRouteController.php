<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Support\Facades\Cache;
use App\Models\Ghat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\VehicleRoute;
use App\Models\User;

class VehicleRouteController extends Controller
{
    protected $success = 200;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index( Request $request )
    {
        if( $request->ajax() === True ) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = VehicleRoute::with(['vehicles', 'boardingVias.ghat', 'startingPoint.ghat', 'endingPoint.ghat'])
                ->where('service_type', $_GET['service_type']);

            //filter by keyword
            // if( isset( $_GET['keyword'] ) && $_GET['keyword'] != null ){
            //     $keyword = $_GET['keyword'];
            //     $query->orWhere('merchantID', 'LIKE', "%$keyword%");
            //     $query->orWhere('primary_contact', 'LIKE', "%$keyword%");
            //     $query->orWhere('secondary_contact', 'LIKE', "%$keyword%");
            //     $query->orWhereHas('user', function ($q) use ($keyword) {
            //         $q->where('first_name', 'LIKE',"%$keyword%");
            //         $q->orWhere('last_name', 'LIKE',"%$keyword%");
            //         $q->orWhere('username', 'LIKE',"%$keyword%");
            //         $q->orWhere('email', 'LIKE',"%$keyword%");
            //         $q->orWhere('mobile', 'LIKE',"%$keyword%");
            //     });
            // }

            $total = $query->count();

            $query->offset($start);
            if( $limit < 0 ) {
                $limit = $total;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $routes = $query->get();

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $routes->toArray()
            ];

            return response()->json( $data );
        }
        return view('admin.routes.index')->withTitle('Launch Routes');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.routes.create')->withTitle('Add new Route');
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
            'route_name'=>'bail|required|string|max:191',
            'route_no' => 'bail|required|integer|unique:vehicle_routes,route_no',
            'route_type'=>'bail|nullable|max:191|string',
            'property_name' => 'bail|required|array',
            'property_type' => 'bail|required|array',
            'property_position' => 'bail|required|array',
            'service_type' => 'bail|required|exists:services,slug'
        ]);

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


        DB::beginTransaction();
        try{
            $route = new VehicleRoute;
            $route->route_name = $request->route_name;
            $route->route_no = $request->route_no;
            $route->route_type = $request->route_type;
            $route->created_by = Auth::user()->id;
            $route->service_type = $request->service_type;
            $route->save();

            if( $route->save() ) {
                if( $request->property_name && $request->property_type ) {
                    foreach( $request->property_name as $key => $value ) {
                        DB::table('route_properties')
                            ->insert([
                                'route_id' => $route->id,
                                'name' => $value,
                                'ghat_id' => $value,
                                'type' => $request->property_type[$key],
                                'user_id' => $route->created_by,
                                'serial_num' => $request->property_position[$key],
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                    }
                }
                $data['route'] = ['id' => $route->id, 'name' => $route->route_name];
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = "You have successfully create new route.";
                Cache::forget('route_dropdowns');
            }
            DB::commit();
        }  catch(\Exception $e) {
            DB::rollback();
//            Log::debug($e->getMessage());
            $data['content'] = $e->getMessage();
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.routes.index')->with([
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $route = VehicleRoute::with(['boardingVias', 'startingPoint', 'endingPoint'])->findOrFail( $id );
     /*   $route1 = $query->get();
        $route = $route1->toArray();*/
        /*dd($query->startingPoint);*/
         $returnArr = array();
        /*$row['start']=$route->startingPoint->name;
          array_push( $returnArr, $row );*/
        if( $route->boardingVias ) {
            $row['start']=$route->startingPoint->name;
                foreach( $route->boardingVias as $launch ) {
                    $row['start'] = $launch->name;
                    $row['end'] = $launch->name;
                    array_push( $returnArr, $row );
                }
            $row['end']=$route->endingPoint->name;
          array_push( $returnArr, $row );
            }
        else{
           $row['start']=$route->startingPoint->name;
           $row['end']=$route->endingPoint->name;
          array_push( $returnArr, $row );
      }
        /*dd($returnArr);*/
        return view('admin.routes.show', compact('returnArr'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $route = VehicleRoute::with(['boardingPoints.ghat'])->findOrFail( $id );
        // dd( $route->startingPoint );
        return view('admin.routes.edit', compact('route'))->withTitle('Edit: ' . $route->route_name);
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
            'route_name'=>'bail|required|string|max:191',
            'route_no' => 'bail|required|integer|unique:vehicle_routes,route_no,' . $id,
            'route_type'=>'bail|nullable|max:191|string',
            'property_name' => 'bail|required|array',
            'property_type' => 'bail|required|array',
            'property_position' => 'bail|required|array',
            'service_type' => 'bail|required|exists:services,slug'
        ]);
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

        DB::beginTransaction();
        try{
            $route = VehicleRoute::findOrFail( $id );
            $route->route_name = $request->route_name;
            $route->route_no = $request->route_no;
            $route->route_type = $request->route_type;
            $route->created_by = Auth::user()->id;
            $route->service_type = $request->service_type;
            $route->save();

            if( $route->save() ) {
                if( $request->property_name && $request->property_type ) {
                    //delete all authors information
                    DB::table('route_properties')
                        ->where('route_id', $route->id )
                        ->delete();

                    foreach( $request->property_name as $key => $name ) {
                        DB::table('route_properties')
                            ->insert([
                                'route_id' => $route->id,
                                'name' => $name,
                                'ghat_id' => $name,
                                'type' => $request->property_type[$key],
                                'serial_num' => $request->property_position[$key],
                                'user_id' => $route->created_by,
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                    }
                }
                Cache::forget('route_dropdowns');
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = "You have successfully updated route.";
            }

            DB::commit();
        }  catch(\Exception $e) {
            DB::rollback();
//            Log::debug($e->getMessage());
            $data['content'] = $e->getMessage();
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.routes.index')->with([
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
     * Remove the specified resource .
     *
     * @query  string  $term
     * @return \Illuminate\Http\Response
     */
    public function suggest()
    {
        $query = VehicleRoute::with(['startingPoint.ghat', 'endingPoint.ghat']);

        if( isset( $_GET['term'] ) ) {
            $term = $_GET['term'];
            $query->where('route_name', 'LIKE', '%' . $term . '%');
        }

        if( isset( $_GET['service_type'] ) ) {
            $term = (string) $_GET['service_type'];
            $query->where('service_type', $term);
        }

        $query = $query->paginate(15);

        $results = [];

        if( $query ) {
            foreach( $query as $q ) {
                $row['id'] = $q->id;
                $row['name'] = $q->route_name;

                array_push($results, $row);
            }
        }

        return response()->json(['results' => $results], 200);
    }

    public function properties( Request $request )
    {
        $route = VehicleRoute::findOrFail($request->route_id);

        $results = [];

        if( $route ) {
            array_push($results, ['id' => $route->startingPoint['id'], 'name' => $route->startingPoint['ghat']['name']]);
            foreach( $route->boardingVias as $q ) {
                array_push($results, ['id' => $q['id'], 'name' => $q['ghat']['name']]);
            }
            array_push($results, ['id' => $route->endingPoint['id'], 'name' => $route->endingPoint['ghat']['name']]);
        }

        return response()->json(['status' => true, 'items' => $results], $this->success);
    }

    public function naming( Request $request )
    {
        $data = ['status' => true, 'route_name' => ''];
        $starting = (int) $request->get('starting');
        $ending = (int) $request->get('ending');
        if( $starting ) {
            $startingPoint = Ghat::findOrFail($starting);
            $data['route_name'] .= $startingPoint->name;
        }

        if( $ending ) {
            $endingPoint = Ghat::findOrFail($ending);
            $data['route_name'] .= ' - ' . $endingPoint->name;
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

    private function active( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'User cannot activate'];
        $user = User::findOrFail($request->id);
        $user->status = 1;
        if( $user->save()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'User has been successfully activated';
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

    private function delete( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'User cannot delete'];
        $route = VehicleRoute::findOrFail($request->id);
        if( $route->delete()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'User has been successfully deleted';
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
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
