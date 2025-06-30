<?php

namespace App\Http\Controllers\Dashboard;

use App\Constants\AppConst;
use App\Models\BookingCancellation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\CancellationCreateRequest;
use App\Notifications\CancellationRequestDeclined;
use App\Notifications\CancellationRequestProcessing;
use App\Events\NewNotification;
use Illuminate\Support\Facades\DB;
use App\Services\CalculationService;
use App\Services\CancellationService;

class BookingCancellationController extends Controller
{
    protected $success = 200;
    protected $calculation;
    protected $cancellationService;

    public function __construct( CalculationService $calculationService, CancellationService $cancellationService)
    {
        $this->calculation = $calculationService;
        $this->cancellationService = $cancellationService;
    }

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

            $query = BookingCancellation::with(['booking', 'customer', 'bookingItems.launch']);

            //filter by keyword
            if( isset( $_GET['keyword'] ) && $_GET['keyword'] != null ){
                $keyword = $_GET['keyword'];
                $query->orWhereHas('customer', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE',"%$keyword%");
                    $q->orWhere('mobile', 'LIKE',"%$keyword%");
                    $q->orWhere('email', 'LIKE',"%$keyword%");
                });
            }
            if( isset( $_GET['merchant'] ) && $_GET['merchant'] != null ){
                $merchant = ( int ) $_GET['merchant'];
                $query->WhereHas('bookingItems', function ($q) use ($merchant) {
                    $q->whereHas('launch', function($q) use ($merchant) {
                        $q->where('route_id', $merchant);
                    });
                });
            }

            if( isset( $_GET['date_from'] ) && $_GET['date_from'] != null ){
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_from']);
                $query->where('created_at', '>=', $date->format('Y-m-d 00:00:00'));
            }

            if( isset( $_GET['date_to'] ) && $_GET['date_to'] != null ){
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_to']);
                $query->where('created_at', '<=', $date->format('Y-m-d 23:59:59'));
            }

            if(isset($_GET['service_type']) && $_GET['service_type'] != 'null') {
                $query->whereHas('bookingItems', function($q) {
                    $q->whereHas('launch', function($q) {
                        $q->where('vehicle_type', $_GET['service_type']);
                    });
                });
            }

            if( isset( $_GET['status'] ) && $_GET['status'] != null ){
                $status = ( int ) $_GET['status'];
                $query->where('status', $status);
            }

            $total = $query->count();

            $query->offset($start);
            if( $limit < 0 ) {
                $limit = $total;
            }
            $query->limit($limit);
            // $query->orderBy($column, $order);
            $query->orderBy('created_at', 'desc');
            $cancellations = $query->get();

            //sanitize data
            $returnArr = array();
            if( $cancellations ) {
                foreach( $cancellations as $cancellation ) {

                    $items = explode(',', $cancellation->items);
                    $row['id'] = $cancellation->id;
                    $row['booking_id'] = $cancellation->booking_id;
                    $row['customer_id'] = $cancellation->customer_id;
                    $row['customer_name'] = $cancellation->customer['name'];
                    $row['customer_email'] = $cancellation->customer['email'];
                    $row['customer_mobile'] = $cancellation->customer['mobile'];
                    $row['booking_date'] = date('d M, Y h:i A', strtotime( $cancellation->booking['created_at'] ) );
                    $row['status'] = $cancellation->status;
                    $row['items'] = count( $items );
                    $row['total'] = $cancellation->booking['total_payable'];
                    $row['cancelled_amount'] = 0;
                    $cancelledItems = explode(',', $cancellation->items);
                    foreach( $cancellation->bookingItems as $item ) {
                        if( in_array($item['id'], $cancelledItems)) {
                            $row['cancelled_amount'] += abs($item['price'] - $item['discount']);
                            if($cancellation->vat_refundable) {
                                $row['cancelled_amount'] += ( $item['vat_applicable_to'] == 'customer') ? abs($item['price']*($item['vat_amount'] / 100)) : 0;
                            }
                            if( $cancellation->charge_refundable ) {
                                $row['cancelled_amount'] += abs($item['price']*($item['charge_amount'] / 100));
                            }
                        }
                    }
                    $row['created_at'] = date('d/m/Y h:i a', strtotime($cancellation->created_at));
                    $row['type'] = ucfirst($cancellation->type);
                    array_push( $returnArr, $row );
                }
            }

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $returnArr
            ];

            return response()->json( $data, $this->success);
        }

        $service_type = (isset($_GET['type'])) ? $_GET['type'] : 'launch';
        return view('admin.booking.cancellation.index', compact('service_type'))->withTitle(ucfirst($service_type) . ' booking cancellations');
    }

    public function action( Request $request )
    {
        $customer_id = $request->id;
        if( isset( $request->action ) ) {
            return call_user_func(array($this, $request->action), $request);
        }
    }

    private function approve( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot approve request.'];
        $cancellation = BookingCancellation::findOrFail($request->id);
        DB::beginTransaction();
        try{
            if( $cancellation->update(['status' => AppConst::CANCELLATION_APPROVED])) {
//                event(new NewNotification($cancellation->customer, ['type' => 'Approved', 'message' => "Your booking cancellation request approved"]));
                DB::commit();
                $data['label'] = 'success';
                $data['status'] = true;
                $data['content'] = 'Cancellation request successfully approved';
            }
        } catch( \Exception $e ) {
            $data['content'] = $e->getMessage();
            DB::rollback();
        }

        if( $request->ajax() === True ) {
            header('Content-Type: application/json');
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function decline( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot decline request.'];
        $cancellation = BookingCancellation::findOrFail($request->id);
        DB::beginTransaction();
        try{
            if( $cancellation->update(['status' => AppConst::CANCELLATION_REJECTED])) {

                DB::commit();
                $data['label'] = 'success';
                $data['status'] = true;
                $data['content'] = 'Cancellation request successfully declined';

                $cancellation->customer->notify(new CancellationRequestDeclined($cancellation));
                event(new NewNotification($cancellation->customer, ['type' => 'Declined', 'message' => "Your booking cancellation request declined"]));
            }
        } catch( \Exception $e ) {
            DB::rollback();
        }

        if( $request->ajax() === True ) {
            header('Content-Type: application/json');
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function processing( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot decline request.'];
        $cancellation = BookingCancellation::findOrFail($request->id);
        DB::beginTransaction();
        try{
            if( $cancellation->update(['status' => AppConst::CANCELLATION_PROCESSING])) {

                DB::commit();
                $data['label'] = 'success';
                $data['status'] = true;
                $data['content'] = 'Your Refund request placed successfully';

                $cancellation->customer->notify(new CancellationRequestProcessing($cancellation));
                event(new NewNotification($cancellation->customer, ['type' => 'Processing', 'message' => "Your booking cancellation refund processing"]));
            }
        } catch( \Exception $e ) {
            DB::rollback();
        }

        if( $request->ajax() === True ) {
            header('Content-Type: application/json');
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    private function refunded( $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot decline request.'];
        $cancellation = BookingCancellation::findOrFail($request->id);
        DB::beginTransaction();
        try{
            if( $cancellation->update(['status' => AppConst::CANCELLATION_REFUNDED])) {
                DB::commit();
                $data['label'] = 'success';
                $data['status'] = true;
                $data['content'] = 'Cancellation request successfully refunded';
                event(new NewNotification($cancellation->customer, ['type' => 'Refunded', 'message' => "Your booking cancellation amount refunded"]));
            }
        } catch( \Exception $e ) {
            DB::rollback();
        }

        if( $request->ajax() === True ) {
            header('Content-Type: application/json');
            return response()->json($data, $this->success);
        }
        return redirect()->back()->with([
            'message' => $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CancellationCreateRequest $request)
    {
        $data = ['success' => false, 'label' => 'error', 'content' => 'Your cancellation request failed'];
        try{
            DB::transaction(function() use($request, &$data) {
                $this->cancellationService->cancelBooking($request->validated());
                $data['success'] = true;
                $data['label'] = 'success';
                $data['content'] = 'Your cancellation request success';
            }, 2);
        } catch( \Exception $e ) {
            $data['content'] = $e->getMessage();
        }
        if( $request->ajax() === True || request()->wantsJson()) {
            return response()->json($data, $this->success );
        } else {
            return redirect()->back()->withMessage($data);
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
        $cancellation = BookingCancellation::with(['bookingItems.item', 'bookingItems.trip.startingPoint', 'bookingItems.trip.endingPoint', 'bookingItems.trip.launch', 'customer', 'payment'])
        ->findOrFail($id);

        return view('admin.booking.cancellation.show', compact('cancellation'))->withTitle('Booking : #' . $cancellation->booking_id . ' cancellation overview');
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
