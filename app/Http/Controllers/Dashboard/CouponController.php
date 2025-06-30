<?php

namespace App\Http\Controllers\Dashboard;

use Intervention\Image\Facades\Image;
use App\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Http\Requests\CouponCreateRequest;
use App\Services\CalculationService;
use App\Services\CouponService;
use App\Models\VehicleRoute;
use App\Models\Merchant;
use App\Models\Vehicle;
use App\Notifications\CouponBroadcust;
use App\Notifications\CouponSmsBroadcust;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class CouponController extends Controller
{
    protected $success = 200;
    private $calculation;
    private $coupon;

    public function __construct(CalculationService $calculationService, CouponService $coupon)
    {
        $this->calculation = $calculationService;
        $this->coupon = $coupon;
    }

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
            $query = Coupon::with(['user'])->where('type', '!=', 'banner');
            // $query = Coupon::with(['user', 'mappings.merchant', 'mappings.launch', 'mappings.route', 'mappings.customer']);

            // if( Auth::user()->type !== 'admin' ) {
            //     $query->where('type', Auth::user()->type);
            // }

            if (isset($_GET['route']) && $_GET['route'] != null) {
                $route = ( int )$_GET['route'];
                $query->where('type', 'route')->whereRaw("find_in_set($route,items)");
            }

            if (isset($_GET['merchant']) && $_GET['merchant'] != null) {
                $merchant = ( int )$_GET['merchant'];
                $query->where('type', 'merchant')->whereRaw("find_in_set($merchant,items)");
            }

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
            $coupons = $query->get()->toArray();
            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $count,
                'recordsFiltered' => $count,
                'data' => $coupons
            ];

            return response()->json($data);
        }

        return view('admin.coupon.index')->withTitle('Manage coupons');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $type = (isset($_GET['type'])) ? $_GET['type'] : 'coupon';
        $customers = User::whereHas('roles', function ($q) {
            $q->where('name', 'customer');
        })->pluck('name', 'id');

        return view('admin.coupon.create', compact('customers', 'type'))->withTitle('Add new ' . $type);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CouponCreateRequest $request)
    {
        try {
            if ($request->items && is_array($request->items)) {
                $request->merge(['items' => implode(',', $request->items)]);
            }
            DB::transaction(function() use($request) {
                $this->coupon->create($request->validated());
            }, 2);
            return redirect()->route('dashboard.coupon.index');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage() . $e->getFile() . $e->getLine());
        }

        return redirect()->back()->withInput($request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $coupon = Coupon::findOrFail($id);
        return view('admin.coupon.show', compact('coupon'))->with(['mappings'])->withTitle('Coupon statistics');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $coupon = Coupon::findOrFail($id);

        $ids = ($coupon->items) ? explode(',', $coupon->items) : [];

        $items = [];
        if ($coupon->type === 'customer') {
            $items = User::select('name', 'id')->whereRaw('FIND_IN_SET(id,"' . $coupon->items . '")')->get()->toArray();
        } else if ($coupon->type === 'merchant') {
            // $items = Merchant::select('merchant_name as name', 'user_id as id')->whereIn('FIND_IN_SET(user_id,' . $coupon->items. ')')->get()->toArray();
            $items = Merchant::select('merchant_name as name', 'user_id as id')->whereIn('user_id', $ids)->get()->toArray();
        } else if ($coupon->type === 'route') {
            $items = VehicleRoute::select('route_name as name', 'id')->whereIn('id', $ids)->get()->toArray();
            // $items = VehicleRoute::select('route_name as name', 'id')->whereRaw('FIND_IN_SET(id,' . $coupon->items. ')')->get()->toArray();
        } else if ($coupon->type === 'launch') {
            // $items = Vehicle::select('name', 'id')->whereRaw('FIND_IN_SET(id,' . $coupon->items. ')')->get()->toArray();
            $items = Vehicle::select('name', 'id')->whereIn('id', $ids)->get()->toArray();
        }
        return view('admin.coupon.edit', compact('coupon', 'items'))->withTitle('Update coupon');
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
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot update coupon'];
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|string|max:191,title',
            'description' => 'bail|nullable|string,content',
            'code' => 'bail|required|unique:coupons,code,' . $id,
            'discount_amount' => 'bail|required|numeric',
            'offer_start' => 'bail|required',
            'offer_end' => 'bail|required',
            'poster' => 'bail|nullable|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:min_width=460,min_height=340',
            'is_cabin' => 'bail|nullable|integer',
            'is_seat' => 'bail|nullable|integer',
            'is_deck' => 'bail|nullable|integer'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['msg'] = $validator->errors()->first();
            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();
        try {
            if ($request->offer_start) {
                $offerStart = \DateTime::createFromFormat('d/m/Y', $request->offer_start);
            } else {
                $offerStart = new \DateTime();
            }
            if ($request->offer_end) {
                $offerEnd = \DateTime::createFromFormat('d/m/Y', $request->offer_end);
            } else {
                $offerEnd = new \DateTime();
            }
            $coupon = Coupon::findOrFail($id);
            $coupon->update([
                'name' => $request->name,
                'code' => $request->code,
                'type' => $request->type,
                'discount_type' => $request->discount_type,
                'discount_amount' => $request->discount_amount,
                'is_offer' => ($request->is_offer) ? 1 : 0,
                'is_cabin' => ($request->is_cabin) ? "1" : "0",
                'is_seat' => ($request->is_seat) ? "1" : "0",
                'is_deck' => ($request->is_deck) ? "1" : "0",
                'offer_start' => $offerStart->format('Y-m-d'),
                'offer_end' => $offerEnd->format('Y-m-d'),
            ]);

            //upload poster/banner
            if ($request->file('poster')) {
                $image = $request->file('poster');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $destinationPath = public_path('uploads/banner/');
                $img = Image::make($image->getRealPath());
                $img->resize(460, 340, function ($constraint) {
                    $constraint->aspectRatio();
                })->save($destinationPath . '/' . $filename);

                $coupon->poster = '/uploads/banner/' . $filename;
            }

            if ($request->items && is_array($request->items)) {
                $coupon->items = implode(',', $request->items);
            }

            if ($coupon->save()) {
                DB::commit();
                $data['content'] = 'Coupon successfully updated';
                $data['label'] = 'success';
                $data['status'] = true;
            }
        } catch (\Exception $e) {
            DB::rollback();
            $data['content'] = $e->getMessage();
        }
        if ($validator->fails()) {
            $data['msg'] = $validator->errors()->first();
            if ($request->ajax() === True) {
                return response()->json($data, $this->success);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
            }
        }

        if ($request->ajax() === True) {
            return response()->json($data, $this->success);
        } else {
            if ($data['status'] == true) {
                return redirect()->route('dashboard.coupon.index')->with([
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
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot delete coupon'];
        $coupon = Coupon::findOrFail($id);

        DB::beginTransaction();
        try {
            $coupon->delete();
            DB::commit();
            $data['status'] = true;
            $data['label'] = 'success';
            $data['content'] = 'Coupon has been successfully deleted';
        } catch (\Exception $e) {
            DB::rollback();
        }

        return response()->json($data, $this->success);
    }

    public function action(Request $request)
    {
        if (isset($request->type) && in_array($request->type, ['enable', 'disable'])) {
            return call_user_func(array($this, $request->type . 'Bulk'), $request);
        } else {
            return response()->json(['status' => false, 'content' => 'Your action type not valid', 'label' => 'error']);
        }
    }

    public function enableBulk($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Action not taken'];
        $ids = explode(',', $request->ids);
        $coupons = Coupon::whereIn('id', $ids)->get();

        try {
            DB::transaction(function () use ($coupons, &$data, $request) {
                if ($coupons) {
                    foreach ($coupons as $coupon) {
                        $coupon->update(['status' => 1]);
                    }

                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Coupons are successfully enabled';
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

    public function disableBulk($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Action not taken'];
        $ids = explode(',', $request->ids);
        $coupons = Coupon::whereIn('id', $ids)->get();

        try {
            DB::transaction(function () use ($coupons, &$data, $request) {
                if ($coupons) {
                    foreach ($coupons as $coupon) {
                        $coupon->update(['status' => 2]);
                    }

                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Coupons are successfully disabled';
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

    public function broadcust(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'bail|required|numeric|exists:coupons,id',
            'type' => 'required|string'
        ]);

        if ($validator->fails() === true) {
            return response()->json(['status' => false, 'content' => $validator->errors()->first(), 'label' => 'error']);
        } else {
            if (isset($request->type) && in_array($request->type, ['email', 'sms', 'enable', 'disable'])) {
                return call_user_func(array($this, $request->type . 'Coupon'), $request);
            } else {
                return response()->json(['status' => false, 'content' => 'Your action type not valid', 'label' => 'error']);
            }
        }
    }

    private function enableCoupon($request)
    {
        $coupon = Coupon::find($request->id);
        if ($coupon->update(['status' => 1])) {
            return response()->json(['status' => true, 'content' => 'Coupon has been enabled.', 'label' => 'success']);
        } else {
            return response()->json(['status' => false, 'content' => 'Coupon cannot be enabled.', 'label' => 'error']);
        }
    }

    private function disableCoupon($request)
    {
        $coupon = Coupon::find($request->id);
        if ($coupon->update(['status' => 2])) {
            return response()->json(['status' => true, 'content' => 'Coupon has been disabled.', 'label' => 'success']);
        } else {
            return response()->json(['status' => false, 'content' => 'Coupon cannot be disabled.', 'label' => 'error']);
        }
    }

    private function smsCoupon($request)
    {
        $coupon = Coupon::find($request->id);
        $customers = null;
        if ($coupon->type == 'customer') {
            $ids = explode(',', $coupon->items);
            $customers = User::whereIn('id', $ids)->get();
        } else {
            $customers = User::where(['status' => 1, 'type' => 'customer'])->get();
        }
        if ($customers) {
            $numbers = [];
            $ids = [];
            foreach ($customers as $customer) {
                if (strlen($customer->mobile) >= 11) {
                    array_push($numbers, '88' . $customer->mobile);
                }
                if (strlen($customer->device_id) > 30) {
                    array_push($ids, $customer->device_id);
                }
            }
            if ($ids) {
                $title = 'New coupon received';
                $message = 'Dear subscriber, you have received new coupon which will valit within ' . date('d/m/Y', strtotime($coupon->offer_start)) . ' - ' . date('d/m/Y', strtotime($coupon->offer_end)) . '. Your coupon code is "' . $coupon->code . '"';
//                $firebase = new Firebase();
//                $firebase->setID($coupon->id);
//                $firebase->toMany($ids);
//                $firebase->setTitle($title);
//                $firebase->setBody($message);
//                $firebase->sendMultiple();
            }
            if ($numbers) {
                $mobiles = implode('|', $numbers);
                sendSMS([
                    'mobile' => $mobiles,
                    'message' => 'Dear subscriber, you have received new coupon which will valid within ' . date('d/m/Y', strtotime($coupon->offer_start)) . ' - ' . date('d/m/Y', strtotime($coupon->offer_end)) . '. Your coupon code is "' . $coupon->code . '"'
                ]);
            }
            Notification::send($customers, new CouponSmsBroadcust($coupon));
            $data['status'] = true;
            $data['label'] = 'success';
            $data['content'] = 'Email broadcusting success';
        }
        header('Content-Type: application/json');
        return response()->json($data);
    }

    private function emailCoupon($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot send message'];
        $coupon = Coupon::find($request->id);
        $customers = null;
        if ($coupon->type == 'customer') {
            $ids = explode(',', $coupon->items);
            $customers = User::whereIn('id', $ids)->get();
        } else {
            $customers = User::where(['status' => 1, 'type' => 'customer'])->get();
        }
        if ($customers) {
            $ids = [];
            foreach ($customers as $customer) {
                if (strlen($customer->device_id) > 30) {
                    array_push($ids, $customer->device_id);
                }
            }
            if ($ids) {
                $title = 'New coupon received';
                $message = 'Dear subscriber, you have received new coupon which will valit within ' . date('d/m/Y', strtotime($coupon->offer_start)) . ' - ' . date('d/m/Y', strtotime($coupon->offer_end)) . '. Your coupon code is "' . $coupon->code . '"';
//                $firebase = new Firebase();
//                $firebase->setID($coupon->id);
//                $firebase->toMany($ids);
//                $firebase->setTitle($title);
//                $firebase->setBody($message);
//                $firebase->sendMultiple();
            }
            Notification::send($customers, new CouponBroadcust($coupon));
            $data['status'] = true;
            $data['label'] = 'success';
            $data['content'] = 'Email broadcusting success';
        }
        header('Content-Type: text/plain');
        return response()->json($data);
    }
}
