<?php
namespace App\Http\Controllers\Dashboard;

use Illuminate\Support\Str;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserMeta;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
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

            $query = User::with(['meta'])->where('type', 'customer');

            //filter by keyword
            if( isset( $_GET['search'] ) && $_GET['search']['value'] != null ){
                $keyword = $_GET['search']['value'];
                    $query->where('name', 'LIKE',"%$keyword%");
                    $query->orWhere('mobile', 'LIKE',"%$keyword%");
                    $query->orWhere('email', 'LIKE',"%$keyword%");
            }


            if( isset( $_GET['status'] ) && $_GET['status'] != null ){
                $status = ( int ) $_GET['status'];
                if( $status == 9 ) {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }

            $total = $query->count();

            $query->offset($start);
            if( $limit < 0 ) {
                $limit = $total;
            }
            $query->limit($limit);
            $query->orderBy('created_at', 'desc');
            $customers = $query->get();

            //sanitize data
            $returnArr = array();
            if( $customers ) {
                foreach( $customers as $customer ) {
                    $row['id'] = $customer->id;
                    $row['name'] = $customer->name;
                    $row['email'] = $customer->email;
                    $row['mobile'] = $customer->mobile;
                    $row['created_by'] = ($customer->meta) ? $customer->meta['created_by'] : '';
                    $row['platform'] = ($customer->meta) ? $customer->meta['platform'] : '';
                    $row['joining_date'] = date('d M, Y', strtotime( $customer->created_at ) );
                    $row['status'] = $customer->status;
                    $row['photo'] = ($customer->profile_pic) ? asset($customer->profile_pic) : asset('default/avatar.png');
                    $row['deleted_at'] = $customer->deleted_at;
                    array_push( $returnArr, $row );
                }
            }

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $returnArr
            ];

            return response()->json( $data );
        }

        return view('admin.customer.index')->withTitle('Customers');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.customer.create')->withTitle('Add new Customer');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Customer cannot be created'];
        $validator = Validator::make( $request->all(), [
            'name' => 'bail|required|string',
            'email' => 'bail|required|email|unique:users,email',
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|unique:users,mobile'
        ]);

        if( $validator->fails() == True ) {
            $data['content'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try{
                $customer = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile' => $request->mobile,
                    'password' => Hash::make(\Str::random(8)),
                    'remember_token' => \Str::random(10),
                    'type' => 'customer'
                ]);
                $customerRole = Role::where('name', 'customer')->first();
                $customer->assignRole($customerRole);
                UserMeta::create([
                    'officer_id' => Auth::user()->id,
                    'officer_designation' => Auth::user()->designation_id,
                    'user_id' => $customer->id,
                    'created_by' => Auth::user()->name,
                    'platform' => 'counter'
                ]);
                DB::commit();
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = 'Customer has been successfully created.';
            }  catch(\Exception $e) {
                DB::rollback();
//                Log::debug($e->getMessage());
                $data['content'] = $e->getMessage();
            }
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success );
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.customer.index')->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
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
        $customer = User::findOrFail($id);

        //travel history
        $bookingItems = BookingItem::with(['booking','trip.launch', 'trip.route', 'cancellations'])
            ->whereHas('booking', function($q) {
                $q->whereNotIn('status', ['PENDING', 'FAILED']);
            })
            ->where('customer_id', $id)->get();
        $mostVisited = [];
        $stat = [
            'total_bookings' => 0,
            'total_booking_amount' => 0,
            'total_cancelled' => 0,
            'total_cancelled_amount' => 0,
            'total_discounts' => 0,
            'total_vat' => 0,
            'total_charge' => 0,
            'total_customer_vat' => 0,
            'total_refunded' => 0
        ];
        foreach( $bookingItems as $item ) {
            $cancellation = collect( $item->cancellations )->whereIn('items', $item->id)->first();
            $vat = abs($item->price *($item->vat_amount/100));
            $charge = abs($item->price *($item->charge_amount/100));
            $stat['total_vat'] += $vat;
            $stat['total_charge'] += $charge;
            if( $item->booking_type == 'deck') {
                $passenger = json_decode($item->passenger);
                $stat['total_bookings'] += $passenger->person;
                $stat['total_cancelled'] += ($item->status == 2) ? $passenger->person : 0;
            } else {
                $stat['total_bookings'] += 1;
                $stat['total_cancelled'] += ($item->status == 2) ? 1 : 0;
            }
            $stat['total_booking_amount'] += abs($item->price - $item->discount + $charge);
            if( $item->vat_applicable_to == 'customer') {
                $stat['total_booking_amount'] += $vat;
                $stat['total_customer_vat'] += $vat;
            }
            if( $item->status == 2) {
                $stat['total_cancelled_amount'] += abs($item->price - $item->discount);
                if( $cancellation && $cancellation->charge_refundable) {
                    $stat['total_cancelled_amount'] += $charge;
                }
                if( $cancellation && $cancellation->charge_refundable) {
                    $stat['total_cancelled_amount'] += ( $item->vat_applicable_to == 'customer') ? $vat : 0;
                }
            }
            if( $cancellation && $cancellation->status == 3 ) {
                $stat['total_refunded'] += abs($item->price - $item->discount );
                if( $item->vat_applicable_to == 'customer' && $item->refunded['vat_refundable']) {
                    $stat['total_refunded'] += $vat;
                }
                if( $item->refunded['charge_refundable']) {
                    $stat['total_refunded'] += $charge;
                }
            }
            $stat['total_discounts'] += $item->discount;
            $mostVisited[$item->trip['route_id']]['route_name'] = $item->trip['route']['route_name'];
            $mostVisited[$item->trip['route_id']]['total'][] = $item->id;
        }
        return view('admin.customer.show', compact('customer', 'mostVisited', 'stat'))->withTitle( 'Customer: ' . $customer->name );
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $customer = User::where('type', 'customer')->findOrFail($id);

        return view('admin.customer.edit', compact('customer'))->withTitle('Edit Customer');
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
        $data = ['status' => false, 'label' => 'error', 'content' => 'Customer cannot be updated'];
        $validator = Validator::make( $request->all(), [
            'name' => 'bail|required|string',
            'email' => 'bail|required|email|unique:users,email,' . $id,
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|unique:users,mobile,' . $id
        ]);

        if( $validator->fails() == True ) {
            $data['content'] = $validator->errors()->first();
        } else {
            DB::beginTransaction();
            try{
                $customer = User::findOrFail( $id );
                $customer->name = $request->name;
                $customer->email = $request->email;
                $customer->mobile = $request->mobile;

                if( $customer->save() ) {
                    DB::commit();
                    $data['status'] = true;
                    $data['label'] = 'success';
                    $data['content'] = 'Customer successfully updated';
                }
            }  catch(\Exception $e) {
                DB::rollback();
                $data['content'] = $e->getMessage();
            }
        }

        if( $request->ajax() === True ) {
            return response()->json($data, $this->success );
        } else {
            if( $data['status'] == true ) {
                return redirect()->route('dashboard.customer.index')->with([
                    'message' => $data
                ]);
            } else {
                return redirect()->back()->with([
                    'message' => $data
                ])->withErrors($validator)->withInput();
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
        $query = User::where('status', 1);

        if( isset( $_GET['term'] ) ) {
            $term = $_GET['term'];
            if( is_numeric( $term ) ) {
                $query->Where('mobile', 'LIKE', '%' . $term . '%');
            } else {
                $query->Where('name', 'LIKE', '%' . $term . '%');
            }
        }

        $query = $query->paginate(15);

        $results = [];

        if( $query ) {
            foreach( $query as $q ) {
                $row['id'] = $q->id;
                $row['name'] = $q->name;

                array_push($results, $row);
            }
        }

        return response()->json(['results' => $results], 200);
    }

    public function action( Request $request )
    {
        if( isset( $request->action ) ) {
            call_user_func(array($this, $request->action), $request);
        }
    }

    private function resetPassword($request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Customer password cannot be reset'];
        $customer = User::findOrFail($request->id);
        $password = Str::random(8);
        $customer->password = Hash::make($password);
        if( $customer->save()) {
            sendSMS([
                'mobile' => $customer->mobile,
                'message' => 'Dear ' . $customer->name . ', Your password has been reset, Your new password is: ' . $password
            ]);
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Customer password has been reset and sent to customer through sms';
        }

            header('Content-Type: application/json');
            echo json_encode( $data );
            exit;
            return response()->json($data, $this->success);
    }

    private function active( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'User cannot activate'];
        $customer = User::findOrFail($request->id);
        $customer->status = 1;
        if( $customer->save()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Customer has been successfully activated';
        }

        if( $request->ajax() === True ) {
            header('Content-Type: application/json');
            echo json_encode( $data );
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function inactive( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Customer cannot inactivated'];
        $customer = User::findOrFail($request->id);
        $customer->status = 2;
        if( $customer->save()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Customer has been successfully activated';
        }

        if( $request->ajax() === True ) {
            DB::table('oauth_access_tokens')->where('user_id', $customer->id)->delete();
            header('Content-Type: application/json');
            echo json_encode( $data );
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function reactive( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Customer cannot re-activated'];
        $customer = User::findOrFail($request->id);
        $customer->status = 1;
        if( $customer->save()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Customer has been successfully re-activated';
        }

        if( $request->ajax() === True ) {
            header('Content-Type: application/json');
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
        $customer = User::findOrFail($request->id);
        if( $customer->delete()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Customer has been successfully deleted';
        }

        if( $request->ajax() === True ) {
            DB::table('oauth_access_tokens')->where('user_id', $customer->id)->delete();
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function restore( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'User cannot delete'];
        $customer = User::withTrashed()->find($request->id);
        if( $customer->restore()) {
            $data['label'] = 'success';
            $data['status'] = true;
            $data['content'] = 'Customer has been successfully restored';
        }

        if( $request->ajax() === True ) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
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

    public function bookings(Request $request, $id)
    {
        if( $request->ajax() === True ) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = Booking::with('bookingItems.item', 'bookingItems.launch', 'customer')->where('customer_id', $id);

            if( Auth::user()->type == 'merchant' ) {
                $user = Auth::user();
                if( $user->hasRole('merchant') ) {
                    $query->whereHas('bookingItems', function($query) use ($user) {
                        $query->whereHas('launch', function($q) use($user) {
                            $q->where('merchant_id', $user->id);
                        });
                    });
                } elseif($user->hasRole('supervisor')) {
                    //TODO Supervisor launch only
                    $query->whereHas('bookingItems', function($query) use ($user) {
                        $query->whereHas('launch', function($q) use($user) {
                            $q->where('merchant_id', $user->merchant_id);
                        });
                    });
                } else {
                    $query->whereHas('bookingItems', function($query) use ($user) {
                        $query->whereHas('launch', function($q) use($user) {
                            $q->where('merchant_id', $user->merchant_id);
                        });
                    });
                }
                $query->where('booking_party', 'merchant');
            }

            //filter by keyword
            if( isset( $_GET['keyword'] ) && $_GET['keyword'] != null ){
                $keyword = $_GET['keyword'];
                $query->where('id', 'LIKE', "%$keyword%");
                $query->orWhereHas('customer', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE',"%$keyword%");
                    $q->orWhere('mobile', 'LIKE', "%$keyword%");
                });
            }

            if( isset( $_GET['date_from'] ) && $_GET['date_from'] != null ){
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_from']);
                $query->where('booking_date', '>=', $date->format('Y-m-d'));
            }

            if( isset( $_GET['date_to'] ) && $_GET['date_to'] != null ){
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_to']);
                $query->where('booking_date', '<=', $date->format('Y-m-d'));
            }

            if( isset( $_GET['merchant'] ) && $_GET['merchant'] != null ){
                $merchant = ( int ) $_GET['merchant'];
                $query->whereHas('bookingItems', function($q) use($merchant) {
                    $q->whereHas('launch', function($q) use($merchant) {
                        $q->where('merchant_id', $merchant);
                    });
                });
            }

            if( isset( $_GET['status'] ) && $_GET['status'] != null ){
                $status = ( int ) $_GET['status'];
                if( $status == 9 ) {
                    $query->onlyTrashed();
                } else {
                    if( $status == 1 ) {
                        $query->whereIn('status', ['ACTIVE', 'COMPLETE']);
                    } elseif( $status == 2 ) {
                        $query->whereIn('status', ['CANCELLED', 'FAILED']);
                    } elseif( $status == 0 ) {
                        $query->where('status', 'PENDING');
                    }
                }
            }

            // //filter by mobile
            // if( isset( $_GET['mobile'] ) && $_GET['mobile'] != null ) {
            //     $mobile = $_GET['mobile'];

            //     $query->where( function ($q) use ($mobile) {
            //         $q->orWhereRaw("lower(mobile) LIKE '%" . strtolower($mobile) . "'");
            //     });
            // }

            $total = $query->count();

            $query->offset($start);
            if( $limit < 0 ) {
                $limit = $total;
            }
            $query->limit($limit);
            $query->orderBy($column, $order);
            $bookings = $query->get();

            //sanitize data
            $returnArr = array();
            if( $bookings ) {
                foreach( $bookings as $booking ) {
                    // dd( $booking );
                    $row['id'] = $booking->id;
                    $row['customer_name'] = ( $booking->customer ) ? $booking->customer['name'] : '';
                    $row['customer_email'] = ( $booking->customer ) ? $booking->customer['email'] : '';
                    $row['customer_mobile'] = ( $booking->customer ) ? $booking->customer['mobile'] : '';
                    $row['created_at'] = date('d M, Y h:i A', strtotime( $booking->created_at ) );
                    $row['total'] = number_format($booking->total_amount, 2);
                    $row['discount'] = number_format($booking->total_discount, 2);
                    $row['vat_total'] = number_format($booking->vat_total, 2);
                    $row['charge_total'] = number_format($booking->charge_total, 2);
                    $row['bank_charge'] = number_format(abs($booking->payment['paid_amount'] - $booking->payment['store_amount']), 2);
                    $row['subtotal'] = number_format(( $booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount), 2);
                    $row['status'] = $booking->status;
                    $row['booking_items'] = $booking->bookingItems->count();
                    $row['cancelled_items'] = 0;
                    $row['honorium_charge'] = 0;
                    if( $booking->bookingItems ) {
                        foreach( $booking->bookingItems as $item ) {
                            if( $item['status'] == 1 && $item['booking_type'] != 'deck' ) {
                                if( $item['is_honorium'] ) {
                                    $row['honorium_charge'] += abs($item['price'] * ($item['honorium_charge']/100));
                                }
                            }

                            if( $item['status'] == 2 ) {
                                $row['cancelled_items'] += 1;
                            }
                        }
                    }
                    $row['honorium_charge'] = number_format($row['honorium_charge'], 2);
                    array_push( $returnArr, $row );
                }
            }

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $returnArr
            ];

            return response()->json( $data );
        }
    }
}
