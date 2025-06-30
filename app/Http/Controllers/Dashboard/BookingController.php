<?php

namespace App\Http\Controllers\Dashboard;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\BookingBatchCancelRequest;
use App\Http\Requests\PaymentConfirmRequest;
use App\Services\BookingService;
use Modules\Booking\Jobs\BookingCreatedSmsJob;
use Modules\Booking\Jobs\BookingInvoiceSendToEmailJob;
use PDF;

class BookingController extends Controller
{
    private $bookings;

    public function __construct(BookingService $bookings)
    {
        $this->bookings = $bookings;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $service_type = (isset($_GET['type'])) ? $_GET['type'] : 'launch';
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = Booking::with(['bookingItems.item', 'bookingItems.launch', 'customer', 'payment']);

            if (Auth::user()->type == 'merchant') {
                $user = Auth::user();
                if ($user->hasRole('merchant')) {
                    $query->whereHas('bookingItems', function ($query) use ($user) {
                        $query->whereHas('launch', function ($q) use ($user) {
                            $q->where('merchant_id', $user->id);
                        });
                    });
                } elseif ($user->hasRole('supervisor')) {
                    //TODO Supervisor launch only
                    $query->whereHas('bookingItems', function ($query) use ($user) {
                        $query->whereHas('launch', function ($q) use ($user) {
                            $q->where('merchant_id', $user->merchant_id);
                        });
                    });
                } else {
                    $query->whereHas('bookingItems', function ($query) use ($user) {
                        $query->whereHas('launch', function ($q) use ($user) {
                            $q->where('merchant_id', $user->merchant_id);
                        });
                    });
                }
                $query->where('booking_party', 'merchant');
            }

            //filter by keyword
            if (isset($_GET['keyword']) && $_GET['keyword'] != null) {
                $keyword = $_GET['keyword'];
                $query->where('id', 'LIKE', "%$keyword%");
                $query->orWhereHas('customer', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE', "%$keyword%");
                    $q->orWhere('mobile', 'LIKE', "%$keyword%");
                });
            }

            if (isset($_GET['date_from']) && $_GET['date_from'] != null) {
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_from']);
                $query->where('booking_date', '>=', $date->format('Y-m-d'));
            }

            if (isset($_GET['date_to']) && $_GET['date_to'] != null) {
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_to']);
                $query->where('booking_date', '<=', $date->format('Y-m-d'));
            }

