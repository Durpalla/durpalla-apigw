<?php
namespace App\Http\Controllers\Dashboard;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Merchant;
use App\Services\BalanceService;
use App\Services\DashboardService;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use PDF;

class DashboardController extends Controller
{
    private $dashboard;
    public function __construct(
        DashboardService $dashboardService,
        BalanceService $balanceService
    )
    {
        $this->dashboard = $dashboardService;
        $this->balanceService = $balanceService;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index( Request $request)
    {
//        $this->dispatch(new OTPCodeSendingJob('8801776273545', '55555'));
//        $customers = User::with('roles')->where('type', 'customer')->limit(5)->get();
//        $customers->each(function($item, $key) {
//            $item->assignRole('customer');
//        });
//        $startTime = date('Y-m-d H:i:s', strtotime('-12 hour'));
//        $endTime = date('Y-m-d H:i:s', strtotime('+2 hour'));
//        dd( $startTime . ' ---- ' . $endTime );
//        $html_content = '<h1>Generate a PDF using TCPDF in laravel </h1>
//        		<h4>by<br/>Learn Infinity</h4>';



//        $attachement = PDF::Output('SamplePDF.pdf');
//        die();
        // $invoice = [];
//         $adminRole = Role::where('name', 'admin');
//        $merchantUser = User::where('email', 'merchant.jolzan@gmail.com')->first();
//         $merchantRole = Role::where('name', 'supervisor');
//         $merchant = User::with('roles')->where('mobile', '01965824332')->first();
//         $merchant->merchant_id = $merchantUser->id;
//         $merchant->type = 'merchant';
//         $merchant->save();
//         dd($merchant);
//         $merchant->removeRole('customer);
//         $merchant->assignRole('supervisor');

        // $pdf = PDF::loadView('emails.invoice', $invoice);
        // return $pdf->download('invoice.pdf');
        $user = User::findOrFail(Auth::user()->id);
//         $invoice = ['name' => 'hello'];
//        $pdf->WriteHTML(view('emails.receipt_pdf', $receipt_data));
//        $attachment = $pdf->Output('Receipt - '.date("M-d-Y").'.pdf','E');

//        PDF::SetTitle('Sample PDF');
//        PDF::AddPage();
//        PDF::writeHTML(view('emails.default', $invoice)->render());
//        $attachement = PDF::Output('Receipt - '.date("M-d-Y").'.pdf','S');
//         $pdf = PDF::loadView('emails.default', $invoice);
//         try{
//             Mail::send('emails.default', $user->toArray(), function($message)use($user, $attachement) {
//             $message->to('jewelrana.dev@gmail.com', 'Jewel Rana')
//             ->subject('Launch ticket purchase')
//              ->attachData($attachement, "invoice.pdf");
//             });
//             // Mail::to('jewelrana.dev@gmail.com')->send(new TicketPurchased($pdf, $user->toArray()));
//         } catch( Exception $e ) {
//             Log::debug($e->getMessage() );
//         }
        // {!! QrCode::size(250)->generate('ItSolutionStuff.com'); !!}
        switch ( $user->type ) {

            case 'admin':
                return $this->initAdmin( $request );
                break;

            case 'merchant':
                return $this->initMerchant( $request );
                break;

            default:
                return $this->initAdmin( $request );
                break;
        }
    }

    private function initAdmin( $request )
    {
        $month = ( isset( $_GET['month'] ) ) ? date('Y-m', strtotime($_GET['month'])) : date('Y-m');
        $type = ( isset( $_GET['type'] ) ) ? $_GET['type'] : 'jolzan';
        $user = Auth::user();
        $query = Booking::with(['bookingConfirmed.launch', 'bookingConfirmed.mappings', 'cancelled'])
            ->with([
                'bookingConfirmed' => function($q) use($month, $user) {
                    $q->whereBetween('booking_date', [date('Y-m-01', strtotime($month)), date('Y-m-t', strtotime($month))]);
                }
            ]);
//         ->where('booking_party', $type)
        $bookings = $query->get();
        $stats = [
            'total_merchants' => $this->dashboard->merchantCount(),
            'total_vehicles' => $this->dashboard->launchCount(),
            'total_cabins' => $this->dashboard->cabinsCount(),
            'total_seats' => $this->dashboard->seatsCount(),
            'total_decks' => $this->dashboard->decksCount(),
            'total_bookings' => 0,
            'total_booking_amount' => 0,
            'total_booking_vat' => 0,
            'total_cabin_booking_amount' => 0,
            'total_cabin_booking_vat' => 0,
            'total_seat_booking_amount' => 0,
            'total_seat_booking_vat' => 0,
            'total_deck_booking_amount' => 0,
            'total_deck_booking_vat' => 0,
            'total_charge_amount' => 0,
            'total_vat_amount' => 0,
            'total_customer_vat_amount' => 0,
            'total_discount_amount' => 0,
            'total_cabin_bookings' => 0,
            'total_seat_bookings' => 0,
            'total_deck_bookings' => 0,
            'bookingGraphs' => [],
            'passengers' => [
                'jolzan' => 0,
                'merchant' => 0,
                'other' => 0
            ],
            'currentTrips' => $this->dashboard->currentTrips()
        ];

        $monthDays = date('t', strtotime($month));
        for( $i = 1; $i <= $monthDays; $i++ ) {
            $stats['bookingGraphs'][date('d/m/Y', strtotime(date($month . '-' . $i)))] = [
                'cabin' => [
                    'count' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null,
                    'amount' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null
                ],
                'seat' => [
                    'count' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null,
                    'amount' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null
                ],
                'deck' => [
                    'count' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null,
                    'amount' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null
                ]
            ];
        }

        // dd( $stats );
        $groupBookings = [];
        $cancellations = [];
        if( $bookings ) {
            foreach( $bookings as $booking ) {
                if( $booking->bookingConfirmed->count() > 0 ) {
                    foreach( $booking->bookingConfirmed as $item ) {
                        $passenger = json_decode( $item['passenger'] );
                        $stats['passengers'][strtolower($booking->booking_party)] += $passenger->person;
                    }
                }
                if( $booking->booking_party == strtolower($type) ) {
                    if( $booking->bookingConfirmed->count() > 0 ) {
                        $groupBookings[$booking->booking_date][] = [
                            'date' => date('d/m/Y', strtotime($booking->booking_date)),
                            'items' => $booking->bookingConfirmed->count(),
                            'subtotal' => $booking->total_amount,
                            'total' => $booking->total_payable,
                            'vat' => $booking->vat_total,
                            'charge' => $booking->charge_total,
                            'discount' => $booking->total_discount
                        ];
                        foreach ( $booking->bookingConfirmed as $item ) {
                            $passenger = json_decode( $item['passenger'] );
                            // $mappings = new Collection($item['mappings']);
                            // $mapping = $mappings->where('cabin_id', $item['cabin_id'])->first();
                            // if( $mapping ) {
                            //     $stats['passengers'][$mapping->ownership] += ($passenger->person) ? abs($passenger->person) : 1;
                            // }
                            $vat = abs(($item['price'] * ($item['vat_amount']/100)));
                            $charge = abs(($item['price'] * ($item['charge_amount']/100)));
                            $customer_vat = 0;
                            if( $item['vat_applicable_to'] == 'customer' ) {
                                $customer_vat = $vat;
                            }
                            if( $item['booking_type'] == 'deck' ) {
                                $stats['total_deck_bookings'] += ( $passenger ) ? $passenger->person : 1;
                                $stats['total_bookings'] += ( $passenger ) ? $passenger->person : 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['deck']['count'] += ( $passenger ) ? $passenger->person : 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['deck']['amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                                $stats['total_deck_booking_amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                            } elseif( $item['booking_type'] == 'seat') {
                                $stats['total_seat_bookings'] += 1;
                                $stats['total_bookings'] += 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['seat']['count'] += 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['seat']['amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                                $stats['total_seat_booking_amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                            } else {
                                $stats['total_cabin_bookings'] += 1;
                                $stats['total_bookings'] += 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['cabin']['count'] += 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['cabin']['amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                                $stats['total_cabin_booking_amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                            }

                            $stats['total_booking_amount'] += abs($item['price'] + $charge - $item['discount']);
                            $stats['total_discount_amount'] += abs($item['discount']);
                            $stats['total_charge_amount'] += $charge;
                            $stats['total_vat_amount'] += $vat;
                            if( $item['vat_applicable_to'] == 'customer') {
                                $stats['total_customer_vat_amount'] += $customer_vat;
                                $stats['total_booking_amount'] += abs($vat);
                            }
                        }

                        if( $booking->cancelled ) {
                            foreach( $booking->cancelled as $cancellation ) {
                                $total = 0;
                                $vat = 0;
                                $customer_vat = 0;
                                $charge = 0;
                                $discount = 0;
                                $date = date('d/m/Y', strtotime($cancellation->created_at));
                                if( $cancellation['bookingItems'] ) {
                                    foreach( $cancellation['bookingItems'] as $item ) {
                                        $total += abs($item['price']);
                                        $vat += abs($item['price']*($item['vat_amount']/100));
                                        if( $item['vat_applicable_to'] == 'customer' ) {
                                            $customer_vat += $vat;
                                        }
                                        $charge += abs($item['price']*($item['charge_amount']/100));
                                        $discount += $item['discount'];
                                    }
                                }
                                $cancellations[$date][] = [
                                    'total' => ($total + $customer_vat + $charge - $discount),
                                    'vat' => $vat,
                                    'customer_vat' => $customer_vat,
                                    'charge' => $charge,
                                    'discount' => $discount
                                ];
                            }
                        }
                    }
                    if($booking->status === AppConst::BOOKING_CANCELLED) {
                        foreach( $booking->cancelled as $cancellation ) {
                            $total = 0;
                            $vat = 0;
                            $customer_vat = 0;
                            $charge = 0;
                            $discount = 0;
                            $date = date('d/m/Y', strtotime($cancellation->created_at));
                            if( $cancellation['bookingItems'] ) {
                                foreach( $cancellation['bookingItems'] as $item ) {
                                    $total += abs($item['price']);
                                    $vat += abs($item['price']*($item['vat_amount']/100));
                                    if( $item['vat_applicable_to'] == 'customer' ) {
                                        $customer_vat += $vat;
                                    }
                                    $charge += abs($item['price']*($item['charge_amount']/100));
                                    $discount += $item['discount'];
                                }
                            }
                            $cancellations[$date][] = [
                                'total' => ($total + $customer_vat + $charge - $discount),
                                'vat' => $vat,
                                'customer_vat' => $customer_vat,
                                'charge' => $charge,
                                'discount' => $discount
                            ];
                        }
                    }
                }
            }
        }

        if( $request->ajax() == True ) {
            return response()->json($stats);
        }

        return view('admin.home.index', compact('stats', 'groupBookings', 'cancellations', 'month', 'type'))->withTitle('Dashboard');
    }

    private function initMerchant( $request )
    {
        $month = ( isset( $_GET['month'] ) ) ? date('Y-m', strtotime($_GET['month'])) : date('Y-m');
        $type = ( isset( $_GET['type'] ) ) ? $_GET['type'] : 'merchant';
        $user = Auth::user();
        $merchant_id = $user->merchant_id;
        if( $user->hasRole('merchant') ) {
            $merchant_id = $user->id;
        }
        $merchant = Merchant::with(['vehicles' => function($q) {
            // $q->where('status', 1);
        }, 'upcomingSchedules' => function($q) use ($month) {
            $q->whereBetween('schedule_date', [date('Y-m-01', strtotime($month)), date('Y-m-t', strtotime($month))]);
        }])->where('user_id', $merchant_id)->first();
        $query = Booking::with(['bookingConfirmed.launch', 'bookingConfirmed.mappings', 'cancelled'])
        ->with([
            'bookingConfirmed' => function($q) use($month, $merchant, $user) {
                $q->whereBetween('booking_date', [date('Y-m-01', strtotime($month)), date('Y-m-t', strtotime($month))]);
                $q->whereHas('launch', function($q) use($merchant) {
                    $q->where('merchant_id', $merchant->user_id);
                });

                if( $user->hasRole('supervisor') ) {
                    $launchIds = [];
                    if( $user->vehicles ) {
                        foreach( $user->vehicles as $launch ) {
                            array_push($launchIds, $launch->vehicle_id);
                        }
                    }
                    $q->whereIn('vehicle_id', $launchIds);
                }
            }
        ]);
        // ->where('booking_party', $type)
        $bookings = $query->get();
        $stats = [
            'total_vehicles' => $merchant->vehicles ? $merchant->vehicles->where('status', AppConst::LAUNCH_ACTIVE)->count() : 0,
            'total_cabins' => 0,
            'total_seats' => 0,
            'total_decks' => 0,
            'total_bookings' => 0,
            'total_booking_amount' => 0,
            'total_booking_vat' => 0,
            'total_cabin_booking_amount' => 0,
            'total_cabin_booking_vat' => 0,
            'total_seat_booking_amount' => 0,
            'total_seat_booking_vat' => 0,
            'total_deck_booking_amount' => 0,
            'total_deck_booking_vat' => 0,
            'total_charge_amount' => 0,
            'total_vat_amount' => 0,
            'total_customer_vat_amount' => 0,
            'total_discount_amount' => 0,
            'total_cabin_bookings' => 0,
            'total_seat_bookings' => 0,
            'total_deck_bookings' => 0,
            'bookingGraphs' => [],
            'passengers' => [
                'jolzan' => 0,
                'merchant' => 0,
                'other' => 0
            ]
        ];

        if( $merchant->vehicles ) {
            foreach( $merchant->vehicles as $launch ) {
                $stats['total_cabins'] += $launch->cabins ? $launch->cabins->count() : 0;
                $stats['total_seats'] += $launch->seats ? $launch->seats->count() : 0;
                $stats['total_decks'] += $launch->passengers_capacity;
            }
        }

        $monthDays = date('t', strtotime($month));
        for( $i = 1; $i <= $monthDays; $i++ ) {
            $stats['bookingGraphs'][date('d/m/Y', strtotime(date($month . '-' . $i)))] = [
                'cabin' => [
                    'count' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null,
                    'amount' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null
                ],
                'seat' => [
                    'count' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null,
                    'amount' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null
                ],
                'deck' => [
                    'count' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null,
                    'amount' => (date('Y-m-d', strtotime(date( $month . '-' . $i))) <= date('Y-m-d')) ? 0 : null
                ]
            ];
        }

        // dd( $stats );
        $groupBookings = [];
        $cancellations = [];
        if( $bookings ) {
            foreach( $bookings as $booking ) {
                if( $booking->bookingConfirmed->count() > 0 ) {
                    foreach( $booking->bookingConfirmed as $item ) {
                        $passenger = json_decode( $item['passenger'] );
                        $stats['passengers'][$booking->booking_party] += $passenger->person;
                    }
                }
                if( $booking->booking_party == strtolower($type) ) {
                    if( $booking->bookingConfirmed->count() > 0 ) {
                        $groupBookings[$booking->booking_date][] = [
                            'date' => date('d/m/Y', strtotime($booking->booking_date)),
                            'items' => $booking->bookingConfirmed->count(),
                            'subtotal' => $booking->total_amount,
                            'total' => $booking->total_payable,
                            'vat' => $booking->vat_total,
                            'charge' => $booking->charge_total,
                            'discount' => $booking->total_discount
                        ];
                        foreach ( $booking->bookingConfirmed as $item ) {
                            $passenger = json_decode( $item['passenger'] );
                            // $mappings = new Collection($item['mappings']);
                            // $mapping = $mappings->where('cabin_id', $item['cabin_id'])->first();
                            // if( $mapping ) {
                            //     $stats['passengers'][$mapping->ownership] += ($passenger->person) ? abs($passenger->person) : 1;
                            // }
                            $vat = abs(($item['price'] * ($item['vat_amount']/100)));
                            $charge = abs(($item['price'] * ($item['charge_amount']/100)));
                            $customer_vat = 0;
                            if( $item['vat_applicable_to'] == 'customer' ) {
                                $customer_vat = $vat;
                            }
                            if( $item['booking_type'] == 'deck' ) {
                                $stats['total_deck_bookings'] += ( $passenger ) ? $passenger->person : 1;
                                $stats['total_bookings'] += ( $passenger ) ? $passenger->person : 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['deck']['count'] += ( $passenger ) ? $passenger->person : 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['deck']['amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                                $stats['total_deck_booking_amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                            } elseif( $item['booking_type'] == 'seat') {
                                $stats['total_seat_bookings'] += 1;
                                $stats['total_bookings'] += 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['seat']['count'] += 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['seat']['amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                                $stats['total_seat_booking_amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                            } else {
                                $stats['total_cabin_bookings'] += 1;
                                $stats['total_bookings'] += 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['cabin']['count'] += 1;
                                $stats['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))]['cabin']['amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                                $stats['total_cabin_booking_amount'] += abs($item['price'] + $charge + $customer_vat - $item['discount']);
                            }

                            $stats['total_booking_amount'] += abs($item['price'] + $charge - $item['discount']);
                            $stats['total_discount_amount'] += abs($item['discount']);
                            $stats['total_charge_amount'] += $charge;
                            $stats['total_vat_amount'] += $vat;
                            if( $item['vat_applicable_to'] == 'customer') {
                                $stats['total_customer_vat_amount'] += $customer_vat;
                                $stats['total_booking_amount'] += abs($vat);
                            }
                        }

                        if( $booking->cancelled ) {
                            foreach( $booking->cancelled as $cancellation ) {
                                $total = 0;
                                $vat = 0;
                                $customer_vat = 0;
                                $charge = 0;
                                $discount = 0;
                                $date = date('d/m/Y', strtotime($cancellation->created_at));
                                if( $cancellation['bookingItems'] ) {
                                    foreach( $cancellation['bookingItems'] as $item ) {
                                        $total += abs($item['price']);
                                        $vat += abs($item['price']*($item['vat_amount']/100));
                                        if( $item['vat_applicable_to'] == 'customer' ) {
                                            $customer_vat += $vat;
                                        }
                                        $charge += abs($item['price']*($item['charge_amount']/100));
                                        $discount += $item['discount'];
                                    }
                                }
                                $cancellations[$date][] = [
                                    'total' => ($total + $customer_vat + $charge - $discount),
                                    'vat' => $vat,
                                    'customer_vat' => $customer_vat,
                                    'charge' => $charge,
                                    'discount' => $discount
                                ];
                            }
                        }
                    }
                }
            }
        }
        // dd( $stats );

        if( $request->ajax() == True ) {
            return response()->json($stats);
        }

        return view('admin.home.merchant', compact('stats', 'groupBookings', 'cancellations', 'merchant', 'month', 'type'))->withTitle('Dashboard');
    }

    private function initSupervisor()
    {
        return view('admin.home.supervisor')->withTitle('Dashboard');
    }

    private function initManager()
    {
        return view('admin.home.manager')->withTitle('Dashboard');
    }

    private function _monthlyBookingGraph( $params )
    {
        $month = ( $params['month'] ) ? $params['month'] : date('Y-m');
        $merchant = Merchant::where('user_id', $params['merchant_id'] )->first();
        $launchIds = [];
        if( $merchant->vehicles ) {
            foreach( $merchant->vehicles as $launch ) {
                array_push($launchIds, $launch->id);
            }
        }
        $bookings = BookingItem::with(['launch'])
        ->whereHas('launch', function($query) use( $params ) {
            $query->where('merchant_id', $params['merchant_id']);
        })
        ->whereIn('vehicle_id', $launchIds)
        ->whereBetween('booking_date', [date('Y-m-01', strtotime($month)), date('Y-m-t', strtotime($month))])->get();

        if( $bookings ) {
            foreach( $bookings as $item ) {
                if( $item->booking_type == 'deck' ) {
                    $passenger = json_decode( $item->passenger );
                    $params['stats']['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))][$item->booking_type] += abs($passenger->person);
                } else {
                    $params['stats']['bookingGraphs'][date('d/m/Y', strtotime($item->booking_date))][$item->booking_type] += 1;
                }
            }
        }

        return $params['stats']['bookingGraphs'];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function merchantBookings( Request $request, $merchant_id )
    {
        if( $request->ajax() === true ) {
            $month = (isset($_GET['month'] ) ) ? date('Y-m', strtotime($_GET['month'] ) ) : date('Y-m');
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $merchant = Merchant::where('user_id', $merchant_id )->first();
            $launchIds = [];
            if( $merchant->vehicles ) {
                foreach( $merchant->vehicles as $launch ) {
                    array_push($launchIds, $launch->id);
                }
            }
            $bookings = Booking::with(['bookingItems.launch', 'customer', 'payment'])
            ->whereHas('bookingItems', function($q) use($merchant_id) {
                $q->whereHas('launch', function($query) use( $merchant_id) {
                    $query->where('merchant_id', $merchant_id);
                });
            })
            ->with(['bookingItems' => function($q) use($launchIds) {
                $q->whereIn('vehicle_id', $launchIds);
            }])->whereBetween('booking_date', [date('Y-m-01', strtotime($month)), date('Y-m-t', strtotime($month))]);

            $total = $bookings->count();

            $bookings->offset($start);
            if( $limit < 0 ) {
                $limit = $total;
            }
            $bookings = $bookings->limit($limit)->get();

            $responseArr = [];
            if( $bookings ) {
                foreach( $bookings as $booking ) {
                    $row = [
                        'id' => $booking->id,
                        'created_at' => date('d/m/Y h:i a', strtotime($booking->created_at)),
                        'customer_name' => $booking->customer['name'],
                        'customer_email' => $booking->customer['email'],
                        'customer_mobile' => $booking->customer['mobile'],
                        'discount' => 0,
                        'booking_items' => $booking->bookingItems->count(),
                        'subtotal' => 0,
                        'total' => 0,
                        'vat_total' => 0,
                        'charge_total' => 0,
                        'payment_status' => ( $booking->payment ) ? $booking->payment['status'] : 'pending'
                    ];
                    if( $booking->bookingItems ) {
                        foreach( $booking->bookingItems as $item ) {
                            if( in_array($item['vehicle_id'], $launchIds) ) {
                                $row['subtotal'] += abs($item['price']);
                                $row['discount'] += abs($item['discount']);
                                $vat = 0;
                                if( $merchant->vat_applicable_to == 'customer' ) {
                                    $vat = ($item['price'] * ($item['vat_amount']/100));
                                }
                                $row['vat_total'] += $vat;
                                $charge_total = abs(($item['price'] * ($item['charge_amount']/100)));
                                $row['charge_total'] += $charge_total;
                                $row['total'] += abs(($item['price'] + $vat + $charge_total) - $item['discount']);
                            }
                        }
                    }
                    array_push($responseArr, $row);
                }
            }

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $responseArr
            ];

            return response()->json( $data );
        }
    }

    public function search( Request $request )
    {
        $data = ['status' => false, 'label' => 'error', 'content' => 'Failed', 'data' => []];
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'search_query' => 'required|string'
        ]);

        if( $validator->fails() ) {
            header('Content-Type: text/plain');
            return $validator->errors()->first();
        } else {
            $bookings = Booking::with(['customer', 'payment', 'bookingItems.item.cabinType', 'bookingItems.launch']);
            if( $user->type == 'merchant' ) {
                if( $user->hasRole('merchant') ) {
                    $merchant_id = $user->id;
                } else {
                    $merchant_id = $user->merchant_id;
                }
                $launchIds = [];

                if( $user->vehicles ) {
                    foreach( $user->vehicles as $launch ) {
                        array_push($launchIds, $launch->id);
                    }
                }

                $bookings->whereHas('bookingItems', function($query) use($launchIds) {
                    $query->whereIn('vehicle_id', $launchIds);
                });
            }

            if( strlen($request->search_query) > 10 ) {
                $bookings->whereHas('customer', function($q) use($request) {
                    $q->where('mobile',  $request->search_query);
                });
            } else {
                $bookings->where('id', $request->search_query);
            }

            $bookings = $bookings->get();
            $str = '';
            if( $bookings->count() > 0 ) {
                $str .= '<table class="table table-striped table-bordered">';
                $str .= '<tr>
                    <th>Booking date</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>';
                foreach( $bookings as $booking ) {
                    $str .= '<tr>';
                    $str .= '<td>' . date('d/m/Y', strtotime($booking->booking_date)) . '</td>';
                    $str .= '<td>';
                    if( $booking->bookingItems ) {
                        foreach( $booking->bookingItems as $item ) {
                            $passenger = json_decode($item['passenger']);
                            if( $item['item'] && $item['item']['type'] == 'cabin') {
                                $str .= '<span class="badge badge-info"><i class="fa fa-bed"></i> ' . $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] . '</span>';
                            } elseif( $item['item'] &&  $item['item']['type'] == 'seat') {
                                $str .= '<span class="badge badge-info"><i class="fa fa-chair"></i> ' . $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] . '</span>';
                            } else {
                                $str .= '<span class="badge badge-info"><i class="fa fa-ticket-alt"></i> X ' . $passenger->person . '</span>';
                            }
                        }
                    }
                    $str .= '</td>';
                    $str .= '<td>' . $booking->total_payable . '</td>';
                    $str .= '<td><a href="' . route('dashboard.booking.show', $booking->id) . '">View</a></td>';
                    $str .= '</tr>';
                }
                $str .= '</table>';
            } else {
                $str .= '<div class="alert text-center">Nothing found</div>';
            }
            header('Content-Type: text/plain');
            return $str;
        }
    }
}
