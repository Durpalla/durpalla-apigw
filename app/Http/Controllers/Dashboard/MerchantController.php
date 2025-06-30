<?php
namespace App\Http\Controllers\Dashboard;
set_time_limit(180);
ini_set('max_execution_time', 180);

use App\Constants\AppConst;
use App\Models\VehicleRoute;
use App\Models\VehicleSchedule;
use App\Models\Merchant;
use App\Models\Vehicle;
use App\Notifications\NewMerchantNotify;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class MerchantController extends Controller
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

            $query = Merchant::with(['user', 'vehicles']);

            //filter by keyword
            if( isset( $_GET['keyword'] ) && $_GET['keyword'] != null ){
                $keyword = $_GET['keyword'];
                $query->orWhereHas('user', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE',"%$keyword%");
                    $q->orWhere('mobile', 'LIKE',"%$keyword%");
                    $q->orWhere('email', 'LIKE',"%$keyword%");
                });
            }

            if( isset( $_GET['route'] ) && $_GET['route'] != null ){
                $route = ( int ) $_GET['route'];
                $query->orWhereHas('vehicles', function ($q) use ($route) {
                    $q->where('route_id', $route);
                });
            }

            if( isset( $_GET['date_from'] ) && $_GET['date_from'] != null ){
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_from']);
                $query->where('created_at', '>=', $date->format('Y-m-d H:i:s'));
            }

            if( isset( $_GET['date_to'] ) && $_GET['date_to'] != null ){
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_to']);
                $query->where('created_at', '<=', $date->format('Y-m-d H:i:s'));
            }

            if( isset( $_GET['status'] ) && $_GET['status'] != null ){
                $status = $_GET['status'];
                if( $status == 'inactive' ) {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', 1);
                }
            }

            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            $query->orderBy($column, $order);
            $merchants = $query->get();

            //sanitize data
            $returnArr = array();
            if ($merchants) {
                foreach ($merchants as $merchant) {
                    $row['id'] = $merchant->id;
                    $row['user_id'] = $merchant->user_id;
                    $row['vehicle_count'] = $merchant->vehicles->count();
                    $row['merchant_name'] = $merchant->merchant_name;
                    $row['merchant_reg_no'] = $merchant->merchant_reg_no;
                    $row['merchant_reg_expiry_date'] = date('d/m/Y', strtotime($merchant->merchant_reg_expiry_date));
                    $row['merchant_email'] = $merchant->merchant_email;
                    $row['merchant_mobile'] = $merchant->merchant_mobile;
                    $row['vat_applicable_to'] = $merchant->vat_applicable_to . ' (' . getOption('vat_amount', 0) . '%)';
                    $row['honorium_service_charge'] = $merchant->honorium_service_charge;
                    $row['honorium_service_charge'] .= ($merchant->honorium_type == 'fixed') ? ' Tk' : '%';
                    $row['phone'] = $merchant->merchant_phone;
                    $row['status'] = $merchant->status;
                    $row['created_at'] = date('d M, Y', strtotime($merchant->created_at));
                    $row['deleted_at'] = $merchant->deleted_at;
                    $row['photo'] = ($merchant->logo) ? asset('images/' . $merchant->logo) : asset('default/avatar.png');
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

        return view('admin.merchant.index')->withTitle('Merchants');
    }

    public function vehicles(Request $request, $id)
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = Vehicle::with(['merchant', 'route'])->withCount(['cabins', 'seats'])->where('merchant_id', $id);

            //filter by keyword
             if( isset( $_GET['search'] ) && $_GET['search'] != null ){
                 $keyword = $_GET['search']['value'];
                 if(is_numeric($keyword)) {
                     $query->Where('id', 'LIKE', "%$keyword%");
                     $query->orWhere('registration_no', 'LIKE', "%$keyword%");
                 } else {
                     $query->Where('name', 'LIKE', "%$keyword%");
                     $query->orWhere('vehicle_no', 'LIKE', "%$keyword%");
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
                    $row['route'] = $vehicle->route['route_name'];
                    $row['status'] = $vehicle->status;
                    $row['cabins'] = $vehicle->cabins_count;
                    $row['seats'] = $vehicle->seats_count;
                    $row['vehicle_type'] = $vehicle->vehicle_type;
                    $row['capacity'] = $vehicle->passengers_capacity;
                    $row['photo'] = ( $vehicle->photo ) ? asset('vehicles/' . $vehicle->photo) : asset('default/avatar.png');
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

        return view('admin.merchant.vehicles')->withTitle('Merchant vehicles');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return view('admin.merchant.create')->withTitle('Add new merchant');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => ''];
        $validator = Validator::make($request->all(), [
            'merchant_name' => 'bail|required|string|max:191|unique:merchants,merchant_name',
            'merchant_reg_no' => 'bail|required|string|unique:merchants,merchant_reg_no',
            'merchant_address' => 'bail|nullable|max:191|string',
            'merchant_email' => 'bail|required|email|max:191|unique:users,email|unique:merchants,merchant_email',
            // 'merchant_username' => 'bail|max:191|unique:users,username',
            'merchant_mobile' => 'bail|required|max:13|regex:/^(01){1}[3456789]{1}(\d){8}$/|unique:users,mobile|unique:merchants,merchant_mobile',
            'merchant_phone' => 'bail|nullable|string',
            'merchant_password' => 'bail|required|max:20|min:8|same:merchant_password_confirm',
            'merchant_password_confirm' => 'required',
            'logo' => 'bail|nullable|image|mimes:png,jpg,jpeg|max:150',
            'vat_applicable_to' => 'bail|required',
            'vat_visibility' => 'bail|required',
            'honorium_service_charge' => 'required|numeric',
            'honorium_type' => 'bail|required|string'
        ]);


        //validation fails
        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        $user = null;
        $merchant = null;

        try {
            DB::transaction(function() use(&$user, &$merchant, &$request) {
                //proecss request
                $user = new User;
                $user->name = $request->merchant_name;
                $user->email = $request->merchant_email;
                $user->mobile = $request->merchant_mobile;
                // $user->username = ($request->merchant_username) ? $request->merchant_username : $request->merchant_email;
                $user->password = Hash::make($request->merchant_password);
                $user->email_verified_at = date('Y-m-d H:i:s');
                $user->type = 'merchant';
                $user->save();

                //assign role
                $role = Role::where('name', 'merchant')->first();
                $user->assignRole($role);
                $merchant = new Merchant();
                $merchant->user_id = $user->id;
                $merchant->merchant_name = $request->merchant_name;
                $merchant->merchant_reg_no = $request->merchant_reg_no;

                if (!is_null($request->merchant_reg_expiry_date)) {
                    $regDate = \DateTime::createFromFormat('d/m/Y', $request->merchant_reg_expiry_date);
                    $merchant->merchant_reg_expiry_date = date('Y-m-d', strtotime($regDate->format('Y-m-d')));
                } else {
                    $merchant->merchant_reg_expiry_date = date('Y-m-d');
                }
                if ($request->logo) {
                    /*logo upload*/
                    $imageName = time() . '.' . $request->logo->extension();
                    $request->logo->move(public_path('images'), $imageName);
                    $merchant->logo = $imageName;
                }

                $merchant->merchant_email = $request->merchant_email;
                $merchant->merchant_mobile = $request->merchant_mobile;
                $merchant->merchant_phone = $request->merchant_phone;
                $merchant->merchant_fax = $request->merchant_fax;
                $merchant->merchant_address = $request->merchant_address;
                $merchant->created_by = Auth::user()->id;
                $merchant->status = 1;
                $merchant->vat_visibility = (int) $request->vat_visibility;
                $merchant->vat_applicable_to = (in_array($request->vat_applicable_to, ['customer', 'merchant', 'vendor'])) ? ( (!(int) $request->vat_visibility) ? 'merchant' : $request->vat_applicable_to) : 'customer';
                $merchant->honorium_service_charge = ( $request->honorium_service_charge ) ? abs($request->honorium_service_charge ) : 0;
                $merchant->honorium_type = ($request->honorium_type == 'fixed') ? 'fixed' : 'percent';
                $merchant->save();
            }, 5);

        } catch (\Exception $e) {
//             Log::debug();
            $data['content'] = $e->getMessage();
        }

        if( !is_null($user) && !is_null($merchant) ) {
            //notify email
            $user->notify( new NewMerchantNotify($merchant));

            //notify sms
            sendSMS([
                'mobile' => $user->mobile,
                'message' => 'Dear ' . $user->name . ', Welcome to ' . config('app.name') . '. Your Merchant account has been successfully created'

            ]);
            $merchant_id = $merchant->id;
            $data['content'] = 'Merchant account successfully created';
            $data['label'] = 'success';
            $data['status'] = true;
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        } else {
            if ($data['status'] == true) {
                return redirect()->route('dashboard.merchant.show', $merchant_id)->with([
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
        $merchant = Merchant::with(['vehicles.cabins', 'vehicles.seats'])->findOrFail($id);
        $routes = VehicleRoute::get();
        $schedules = array();
        foreach ($merchant->schedules as $schedule) {
            $row = array();
            $row['title'] = ( $schedule->vehicle ) ? $schedule->vehicle->name : '';
            $row['title'] .= ($schedule->route) ? " (".$schedule->route->startingPoint['ghat']['name'] . '-' . $schedule->route->endingPoint['ghat']['name'] . ") " : '';
            if( $schedule->schedule_type == 'reverse' ) {
                $row['title'] = ($schedule->vehicle ) ? $schedule->vehicle->name : '';
                $row['title'] .= ($schedule->route) ? " (".$schedule->route->endingPoint['ghat']['name'] . '-' . $schedule->route->startingPoint['ghat']['name'] . ") " : "";
            }
            $row['start'] = Carbon::parse($schedule->schedule_date)->format("Y-m-d")." ".Carbon::parse($schedule->leaving_at)->format("H:i:s");  //Fri Jun 12 2020 10:30:00 GMT+0600
            $row['allDay'] = false;
            $row['className'] = "success";
            $row['url'] = route('dashboard.other.quickbook', ['route_id' => $schedule->route_id, 'trip_date' => date('d/m/Y', strtotime( $schedule->schedule_date ) ), 'type' => $schedule->schedule_type, 'schedule_id' => $schedule->id]);
            $schedules[] = $row;
        }
      //  return  $schedules;

        return view('admin.merchant.show', compact('merchant', 'routes', 'schedules'))->withTitle($merchant->merchant_name);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $merchant = Merchant::find($id);
        return view('admin.merchant.edit', compact('merchant'))->withTitle('Update merchant');
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
        $data = ['status' => false, 'label' => 'error', 'content' => ''];
        $merchant = Merchant::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'merchant_name' => 'bail|required|string|max:191|unique:merchants,merchant_name,' . $merchant->id,
            'merchant_reg_no' => 'bail|required|string|unique:merchants,merchant_reg_no,' . $merchant->id,
            'merchant_address' => 'bail|required|max:191|string',
            'merchant_email' => 'bail|email|max:191|unique:users,email,' . $merchant->user_id,
            'merchant_mobile' => 'bail|required|max:11|regex:/^(01){1}[3456789]{1}(\d){8}$/|unique:users,mobile,' . $merchant->user_id,
            'merchant_phone' => 'bail|nullable|string',
            'merchant_password' => 'bail|nullable|max:20|min:8',
            'logo' => 'bail|nullable|image|mimes:png,jpg,jpeg|max:150',
            'vat_applicable_to' => 'bail|required',
            'vat_visibility' => 'bail|required',
            'honorium_service_charge' => 'required|numeric',
            'honorium_type' => 'bail|required|string'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();

            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator->errors())->withInput( $request->all());
            }
        }

        DB::beginTransaction();
        try {
            //proecss request
            $merchant->merchant_name = $request->merchant_name;
            $merchant->merchant_reg_no = $request->merchant_reg_no;

            if ($request->merchant_reg_expiry_date) {
                $regDate = \DateTime::createFromFormat('d/m/Y', $request->merchant_reg_expiry_date);
                $merchant->merchant_reg_expiry_date = $regDate->format('Y-m-d');
            }

            $merchant->merchant_email = $request->merchant_email;
            $merchant->merchant_mobile = $request->merchant_mobile;
            $merchant->merchant_phone = $request->merchant_phone;
            $merchant->merchant_fax = $request->merchant_fax;
            $merchant->merchant_address = $request->merchant_address;
            $merchant->vat_visibility = (int) $request->vat_visibility;
            $merchant->vat_applicable_to = (in_array($request->vat_applicable_to, ['customer', 'merchant', 'vendor'])) ? ( (!(int) $request->vat_visibility) ? 'merchant' : $request->vat_applicable_to) : $merchant->vat_applicable_to;
            $merchant->honorium_service_charge = ( $request->honorium_service_charge ) ? abs($request->honorium_service_charge ) : $merchant->honorium_service_charge;
            $merchant->honorium_type = ($request->honorium_type == 'fixed') ? 'fixed' : 'percent';
            if ($request->logo) {
                $imageName = time() . '.' . $request->logo->extension();
                $request->logo->move(public_path('images'), $imageName);
                $merchant->logo = $imageName;
            }

            if ($merchant->save()) {
                $user = $merchant->user;
                $user->type = 'merchant';
                $user->email = $request->merchant_email;
                $user->mobile = $request->merchant_mobile;
                $user->name = $request->merchant_name;
                $user->password = ($request->merchant_password) ? Hash::make($request->merchant_password) : $user->password;
                $user->save();

                $role = Role::where('name', 'merchant')->first();
                $user->assignRole($role);
                $merchant->update(['user_id' => $user->id]);

                DB::commit();
                $data['content'] = 'Merchant account successfully updated';
                $data['label'] = 'success';
                $data['status'] = true;
            } else {
                throw new \Exception('Cannot update merchant');
            }
        } catch (\Exception $e) {
            DB::rollback();
            $data['content'] = $e->getMessage();
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        }

        return redirect()->route('dashboard.merchant.index')->with([
            'message' => $data
        ]);
    }

    public function supervisors(Request $request)
    {
        if($request->wantsJson()) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = User::with(['vehicles.vehicle', 'meta'])->whereHas('roles', function($q) {
                $q->where('name', AppConst::SUPERVISOR_ROLE);
            });

            //filter by keyword
            if( isset( $_GET['keyword'] ) && $_GET['keyword'] != null ){
                $keyword = $_GET['keyword'];
                $query->where('name', 'LIKE',"%$keyword%");
                $query->orWhere('mobile', 'LIKE',"%$keyword%");
                $query->orWhere('email', 'LIKE',"%$keyword%");
            }

            if( isset( $_GET['status'] ) && $_GET['status'] != null ){
                $status = (int) $_GET['status'];
                $query->where('status', $status);
            }

            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            $query->orderBy($column, $order);
            $supervisors = $query->get();
//            dd($supervisors->toArray());
            $config = config('constants.user_status');

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $supervisors->map(function($item, $key) use($config) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'photo' => ($item->profile_pic) ? asset($item->profile_pic) : asset('default/avatar.png'),
                        'mobile' => $item->mobile,
                        'email' => $item->email,
                        'type' => ($item->type === 'admin') ? AppConst::OWNER : 'merchant',
                        'vehicles' => $item->vehicles->map(function($item, $key) {
                            return [
                                'id' => $item->vehicle_id,
                                'name' => $item->vehicle['name']
                            ];
                        })->toArray(),
                        'nid_visiable_at' => ($item->meta && $item->meta->nid_visible_until > now()) ? $item->meta->nid_visible_until : '----',
                        'status' => $config[$item->status]
                    ];
                })->toArray()
            ];

            return response()->json($data);
        }
        return view('admin.merchant.supervisor')->withTitle('Supervisors');
    }

    /**
     * Remove the specified resource .
     *
     * @query  string  $term
     * @return \Illuminate\Http\Response
     */
    public function suggest()
    {
        $query = Merchant::select('merchant_name', 'id', 'user_id');

        if (isset($_GET['term'])) {
            $term = $_GET['term'];
            $query->where('merchant_name', 'LIKE', '%' . $term . '%');
        }

        $query = $query->paginate(15);

        $results = [];

        if ($query) {
            foreach ($query as $q) {
                $row['id'] = $q->user_id;
                $row['name'] = $q->merchant_name;

                array_push($results, $row);
            }
        }

        return response()->json(['results' => $results], 200);
    }

    public function action( Request $request )
    {
        $customer_id = $request->id;
        if( isset( $request->action ) ) {
            return call_user_func(array($this, $request->action), $request);
        }
    }

    private function active( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Merchant cannot activate'];
        $merchant = Merchant::findOrFail($request->id);
        $merchant->status = 1;
        if( $merchant->save()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Merchant has been successfully activated';
        }

        if( $request->ajax() === True || request()->wantsJson()) {
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function delete( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Merchant cannot delete'];
        $merchant = Merchant::findOrFail($request->id);
        if( $merchant->delete()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Merchant has been successfully deleted';
        }

        if( $request->ajax() === True || request()->wantsJson()) {
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function restore( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Merchant cannot delete'];
        $merchant = Merchant::withTrashed()->findOrFail($request->id);
        if( $merchant->restore()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Merchant has been successfully deleted';
        }

        if( $request->ajax() === True || request()->wantsJson()) {
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

    public function vehicleStatistics( Request $request, $id )
    {
        if( $request->ajax() == true ) {
            $date_from = date('Y-m-01');
            $date_to = date('Y-m-t');

            if( $request->date_from ){
                $date_from = \DateTime::createFromFormat('d/m/Y', $request->date_from )->format('Y-m-d');
            }

            if( $request->date_to ){
                $date_to = \DateTime::createFromFormat('d/m/Y', $request->date_to )->format('Y-m-d');
            }

            $query = Vehicle::with(['schedules' => function($query) use($date_from, $date_to) {
                $query->where('schedule_date', '>=', $date_from);
                $query->where('schedule_date', '<=', $date_to);
                $query->where('status', 'ACTIVE');
                }, 'merchant', 'schedules.mappings', 'schedules.bookingConfirmed.payment'
            ])->where('merchant_id', $id);

            $route_id = '';
            if( $request->route_id ) {
                $route_id = $request->route_id;
                $query->where('route_id', $request->route_id);
                $query->with(['schedules' => function($q) use ($request) {
                    $q->where('route_id', $request->route_id);
                }]);
            }

            $type = 'All';

            if( $request->type ) {
                $type = (string) ucfirst($request->type);
                $query->wherehas('schedules', function($q) use ($request) {
                    $q->whereHas('mappings', function($q) use ($request) {
                        $q->where('ownership', strtolower($request->type));
                    });
                });
            }

            $total = $query->count();
            $results = $query->get();

            //sanitize data
            $returnArr = array();
            $vat_visibility = true;
            if ($results) {
                foreach ($results as $result) {
                    $vat_visibility = ($result->merchant['vat_visibility']) ? true : false;
                    $row['vehicle_id'] = $result->id;
                    $row['vehicle_name'] = $result->name;
                    $row['total_schedules'] = $result->schedules->count();
                    $row['total_routes'] = 0;
                    $row['no_of_passengers'] = 0;
                    $row['total_ticket_sell_amount'] = 0;
                    $row['total_vat_amount'] = 0;
                    $row['total_waiver'] = 0;
                    $row['total_service_charge'] =0;
                    $row['total_bank_charge'] =0;
                    $row['no_of_discount_applied'] = 0;
                    $row['discount_amount'] = 0;
                    $row['coupon_amount'] = 0;
                    $row['no_of_coupon_applied'] = 0;
                    $row['no_of_ticket_sell'] = 0;
                    $row['cabins_total'] = 0;
                    $row['cabins_booking'] = 0;
                    $row['cabin_sell_amount'] = 0;
                    $row['cabin_sell_vat'] =0;
                    $row['cabin_sell_vat_customer'] = 0;
                    $row['cabin_sell_vat_merchant'] = 0;
                    $row['cabin_sell_vat_vendor'] = 0;
                    $row['cabin_service_charge'] = 0;
                    $row['seats_total'] = 0;
                    $row['seats_sell_total'] = 0;
                    $row['seats_booking'] = 0;
                    $row['seat_sell_amount'] =0;
                    $row['seat_sell_vat'] =0;
                    $row['seat_sell_vat_customer'] = 0;
                    $row['seat_sell_vat_merchant'] = 0;
                    $row['seat_sell_vat_vendor'] = 0;
                    $row['seat_service_charge'] = 0;
                    $row['decks_total'] = 0;
                    $row['decks_booking'] = 0;
                    $row['deck_sell_amount'] =0;
                    $row['deck_sell_vat'] =0;
                    $row['deck_sell_vat_customer'] = 0;
                    $row['deck_sell_vat_merchant'] = 0;
                    $row['deck_sell_vat_vendor'] = 0;
                    $row['deck_service_charge'] = 0;
                    if( $result->schedules ) {
                        $routes = [];
                        foreach( $result->schedules as $schedule ) {
                            array_push($routes, $schedule['route_id']);
                            $mappings = new Collection($schedule->mappings);
                            switch ($type) {
                                case 'Merchant':
                                    $row['cabins_total'] += $mappings->where('ownership', 'merchant')->where('type', 'cabin')->count();
                                    $row['seats_total'] += $mappings->where('ownership', 'merchant')->where('type', 'seat')->count();
                                    break;
                                case AppConst::OWNER:
                                    $row['cabins_total'] += $mappings->where('ownership', AppConst::OWNER)->where('type', 'cabin')->count();
                                    $row['seats_total'] += $mappings->where('ownership', AppConst::OWNER)->where('type', 'seat')->count();
                                    break;

                                default:
                                    $row['cabins_total'] += $mappings->where('type', 'cabin')->count();
                                    $row['seats_total'] += $mappings->where('type', 'seat')->count();
                                    break;
                            }

                            $row['decks_total'] += $result->passengers_capacity;
                            if( $schedule['bookingConfirmed'] ) {
                                $coupons = [];
                                $discounts = [];
                                $payments = [];
                                foreach( $schedule->bookingConfirmed as $item ) {
                                    $payments[$item['booking_id']] = $item['payment'];
                                    if( $item['discount_type'] == 'coupon') {
                                        if( abs($item['discount']) > 0 ) {
                                            array_push($coupons, $item['booking_id']);
                                        }
                                    } else {
                                        if( abs($item['discount']) > 0 ) {
                                            array_push($discounts, $item['booking_id']);
                                        }
                                    }
                                    $vat = abs($item['price']*($item['vat_amount'] / 100));
                                    $charge = abs($item['price']*($item['charge_amount'] / 100));
                                    if( $type == 'All' || $item['booking_party'] == strtolower($type) ) {
                                        $passenger = json_decode($item['passenger']);
                                        $customer_vat = 0;
                                        if( $item['vat_applicable_to'] == 'customer' ) {
                                            $customer_vat += $vat;
                                            $row[$item['booking_type'] . '_sell_vat_customer'] += $vat;
                                        } elseif( $item['vat_applicable_to'] == 'merchant') {
                                            $row[$item['booking_type'] . '_sell_vat_merchant'] += $vat;
                                        } else {
                                            $row[$item['booking_type'] . '_sell_vat_vendor'] += $vat;
                                        }
                                        $row['total_service_charge'] += $charge;
                                        $discount = $item['discount'];
                                        if( $item['booking_type'] == 'deck' ) {
                                            $row['decks_booking'] += $passenger->person;
                                            $row['deck_sell_amount'] += abs($item['price'] + $customer_vat + $charge - $discount);
                                            $row['deck_sell_vat'] += $vat;
                                            $row['no_of_ticket_sell'] += abs($passenger->person);
                                        } elseif( $item['booking_type'] == 'cabin' ) {
                                            $row['cabins_booking'] += 1;
                                            $row['cabin_sell_amount'] += abs($item['price'] + $customer_vat + $charge - $discount);
                                            $row['cabin_sell_vat'] += $vat;
                                            $row['no_of_ticket_sell'] += 1;
                                        } else {
                                            $row['seats_booking'] += 1;
                                            $row['seat_sell_amount'] += abs($item['price'] + $customer_vat + $charge - $discount);
                                            $row['seat_sell_vat'] += $vat;
                                            $row['no_of_ticket_sell'] += 1;
                                        }

                                        $row['total_ticket_sell_amount'] += abs( $item['price'] + $customer_vat + $charge - $item['discount']);
                                        $row['no_of_passengers'] += $passenger->person;
                                        $row['total_vat_amount'] += $vat;
                                        if( $item['discount_type'] == 'coupon' ) {
                                            $row['coupon_amount'] += abs($item['discount']);
                                        } else {
                                            $row['discount_amount'] += abs($item['discount']);
                                        }
                                        $row['total_waiver'] += abs($item['discount']);
                                    }
                                }
                                if( $payments ) {
                                    foreach( $payments as $key => $payment ) {
                                        $row['total_bank_charge'] += abs($payment['paid_amount'] - $payment['store_amount']);
                                    }
                                }
                                $row['no_of_discount_applied'] += count(array_values( array_unique($discounts)));
                                $row['no_of_coupon_applied'] += count(array_values( array_unique($coupons)));
                            }
                        }
                        $row['total_routes'] = count(array_values( array_unique($routes ) ));
                    }
                    array_push($returnArr, $row);
                }
            }

            $title = $type . ' (' . date('d/m/Y', strtotime( $date_from )) .' to ';
            $title .= ($date_to == '-') ? $date_to : date('d/m/Y', strtotime($date_to));
            $str = '<div class="row">
                <div class="col-9"><h2>Account: ' . $title . '</h2></div>
                <div class="col-md-3 text-right">
                      <button type="button" class="btn btn-primary" onclick="printJS(\'vehicleStatistics\', \'html\')"><i class="fa fa-print"></i> Print</button>
                    <button type="button" class="btn btn-success" onclick="tableToExcel(\'vehicleStat\', \'vehicle-statistics\', \'statistics.xls\')"><i class="fa fa-file-excel"></i> Excel</button>
                </div>
                </div>';
                $str .= '<table class="table table-striped table-bordered" id="vehicleStat">
                        <thead>
                          <tr>
                            <th></th>
                            <th>Vehicle</th>
                            <th>No of Routes</th>
                            <th>No of Passengers</th>
                            <th>Total Ticket sell amount</th>';
                            if( $vat_visibility ) {
                                $str .= '<th>Total Vat amount</th>';
                            }
                            $str .= '<th>Waiver</th>
                            <th>No of discount applied</th>
                            <th>Discount amount</th>
                            <th>No of coupon applied</th>
                            <th>Coupon amount</th>
                            <th>Service charge</th>
                            <th>Bank charge</th>
                          </tr>
                        </thead>
                        <tbody>';
            if( $returnArr ) {
                foreach( $returnArr as $key => $item ) {
                    if( $vat_visibility ) {
                        $str .= '
                            <tr>
                                <td>
                                  <span class="toggleRow" onclick="toggleRow(this)" data-id="' . $key . '"><i class="fa fa-plus"></i></span>
                                </td>
                                <td><a href="' . route('dashboard.vehicle.show', ['id' => $item['vehicle_id'], 'tab' => 'stat']) . '">' . $item['vehicle_name'] . '</a></td>
                                <td><a href="#" onclick="openStatToModal(this); return false;" data-id="' . $item['vehicle_id'] . '" data-date-from="' . $date_from . '" data-date-to="' . $date_to . '" data-type="' . $type . '" data-route-id="' . $route_id . '">' . $item['total_routes'] . '</a></td>
                                <td>' . $item['no_of_passengers'] . '</td>
                                <td>' . number_format($item['total_ticket_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['total_vat_amount'], 2) . '</td>
                                <td>' . number_format($item['total_waiver'], 2) . '</td>
                                <td>' . $item['no_of_discount_applied'] . '</td>
                                <td>' . number_format($item['discount_amount'], 2) . '</td>
                                <td>' . $item['no_of_coupon_applied'] . '</td>
                                <td>' . number_format($item['coupon_amount'], 2) . '</td>
                                <td>' . number_format($item['total_service_charge'], 2) . '</td>
                                <td>' . number_format($item['total_bank_charge'], 2) . '</td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td rowspan="2"></td>
                                <td>No of Ticket sell</td>
                                <td>Cabin (b/c)</td>
                                <td>Sell amount</td>
                                <td>Vat (M/V/C)</td>
                                <td>Seat (b/c)</td>
                                <td>Sell amount</td>
                                <td>Vat (M/V/C)</td>
                                <td>Deck (b/c)</td>
                                <td>Sell amount</td>
                                <td>Vat (M/V/C)</td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td>' . $item['no_of_ticket_sell'] . '</td>
                                <td>' . $item['cabins_booking'] . '/' . $item['cabins_total'] . '</td>
                                <td>' . number_format($item['cabin_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['cabin_sell_vat_merchant'], 2) . '/' . number_format($item['cabin_sell_vat_vendor'], 2) . '/' . number_format($item['cabin_sell_vat_customer'], 2) . '</td>
                                <td>' . $item['seats_booking'] . '/' . $item['seats_total'] . '</td>
                                <td>' . number_format($item['seat_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['seat_sell_vat_merchant'], 2) . '/' . number_format($item['seat_sell_vat_vendor'], 2) . '/' . number_format($item['seat_sell_vat_customer'], 2) . '</td>
                                <td>' . $item['decks_booking'] . '/' . $item['decks_total'] . '</td>
                                <td>' . number_format($item['deck_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['deck_sell_vat_merchant'], 2) . '/' . number_format($item['deck_sell_vat_vendor'], 2) . '/' . number_format($item['deck_sell_vat_customer'], 2) . '</td>
                                <td></td>
                                <td></td>
                            </tr>';
                    } else {
                        $str .= '
                            <tr>
                                <td>
                                  <span class="toggleRow" onclick="toggleRow(this)" data-id="' . $key . '"><i class="fa fa-plus"></i></span>
                                </td>
                                <td><a href="' . route('dashboard.vehicle.show', ['id' => $item['vehicle_id'], 'tab' => 'stat']) . '">' . $item['vehicle_name'] . '</a></td>
                                <td><a href="#" onclick="openStatToModal(this); return false;" data-id="' . $item['vehicle_id'] . '" data-date-from="' . $date_from . '" data-date-to="' . $date_to . '" data-type="' . $type . '" data-route-id="' . $route_id . '">' . $item['total_routes'] . '</a></td>
                                <td>' . $item['no_of_passengers'] . '</td>
                                <td>' . number_format($item['total_ticket_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['total_waiver'], 2) . '</td>
                                <td>' . $item['no_of_discount_applied'] . '</td>
                                <td>' . number_format($item['discount_amount'], 2) . '</td>
                                <td>' . $item['no_of_coupon_applied'] . '</td>
                                <td>' . number_format($item['coupon_amount'], 2) . '</td>
                                <td>' . number_format($item['total_service_charge'], 2) . '</td>
                                <td>' . number_format($item['total_bank_charge'], 2) . '</td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td rowspan="2"></td>
                                <td>No of Ticket sell</td>
                                <td>Cabin (b/c)</td>
                                <td>Sell amount</td>
                                <td>Seat (b/c)</td>
                                <td>Sell amount</td>
                                <td>Deck (b/c)</td>
                                <td>Sell amount</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td>' . $item['no_of_ticket_sell'] . '</td>
                                <td>' . $item['cabins_booking'] . '/' . $item['cabins_total'] . '</td>
                                <td>' . number_format($item['cabin_sell_amount'], 2) . '</td>
                                <td>' . $item['seats_booking'] . '/' . $item['seats_total'] . '</td>
                                <td>' . number_format($item['seat_sell_amount'], 2) . '</td>
                                <td>' . $item['decks_booking'] . '/' . $item['decks_total'] . '</td>
                                <td>' . number_format($item['deck_sell_amount'], 2) . '</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>';
                    }
                }
            } else {
                if($vat_visibility) {
                    $str .= "<tr><td colspan='13'>No data found</td></tr>";
                } else {
                    $str .= "<tr><td colspan='12'>No data found</td></tr>";
                }
            }
            $str .= '</tbody>
                      </table>';
            header('Content-Type: text/plain');
            // header('Content-Type: application/json');
            echo $str;
        }
    }

    public function routeStatistics( Request $request )
    {
        if( $request->ajax() == true ) {
            $date_from = date('Y-m-01');
            $date_to = date('Y-m-t');
            if( $request->date_from ){
                $date_from = \DateTime::createFromFormat('Y-m-d', $request->date_from )->format('Y-m-d');
            }

            if( $request->date_to ) {
                $date_to = \DateTime::createFromFormat('Y-m-d', $request->date_to )->format('Y-m-d');
            }

            $query = VehicleSchedule::with(['merchant', 'mappings', 'cabinMappings', 'seatMappings', 'bookingConfirmed.item.cabinType', 'bookingConfirmed.payment'])->where(['vehicle_id' => $request->vehicle_id, 'status' => 'ACTIVE'])->where('schedule_date', '>=', $date_from)->where('schedule_date', '<=', $date_to);

            if( $request->route_id ) {
                $query->where('route_id', $request->route_id);
            }

            $type = 'All';

            if( $request->type && $request->type != 'All') {
                $type = (string) ucfirst($request->type);
                $query->whereHas('mappings', function($q) use ($type) {
                    $q->where('ownership', strtolower($type));
                });
                $query->with([
                    'bookingConfirmed' => function($q) use ($request) {
                        $q->whereHas('booking', function($query) use ($request) {
                            $query->where('booking_party', $request->type);
                        });
                    },
                    'cabinMappings' => function($q) use ($request) {
                        $q->where('ownership', $request->type);
                    },
                    'seatMappings' => function($q) use ($request) {
                        $q->where('ownership', $request->type);
                    }
                ]);
            }

            $total = $query->count();
            $schedules = $query->get();

            //sanitize data
            $returnArr = [];
            $finalArr = [];
            $vat_visibility = true;
            if ($schedules) {
                foreach ($schedules as $schedule) {
                    $vat_visibility = ($schedule->merchant['vat_visibility']) ? true : false;
                    $returnArr[$schedule->route['route_name']][] = $schedule;
                }
            }

            if( $returnArr ) {
                foreach ($returnArr as $key => $schedules) {
                    $row['schedule_id'] = 0;
                    $row['route_id'] = 0;
                    $row['trip_route'] = $key;
                    $row['no_of_trips'] = count($schedules);
                    $row['no_of_passengers'] = 0;
                    $row['total_ticket_sell_amount'] = 0;
                    $row['total_vat_amount'] = 0;
                    $row['total_waiver'] = 0;
                    $row['total_bank_charge'] = 0;
                    $row['total_service_charge'] = 0;
                    $row['no_of_discount_applied'] = 0;
                    $row['discount_amount'] = 0;
                    $row['coupon_amount'] = 0;
                    $row['no_of_coupon_applied'] = 0;
                    $row['no_of_ticket_sell'] = 0;
                    $row['cabins_total'] = 0;
                    $row['cabins_booking'] = 0;
                    $row['cabin_sell_amount'] = 0;
                    $row['cabin_sell_vat'] =0;
                    $row['cabin_sell_vat_customer'] = 0;
                    $row['cabin_sell_vat_merchant'] = 0;
                    $row['cabin_sell_vat_vendor'] = 0;
                    $row['seats_total'] = 0;
                    $row['seats_booking'] = 0;
                    $row['seat_sell_amount'] =0;
                    $row['seat_sell_vat'] =0;
                    $row['seat_sell_vat_customer'] = 0;
                    $row['seat_sell_vat_merchant'] = 0;
                    $row['seat_sell_vat_vendor'] = 0;
                    $row['decks_total'] =0;
                    $row['decks_booking'] =0;
                    $row['deck_sell_amount'] =0;
                    $row['deck_sell_vat'] =0;
                    $row['deck_sell_vat_customer'] = 0;
                    $row['deck_sell_vat_merchant'] = 0;
                    $row['deck_sell_vat_vendor'] = 0;
                    foreach( $schedules as $schedule ) {
                        $row['vehicle_id'] = $schedule->vehicle_id;
                        $row['route_id'] = $schedule->route_id;
                        $row['cabins_total'] += $schedule->cabinMappings->count();
                        $row['seats_total'] += $schedule->seatMappings->count();
                        $row['decks_total'] += $schedule->vehicle['passengers_capacity'];
                            if( $schedule['bookingConfirmed'] ) {
                                $discounts = [];
                                $coupons = [];
                                $payments = [];
                                foreach( $schedule->bookingConfirmed as $item ) {
                                    $payments[$item['booking_id']] = $item['payment'];
                                    if( $item['discount_type'] == 'coupon') {
                                        if( abs($item['discount']) > 0 ) {
                                            array_push($coupons, $item['booking_id']);
                                        }
                                    } else {
                                        if( abs($item['discount']) > 0 ) {
                                            array_push($discounts, $item['booking_id']);
                                        }
                                    }
                                    $passenger = json_decode($item['passenger']);
                                    $vat = abs($item['price']*($item['vat_amount'] / 100));
                                    $customer_vat = 0;
                                    if( $item['vat_applicable_to'] == 'customer' ) {
                                        $customer_vat = abs($item['price']*($item['vat_amount'] / 100));
                                        $row[$item['booking_type'] . '_sell_vat_customer'] += $vat;
                                    } elseif( $item['vat_applicable_to'] == 'merchant') {
                                        $row[$item['booking_type'] . '_sell_vat_merchant'] += $vat;
                                    } else {
                                        $row[$item['booking_type'] . '_sell_vat_vendor'] += $vat;
                                    }
                                    $charge = abs($item['price']*($item['charge_amount'] / 100));
                                    $discount = $item['discount'];
                                    if( $item['booking_type'] == 'deck' ) {
                                        $row['decks_booking'] += round($passenger->person);
                                        $row['deck_sell_amount'] += abs($item['price'] + $customer_vat + $charge - $discount);
                                        $row['deck_sell_vat'] += $vat;
                                        $row['no_of_ticket_sell'] += abs($passenger->person);
                                    } elseif( $item['booking_type'] == 'cabin' ) {
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

                                    $row['total_ticket_sell_amount'] += abs( $item['price'] + $customer_vat + $charge - $discount);
                                    $row['no_of_passengers'] += $passenger->person;
                                    $row['total_vat_amount'] += $vat;
                                    if( $item['discount_type'] == 'coupon' ) {
                                        $row['coupon_amount'] += abs($item['discount']);
                                    } else {
                                        $row['discount_amount'] += abs($item['discount']);
                                    }
                                    $row['total_waiver'] += abs($item['discount']);
                                    $row['total_service_charge'] += $charge;
                                }
                                if( $payments ) {
                                    foreach( $payments as $key => $payment ) {
                                        $row['total_bank_charge'] += abs($payment['paid_amount'] - $payment['store_amount']);
                                    }
                                }
                                $row['no_of_discount_applied'] += count(array_values( array_unique($discounts)));
                                $row['no_of_coupon_applied'] += count(array_values( array_unique($coupons)));
                            }
                    }

                    array_push($finalArr, $row);
                }
            }
            $title = $type . ' (' . date('d/m/Y', strtotime( $date_from )) .' to ' . date('d/m/Y', strtotime($date_to)) . ')';
            $str = '<div class="row">
                <div class="col-9"></div>
                <div class="col-md-3 text-right">
                      <button type="button" class="btn btn-primary" onclick="printJS(\'routeStatistics\', \'html\')"><i class="fa fa-print"></i> Print</button>
                    <button type="button" class="btn btn-success" onclick="tableToExcel(\'routeStat\', \'route-statistics\', \'statistics.xls\')"><i class="fa fa-file-excel"></i> Excel</button>
                </div>
                </div>';
            if( $vat_visibility ) {
                $str .= '<table class="table table-striped table-bordered" id="routeStat">
                        <thead>
                          <tr>
                            <th></th>
                            <th>Route name</th>
                            <th>No of Trips</th>
                            <th>No of Passengers</th>
                            <th>Total Ticket sell amount</th>
                            <th>Total Vat amount</th>
                            <th>Waiver</th>
                            <th>No of discount applied</th>
                            <th>Discount amount</th>
                            <th>No of coupon applied</th>
                            <th>Coupon amount</th>
                            <th>Service charge</th>
                            <th>Bank charge</th>
                          </tr>
                        </thead>
                        <tbody>';
            } else {
                $str .= '<table class="table table-striped table-bordered">
                        <thead>
                          <tr>
                            <th></th>
                            <th>Route name</th>
                            <th>No of Trips</th>
                            <th>No of Passengers</th>
                            <th>Total Ticket sell amount</th>
                            <th>Waiver</th>
                            <th>No of discount applied</th>
                            <th>Discount amount</th>
                            <th>No of coupon applied</th>
                            <th>Coupon amount</th>
                            <th>Service charge</th>
                            <th>Bank charge</th>
                          </tr>
                        </thead>
                        <tbody>';
            }
            if( $finalArr ) {
                foreach( $finalArr as $key => $item ) {
                    if( $vat_visibility ) {
                        $str .= '
                            <tr>
                                <td>
                                  <span class="toggleRow" onclick="toggleRow(this)" data-id="' . $key . '"><i class="fa fa-plus"></i></span>
                                </td>
                                <td>' . $item['trip_route'] . '</td>
                                <td><a href="#" onclick="scheduleStatModal(this); return false;" data-id="' . $item['vehicle_id'] . '" data-date-from="' . $date_from . '" data-date-to="' . $date_to . '" data-route-id="' . $item['route_id'] . '" data-type="' . $type . '">' . $item['no_of_trips'] . '</a></td>
                                <td>' . $item['no_of_passengers'] . '</td>
                                <td>' . number_format($item['total_ticket_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['total_vat_amount'], 2) . '</td>
                                <td>' . number_format($item['total_waiver'], 2) . '</td>
                                <td>' . $item['no_of_discount_applied'] . '</td>
                                <td>' . number_format($item['discount_amount'], 2) . '</td>
                                <td>' . $item['no_of_coupon_applied'] . '</td>
                                <td>' . number_format($item['coupon_amount'], 2) . '</td>
                                <td>' . number_format($item['total_service_charge'], 2) . '</td>
                                <td>' . number_format($item['total_bank_charge'], 2) . '</td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td rowspan="2"></td>
                                <td>No of Ticket sell</td>
                                <td>Cabin (b/c)</td>
                                <td>Sell amount</td>
                                <td>Vat (M/V/C)</td>
                                <td>Seat (b/c)</td>
                                <td>Sell amount</td>
                                <td>Vat (M/V/C)</td>
                                <td>Deck (b/c)</td>
                                <td>Sell amount</td>
                                <td>Vat (M/V/C)</td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td>' . $item['no_of_ticket_sell'] . '</td>
                                <td>' . $item['cabins_booking'] . '/' . $item['cabins_total'] . '</td>
                                <td>' . number_format($item['cabin_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['cabin_sell_vat_merchant'], 2) . '/' . number_format($item['cabin_sell_vat_vendor'], 2) . '/' . number_format($item['cabin_sell_vat_customer'], 2) . '</td>
                                <td>' . $item['seats_booking'] . '/' . $item['seats_total'] . '</td>
                                <td>' . number_format($item['seat_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['seat_sell_vat_merchant'], 2) . '/' . number_format($item['seat_sell_vat_vendor'], 2) . '/' . number_format($item['seat_sell_vat_customer'], 2) . '</td>
                                <td>' . $item['decks_booking'] . '/' . $item['decks_total'] . '</td>
                                <td>' . number_format($item['deck_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['deck_sell_vat_merchant'], 2) . '/' . number_format($item['deck_sell_vat_vendor'], 2) . '/' . number_format($item['deck_sell_vat_customer'], 2) . '</td>
                                <td></td>
                                <td></td>
                            </tr>';
                    } else {
                        $str .= '
                            <tr>
                                <td>
                                  <span class="toggleRow" onclick="toggleRow(this)" data-id="' . $key . '"><i class="fa fa-plus"></i></span>
                                </td>
                                <td>' . $item['trip_route'] . '</td>
                                <td><a href="#" onclick="scheduleStatModal(this); return false;" data-id="' . $item['vehicle_id'] . '" data-date-from="' . $date_from . '" data-date-to="' . $date_to . '" data-route-id="' . $item['route_id'] . '" data-type="' . $type . '">' . $item['no_of_trips'] . '</a></td>
                                <td>' . $item['no_of_passengers'] . '</td>
                                <td>' . number_format($item['total_ticket_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['total_waiver'], 2) . '</td>
                                <td>' . $item['no_of_discount_applied'] . '</td>
                                <td>' . number_format($item['discount_amount'], 2) . '</td>
                                <td>' . $item['no_of_coupon_applied'] . '</td>
                                <td>' . number_format($item['coupon_amount'], 2) . '</td>
                                <td>' . number_format($item['total_service_charge'], 2) . '</td>
                                <td>' . number_format($item['total_bank_charge'], 2) . '</td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td rowspan="2"></td>
                                <td>No of Ticket sell</td>
                                <td>Cabin (b/c)</td>
                                <td>Sell amount</td>
                                <td>Seat (b/c)</td>
                                <td>Sell amount</td>
                                <td>Deck (b/c)</td>
                                <td>Sell amount</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td>' . $item['no_of_ticket_sell'] . '</td>
                                <td>' . $item['cabins_booking'] . '/' . $item['cabins_total'] . '</td>
                                <td>' . number_format($item['cabin_sell_amount'], 2) . '</td>
                                <td>' . $item['seats_booking'] . '/' . $item['seats_total'] . '</td>
                                <td>' . number_format($item['seat_sell_amount'], 2) . '</td>
                                <td>' . $item['decks_booking'] . '/' . $item['decks_total'] . '</td>
                                <td>' . number_format($item['deck_sell_amount'], 2) . '</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>';
                    }
                }
            } else {
                if( $vat_visibility ) {
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

    public function scheduleStatistics( Request $request )
    {
        if( $request->ajax() == true ) {
            $date_from = date('Y-m-01');
            $date_to = date('Y-m-t');
            if( $request->date_from ) {
                $date_from = \DateTime::createFromFormat('Y-m-d', $request->date_from )->format('Y-m-d');
            }

            if( $request->date_to ) {
                $date_to = \DateTime::createFromFormat('Y-m-d', $request->date_to )->format('Y-m-d');
            }

            $query = VehicleSchedule::with(['merchant', 'mappings', 'cabinMappings', 'seatMappings', 'bookingConfirmed.item.cabinType', 'bookingConfirmed.payment'])->where(['vehicle_id' => $request->vehicle_id, 'status' => 'ACTIVE'])->where('schedule_date', '>=', $date_from)->where('schedule_date', '<=', $date_to);

            if( $request->route_id ) {
                $query->where('route_id', $request->route_id);
            }

            $type = 'All';

            if( $request->type && $request->type != 'All') {
                $type = (string) ucfirst($request->type);
                $query->whereHas('mappings', function($q) use ($type) {
                    $q->where('ownership', strtolower($type));
                });
                $query->with([
                    'bookingConfirmed' => function($q) use ($request) {
                        $q->whereHas('booking', function($query) use ($request) {
                            $query->where('booking_party', $request->type);
                        });
                    },
                    'cabinMappings' => function($q) use ($request) {
                        $q->where('ownership', $request->type);
                    },
                    'seatMappings' => function($q) use ($request) {
                        $q->where('ownership', $request->type);
                    }
                ]);
            }

            $total = $query->count();
            $schedules = $query->get();

            $finalArr = [];
            $vat_visibility = true;
            if( $schedules ) {
                foreach ($schedules as $schedule) {
                    $vat_visibility = ($schedule->merchant['vat_visibility']) ? true : false;
                    $row['schedule_id'] = 0;
                    $row['trip_route'] = $schedule->route['route_name'];
                    if( $schedule->schedule_type == 'reverse' ) {
                        $row['trip_route'] = $schedule->route['endingPoint']['ghat']['name'] . ' - ' . $schedule->route['startingPoint']['ghat']['name'];
                    }
                    $row['trip_date'] = date('d M Y', strtotime($schedule->schedule_date));
                    $row['no_of_passengers'] = 0;
                    $row['total_ticket_sell_amount'] = 0;
                    $row['total_vat_amount'] = 0;
                    $row['total_waiver'] = 0;
                    $row['total_bank_charge'] = 0;
                    $row['total_service_charge'] = 0;
                    $row['no_of_discount_applied'] = 0;
                    $row['discount_amount'] = 0;
                    $row['coupon_amount'] = 0;
                    $row['no_of_coupon_applied'] = 0;
                    $row['no_of_ticket_sell'] = 0;
                    $row['cabins_total'] = 0;
                    $row['cabins_booking'] = 0;
                    $row['cabin_sell_amount'] = 0;
                    $row['cabin_sell_vat'] =0;
                    $row['cabin_sell_vat_customer'] = 0;
                    $row['cabin_sell_vat_merchant'] = 0;
                    $row['cabin_sell_vat_vendor'] = 0;
                    $row['seats_total'] = 0;
                    $row['seats_booking'] = 0;
                    $row['seat_sell_amount'] =0;
                    $row['seat_sell_vat'] =0;
                    $row['seat_sell_vat_customer'] = 0;
                    $row['seat_sell_vat_merchant'] = 0;
                    $row['seat_sell_vat_vendor'] = 0;
                    $row['decks_total'] =0;
                    $row['decks_booking'] =0;
                    $row['deck_sell_amount'] =0;
                    $row['deck_sell_vat'] =0;
                    $row['deck_sell_vat_customer'] = 0;
                    $row['deck_sell_vat_merchant'] = 0;
                    $row['deck_sell_vat_vendor'] = 0;
                    $row['cabins_total'] += $schedule->cabinMappings->count();
                    $row['seats_total'] += $schedule->seatMappings->count();
                    $row['decks_total'] += $schedule->vehicle['passengers_capacity'];
                    if( $schedule['bookingConfirmed'] ) {
                        $discounts = [];
                        $coupons = [];
                        $payments = [];
                        foreach( $schedule->bookingConfirmed as $item ) {
                            $payments[$item['booking_id']] = $item['payment'];
                            if( $item['discount_type'] == 'coupon') {
                                if( abs($item['discount']) > 0 ) {
                                    array_push($coupons, $item['booking_id']);
                                }
                            } else {
                                if( abs($item['discount']) > 0 ) {
                                    array_push($discounts, $item['booking_id']);
                                }
                            }
                            $passenger = json_decode($item['passenger']);
                            $vat = abs($item['price']*($item['vat_amount'] / 100));
                            $customer_vat = 0;
                            if( $item['vat_applicable_to'] == 'customer' ) {
                                $customer_vat = abs($item['price']*($item['vat_amount'] / 100));
                                $row[$item['booking_type'] . '_sell_vat_customer'] += $vat;
                            } elseif( $item['vat_applicable_to'] == 'merchant') {
                                $row[$item['booking_type'] . '_sell_vat_merchant'] += $vat;
                            } else {
                                $row[$item['booking_type'] . '_sell_vat_vendor'] += $vat;
                            }
                            $charge = abs($item['price']*($item['charge_amount'] / 100));
                            $discount = $item['discount'];
                            if( $item['booking_type'] == 'deck' ) {
                                $row['decks_booking'] += round($passenger->person);
                                $row['deck_sell_amount'] += abs($item['price'] + $customer_vat + $charge - $discount);
                                $row['deck_sell_vat'] += $vat;
                                $row['no_of_ticket_sell'] += abs($passenger->person);
                            } elseif( $item['booking_type'] == 'cabin' ) {
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

                            $row['total_ticket_sell_amount'] += abs( $item['price'] + $customer_vat + $charge - $discount);
                            $row['no_of_passengers'] += $passenger->person;
                            $row['total_vat_amount'] += $vat;
                            if( $item['discount_type'] == 'coupon' ) {
                                $row['coupon_amount'] += abs($item['discount']);
                            } else {
                                $row['discount_amount'] += abs($item['discount']);
                            }
                            $row['total_waiver'] += abs($item['discount']);
                            $row['total_service_charge'] += abs($charge);
                        }
                        if( $payments ) {
                            foreach( $payments as $key => $payment ) {
                                $row['total_bank_charge'] += abs($payment['paid_amount'] - $payment['store_amount']);
                            }
                        }
                        $row['no_of_discount_applied'] += count(array_values( array_unique($discounts)));
                        $row['no_of_coupon_applied'] += count(array_values( array_unique($coupons)));
                    }

                    array_push($finalArr, $row);
                }
            }
            $title = $type . ' (' . date('d/m/Y', strtotime( $date_from )) .' to ' . date('d/m/Y', strtotime($date_to)) . ')';
            $str = '<div class="row">
                <div class="col-9"></div>
                <div class="col-md-3 text-right">
                      <button type="button" class="btn btn-primary" onclick="printJS(\'scheduleStatistics\', \'html\')"><i class="fa fa-print"></i> Print</button>
                    <button type="button" class="btn btn-success" onclick="tableToExcel(\'scheduleStat\', \'route-statistics\', \'statistics.xls\')"><i class="fa fa-file-excel"></i> Excel</button>
                </div>
                </div>';
            if( $vat_visibility ) {
                $str .= '<table class="table table-striped table-bordered" id="scheduleStat">
                        <thead>
                          <tr>
                            <th></th>
                            <th>Route name</th>
                            <th>No of Trips</th>
                            <th>No of Passengers</th>
                            <th>Total Ticket sell amount</th>
                            <th>Total Vat amount</th>
                            <th>Waiver</th>
                            <th>No of discount applied</th>
                            <th>Discount amount</th>
                            <th>No of coupon applied</th>
                            <th>Coupon amount</th>
                            <th>Service Charge</th>
                            <th>Bank Charge</th>
                          </tr>
                        </thead>
                        <tbody>';
            } else {

                $str .= '<table class="table table-striped table-bordered">
                        <thead>
                          <tr>
                            <th></th>
                            <th>Route name</th>
                            <th>No of Trips</th>
                            <th>No of Passengers</th>
                            <th>Total Ticket sell amount</th>
                            <th>Waiver</th>
                            <th>No of discount applied</th>
                            <th>Discount amount</th>
                            <th>No of coupon applied</th>
                            <th>Coupon amount</th>
                            <th>Service Charge</th>
                            <th>Bank Charge</th>
                          </tr>
                        </thead>
                        <tbody>';
            }
            if( $finalArr ) {
                foreach( $finalArr as $key => $item ) {
                    if( $vat_visibility ) {
                        $str .= '
                            <tr>
                                <td>
                                  <span class="toggleRow" onclick="toggleRow(this)" data-id="' . $key . '"><i class="fa fa-plus"></i></span>
                                </td>
                                <td>' . $item['trip_route'] . '</td>
                                <td>' . $item['trip_date'] . '</td>
                                <td>' . $item['no_of_passengers'] . '</td>
                                <td>' . number_format($item['total_ticket_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['total_vat_amount'], 2) . '</td>
                                <td>' . number_format($item['total_waiver'], 2) . '</td>
                                <td>' . $item['no_of_discount_applied'] . '</td>
                                <td>' . number_format($item['discount_amount'], 2) . '</td>
                                <td>' . $item['no_of_coupon_applied'] . '</td>
                                <td>' . number_format($item['coupon_amount'], 2) . '</td>
                                <td>' . number_format($item['total_service_charge'], 2) . '</td>
                                <td>' . number_format($item['total_bank_charge'], 2) . '</td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td rowspan="2"></td>
                                <td>No of Ticket sell</td>
                                <td>Cabin (b/c)</td>
                                <td>Sell amount</td>
                                <td>Vat (M/V/C)</td>
                                <td>Seat (b/c)</td>
                                <td>Sell amount</td>
                                <td>Vat (M/V/C)</td>
                                <td>Deck (b/c)</td>
                                <td>Sell amount</td>
                                <td>Vat (M/V/C)</td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td>' . $item['no_of_ticket_sell'] . '</td>
                                <td>' . $item['cabins_booking'] . '/' . $item['cabins_total'] . '</td>
                                <td>' . number_format($item['cabin_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['cabin_sell_vat_merchant'], 2) . '/' . number_format($item['cabin_sell_vat_vendor'], 2) . '/' . number_format($item['cabin_sell_vat_customer'], 2) . '</td>
                                <td>' . $item['seats_booking'] . '/' . $item['seats_total'] . '</td>
                                <td>' . number_format($item['seat_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['seat_sell_vat_merchant'], 2) . '/' . number_format($item['seat_sell_vat_vendor'], 2) . '/' . number_format($item['seat_sell_vat_customer'], 2) . '</td>
                                <td>' . $item['decks_booking'] . '/' . $item['decks_total'] . '</td>
                                <td>' . number_format($item['deck_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['deck_sell_vat_merchant'], 2) . '/' . number_format($item['deck_sell_vat_vendor'], 2) . '/' . number_format($item['deck_sell_vat_customer'], 2) . '</td>
                                <td></td>
                                <td></td>
                            </tr>';
                    } else {
                        $str .= '
                            <tr>
                                <td>
                                  <span class="toggleRow" onclick="toggleRow(this)" data-id="' . $key . '"><i class="fa fa-plus"></i></span>
                                </td>
                                <td>' . $item['trip_route'] . '</td>
                                <td>' . $item['trip_date'] . '</td>
                                <td>' . $item['no_of_passengers'] . '</td>
                                <td>' . number_format($item['total_ticket_sell_amount'], 2) . '</td>
                                <td>' . number_format($item['total_waiver'], 2) . '</td>
                                <td>' . $item['no_of_discount_applied'] . '</td>
                                <td>' . number_format($item['discount_amount'], 2) . '</td>
                                <td>' . $item['no_of_coupon_applied'] . '</td>
                                <td>' . number_format($item['coupon_amount'], 2) . '</td>
                                <td>' . number_format($item['total_service_charge'], 2) . '</td>
                                <td>' . number_format($item['total_bank_charge'], 2) . '</td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td rowspan="2"></td>
                                <td>No of Ticket sell</td>
                                <td>Cabin (b/c)</td>
                                <td>Sell amount</td>
                                <td>Seat (b/c)</td>
                                <td>Sell amount</td>
                                <td>Deck (b/c)</td>
                                <td>Sell amount</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr class="collapse-' . $key . ' d-none">
                                <td>' . $item['no_of_ticket_sell'] . '</td>
                                <td>' . $item['cabins_booking'] . '/' . $item['cabins_total'] . '</td>
                                <td>' . number_format($item['cabin_sell_amount'], 2) . '</td>
                                <td>' . $item['seats_booking'] . '/' . $item['seats_total'] . '</td>
                                <td>' . number_format($item['seat_sell_amount'], 2) . '</td>
                                <td>' . $item['decks_booking'] . '/' . $item['decks_total'] . '</td>
                                <td>' . number_format($item['deck_sell_amount'], 2) . '</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>';
                    }
                }
            } else {
                if($vat_visibility) {
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
}