            if (isset($_GET['journey_date']) && $_GET['journey_date'] != null) {
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['journey_date']);
                $query->whereHas('bookingItems', function($q) use($date) {
                    $q->where('trip_date', '=', $date->format('Y-m-d'));
                });
            }

            if (isset($_GET['merchant']) && $_GET['merchant'] != null) {
                $merchant = ( int )$_GET['merchant'];
                $query->whereHas('bookingItems', function ($q) use ($merchant) {
                    $q->whereHas('launch', function ($q) use ($merchant) {
                        $q->where('merchant_id', $merchant);
                    });
                });
            }

            if (isset($_GET['vehicle']) && $_GET['vehicle'] != null) {
                $vehicle = ( int )$_GET['vehicle'];
                $query->whereHas('bookingItems', function ($q) use ($vehicle) {
                    $q->where('vehicle_id', $vehicle);
                });
            }

            if (isset($_GET['service_type']) && $_GET['service_type'] != 'null') {
                $query->whereHas('bookingItems', function ($q) {
                    $q->whereHas('launch', function ($q) {
                        $q->where('vehicle_type', $_GET['service_type']);
                    });
                });
            }

            if (isset($_GET['status']) && $_GET['status'] != null) {
                $status = ( int )$_GET['status'];
                if ($status == 9) {
                    $query->onlyTrashed();
                } else {
                    if ($status === 1) {
                        $query->whereIn('status', ['ACTIVE', 'COMPLETE'])
                            ->whereHas('payment', function ($q) {
                                $q->where('dues', 0);
                            });
                    } elseif ($status === 2) {
                        $query->whereIn('status', ['CANCELLED', 'FAILED']);
                    } elseif ($status === 0) {
                        $query->whereIn('status', ['PENDING']);
                    } elseif ($status === 3) {
                        $query->where('status', 'ADVANCE');
                    }
                }
            }

            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            $query->orderBy('created_at', 'desc');
            $bookings = $query->get();

            //sanitize data
            $returnArr = array();
            if ($bookings) {
                foreach ($bookings as $booking) {
                    $row['id'] = $booking->id;
                    $row['customer_id'] = $booking->customer_id;
                    $row['customer_name'] = ($booking->customer) ? $booking->customer['name'] : '';
                    $row['customer_email'] = ($booking->customer) ? $booking->customer['email'] : '';
                    $row['customer_mobile'] = ($booking->customer) ? $booking->customer['mobile'] : '';
                    $row['created_at'] = date('d M, Y h:i A', strtotime($booking->created_at));
                    $row['total'] = number_format($booking->total_amount, 2);
                    $row['discount'] = number_format($booking->total_discount, 2);
                    $row['vat_total'] = number_format($booking->vat_total, 2);
                    $row['charge_total'] = number_format($booking->charge_total, 2);
                    $row['subtotal'] = number_format(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount), 2);
                    $row['payment_status'] = ($booking->payment) ? $booking->payment['status'] : 'pending';
                    $row['status'] = $booking->status;
                    $row['booking_items'] = $booking->bookingItems->count();
                    $row['cancelled_items'] = collect($booking->bookingItems)->where('status', 2)->count();
                    $row['honorium_charge'] = 0;
                    $row['platform'] = $booking->platform;
                    $row['paid_amount'] = round($booking->payment['paid_amount'], 2);
                    $row['dues'] = round($booking->payment['dues'], 2);
                    $row['journey_dates'] = [];
                    if ($booking->bookingItems) {
                        foreach ($booking->bookingItems as $item) {
                            if ($item['status'] == 1 && $item['booking_type'] != 'deck') {
                                if ($item['is_honorium']) {
                                    $row['honorium_charge'] += abs($item['price'] * ($item['honorium_charge'] / 100));
                                }
                            }
                            if ($item['trip']) {
                                array_push($row['journey_dates'], date('d/m/Y h:i a', strtotime($item['trip']['leaving_at'])));
                            }
                        }
                    }
                    $row['honorium_charge'] = number_format($row['honorium_charge'], 2);
                    $row['journey_dates'] = implode(', ', array_unique($row['journey_dates']));
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

        return view('admin.booking.index', compact('service_type'))->withTitle(ucfirst($service_type) . ' bookings');
    }

    /**
     * Show the booking summary.
     *
     * @return \Illuminate\Http\Response
     */
    public function summary(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Booking not found'];
        $validator = Validator::make($request->all(), [
            'id' => 'bail|required|numeric|exists:bookings'
        ]);

        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
        } else {
            $booking = Booking::find($request->id);
            if ($booking) {
                $data['stat'] = [
                    'booking_id' => $booking->id,
                    'booking_date' => date('d/m/Y h:i a', strtotime($booking->created_at)),
                    'customer_name' => $booking->customer->name,
                    'customer_mobile' => $booking->customer->mobile,
                    'booking_total' => $booking->total_amount,
                    'booking_discount' => $booking->total_discount,
                    'booking_payable' => $booking->total_payable,
                    'booking_vat' => $booking->vat_total,
                    'booking_charge' => $booking->charge_total,
                    'booking_store_amount' => $booking->payment->store_amount,
                    'booking_bank_charge' => abs($booking->total_payable - $booking->payment->store_amount),
                    'cancelled_items' => 0,
                    'cancelled_amount' => 0
                ];
                $items = collect($booking->cancelledItems);
                if ($booking->cancelled && $items) {
                    foreach ($booking->cancelled as $cancelled) {
                        $cancelledIds = explode(',', $cancelled->items);
                        $cancelledItems = $items->whereIn('id', $cancelledIds)->all();
                        foreach ($cancelledItems as $item) {
                            $data['stat']['cancelled_items'] += 1;
                            $data['stat']['cancelled_amount'] += abs($item->price - $item->discount);
                            if ($cancelled->vat_refundable) {
                                $data['stat']['cancelled_amount'] += abs(($item->price) * ($cancelled->vat_amount / 100));
                            }
                            if ($cancelled->charge_refundable) {
                                $data['stat']['cancelled_amount'] += abs(($item->price) * ($cancelled->charge_amount / 100));
                            }
                        }
                    }
                }
                $data['status'] = true;
                $data['label'] = 'success';
            } else {
                $data['content'] = 'Booking not found';
            }
        }

        return response()->json($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $booking = Booking::with(['bookingItems.item', 'bookingItems.trip.startingPoint', 'bookingItems.trip.endingPoint', 'bookingItems.trip.launch', 'customer', 'payment', 'cancelationRequests'])->findOrFail($id);
        return view('admin.booking.show', compact('booking'))->withTitle('Booking : #' . $booking->id);
    }

    public function batchCancel(BookingBatchCancelRequest $request)
    {
        $data = ['status' => false, 'content' => 'Error occured!', 'label' => 'error'];
        try {
            DB::transaction(function () use ($request, &$data) {
                $bookings = Booking::with(['bookingItems', 'cancelationRequests'])->whereIn('id', explode(',', $request->ids))->get();
                $bookings->each(function ($item, $key) {
                    if (!$item->cancelationRequests) {
                        $this->bookings->cancelBooking($item, true);
                    }
                });
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = 'Your selected items has been sent to cancel list';
            }, 2);
        } catch (\Exception $exception) {
            $data['content'] = $exception->getMessage();
        }

        return response()->json($data);
    }

    /**
     * Display the specified invoice.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function invoice($id)
    {
        $booking = Booking::with(['bookingItems.item', 'bookingItems.trip.startingPoint', 'bookingItems.trip.endingPoint', 'bookingItems.trip.launch', 'customer', 'payment'])->findOrFail($id);
        // dd( $booking );
        return view('admin.booking.invoice', compact('booking'))->withTitle('Invoice : #' . $booking->id);
    }

    public function ViewInvoice($id)
    {
        $booking = Booking::with(['bookingItems.trip.route', 'cancellations', 'bookingItems.item.cabinType', 'bookingItems.trip.launch', 'payment', 'customer'])->findOrFail($id);
        $responseArr = [];
        if ($booking) {
            $responseArr['id'] = $booking->id;
            $responseArr['pnr'] = $booking->id;
            $responseArr['qr'] = asset('qrs/' . $booking->id . '.png');
            $responseArr['booking_date'] = date('Y-m-d H:i:s', strtotime($booking->created_at));
            $responseArr['booking_date_formated'] = date('d M, Y h:i A', strtotime($booking->created_at));
            $responseArr['payment_status'] = $booking->payment['status'];
            $responseArr['total_amount'] = $booking->total_amount;
            $responseArr['total_discount'] = $booking->total_discount;
            $responseArr['vat_amount'] = $booking->vat_amount;
            $responseArr['vat_total'] = $booking->vat_total;
            $responseArr['charge_amount'] = $booking->charge_amount;
            $responseArr['charge_total'] = $booking->charge_total;
            $responseArr['total_payable'] = number_format(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount), 2);
            $responseArr['payment'] = $booking->payment;
            $responseArr['customer'] = $booking->customer;
            $responseArr['items'] = [];

            $cancellations = [];
            if ($booking->cancellations) {
                foreach ($booking->cancellations as $cancellation) {
                    $cancellations = array_merge_recursive($cancellations, explode(',', $cancellation->items));
                }
            }

            foreach ($booking->bookingItems as $item) {
                $row = [
                    'id' => $item['id'],
                    'cabin_no' => ($item['item']) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                    'cabin_type' => $item['booking_type'],
                    'price' => $item['price'],
                    'discount' => $item['discount'],
                    'is_ac' => ($item['booking_type'] != 'deck') ? $item['item']['cabinType']['is_ac'] : 0,
                    'vehicle_name' => $item['trip']['launch']['name'],
                    'route_name' => $item['trip']['route']['route_name'],
                    'schedule_date' => date('d F Y', strtotime($item['trip_date'])),
                    'leaving_time' => $item['trip']['leaving_at'],
                    'leaving_time_formated' => date('h:i A', strtotime($item['trip']['leaving_at'])),
                    'boarding_point' => json_decode($item['boarding_point']),
                    'passenger' => json_decode($item['passenger']),
                    'from' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                    'to' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                    'cancellable' => ($item['trip_date'] >= date('Y-m-d')) ? ((in_array($item['id'], $cancellations)) ? false : true) : false,
                    'status' => $item['status']
                ];
                if ($item['trip']['schedule_type'] == 'reverse') {
                    $row['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                }
                array_push($responseArr['items'], $row);
            }

            $responseArr['items'] = ($responseArr['items']) ? _my_group_by_old($responseArr['items'], 'schedule_date') : [];

            $tickets = [];
            foreach ($responseArr['items'] as $key => $items) {
                array_push($tickets, ['date' => $key, 'tickets' => $items]);
            }

            $responseArr['items'] = $tickets;
        }
        $invoice = $responseArr;
        return view('emails.invoice', compact('invoice'));
    }

    public function sendInvoice(Request $request)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Cannot send email'];

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:bookings,id',
            'type' => 'bail|required|string'
        ]);

        if ($validator->fails()) {
            $data['content'] = $validator->errors()->first();
        } else {
            //sending email to customer with attachment
            $booking = Booking::findOrFail($request->id);
            if ($request->type == 'email') {
                try {
                    if ($booking->customer->email) {
                        $this->dispatch(new BookingInvoiceSendToEmailJob($booking));
                        $data['label'] = 'success';
                        $data['status'] = true;
                        $data['content'] = 'Invoice successfully sent to customers email';
                    } else {
                        $data['content'] = 'Customer has no valid email address';
                    }
                } catch (\Exception $e) {
                    $data['content'] = $e->getMessage();
                }
            } else {
                $this->dispatch(new BookingCreatedSmsJob($booking));
                $data['status'] = true;
                $data['label'] = 'success';
                $data['content'] = 'Invoice successfully sent to customer mobile number';
            }
        }

        if ($request->ajax() === True) {
            header('Content-Type: application/json');
            return response()->json($data);
        } else {
            return redirect()->back()->with([
                'message' => $data
            ]);
        }
    }

    /**
     * Payment confirm for failed booking
     *
     * @param PaymentConfirmRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentConfirm(PaymentConfirmRequest $request, $id)
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Booking cannot confirmed'];
        try {
            $request->merge([
                'status' => AppConst::BOOKING_COMPLETE,
                'user_id' => auth()->user()->id
            ]);
            DB::transaction(function () use ($request, $id) {
                $this->bookings->confirmFailedBooking($request->all(), $id);
            }, 2);
            $data = ['status' => true, 'label' => 'success', 'content' => 'Booking successfully confirmed'];
        } catch (\Exception $exception) {
            $data['content'] = $exception->getMessage();
        }

        return response()->json($data);
    }
}
