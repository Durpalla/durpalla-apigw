<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\QuickBookRequest;
use App\Models\ScheduleCabinMapping;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CabinLock;
use App\Models\DeckFare;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\CartService;
use App\Services\GhatService;
use App\Services\TripService;
use App\Models\VehicleRoute;
use App\Models\VehicleSchedule;
use App\Models\Payment;
use App\Models\PaymentCollector;
use App\Rules\BDMobile;
use App\Services\CalculationService;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use App\Events\UserCreated;
use Illuminate\Support\Facades\Validator;

class OtherController extends Controller
{
    protected $success = 200;
    private $booking;
    private $calculation;
    private $trip;
    private $cart;
    private $ghatService;

    public function __construct(
        BookingService $bookingService,
        CalculationService $calculationService,
        TripService $tripService,
        CartService $cartService,
        GhatService $ghatService
    )
    {
        $this->booking = $bookingService;
        $this->calculation = $calculationService;
        $this->trip = $tripService;
        $this->cart = $cartService;
        $this->ghatService = $ghatService;
    }

    public function getTrip(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $layout = collect(VehicleSchedule::with(['route', 'decks.departureFrom.ghat', 'decks.departureTo.ghat', 'boardingVias.ghat', 'startFrom', 'stopTo', 'mappings.cabinType', 'vehicle', 'merchant'])
            ->where('id', $id)
            ->get())
            ->map(function($trip, $key) use($request) {
                return $this->trip->formatTriplayout($trip, $request->floor);
            });

        return response()->json(['success' => true, 'data' => $layout], $this->success);
    }

    public function quickbook(Request $request)
    {
        $type = (isset($_GET['type'])) ? $_GET['type'] : 'launch';
        $trip_id = (isset($_GET['trip_id'])) ? (int)$_GET['trip_id'] : 0;
        $trip_from = (isset($_GET['departure_from'])) ? $_GET['departure_from'] : '';
        $trip_to = (isset($_GET['departure_to'])) ? $_GET['departure_to'] : '';
        $trip_date = date('Y-m-d');
        if (isset($_GET['trip_date'])) {
            $trip_date = \DateTime::createFromFormat('d/m/Y', $_GET['trip_date']);
            $trip_date = $trip_date->format('Y-m-d');
        }

        //get schedules
        $schedules = $this->trip->getSearchTrip([
            'trip_from' => $trip_from,
            'trip_to' => $trip_to,
            'trip_id' => $trip_id,
            'trip_date' => $trip_date,
            'service_type' => $type
        ]);

        if (!\Session::has('user.carts')) {
            \Session::put('user.carts', []);
        }

        $carts = \Session::get('user.carts');
        $ghats = $this->ghatService->getPlucked($type);
        return view('admin.others.quickbook', compact('schedules', 'ghats', 'trip_date', 'type', 'carts', 'trip_id', 'trip_from', 'trip_to'))->withTitle('Quick booking');
    }

    public function otherQuickBook(Request $request)
    {
        $schedule_id = (isset($_GET['schedule_id'])) ? (int)$_GET['schedule_id'] : 0;
        $route_id = (isset($_GET['route_id'])) ? (int)$_GET['route_id'] : 0;
        $trip_date = date('Y-m-d');
        if (isset($_GET['trip_date'])) {
            $trip_date = \DateTime::createFromFormat('d/m/Y', $_GET['trip_date']);
            $trip_date = $trip_date->format('Y-m-d');
        }
        $type = (isset($_GET['type'])) ? $_GET['type'] : 'straight';

        //get schedules
        $schedules = VehicleSchedule::with(['route.boardingPoints', 'route.startingPoint', 'route.endingPoint', 'seatMappings.cabin', 'cabinMappings.cabin', 'locks', 'deckFares.departureFrom', 'deckFares.departureTo', 'ticketBookings'])->withCount(['cabinBookings', 'seatBookings'])->where('schedule_date', '>=', date('Y-m-d'))->where(['schedule_date' => $trip_date, 'schedule_type' => $type]);
        if (Auth::user()->type == 'merchant') {
            if (Auth::user()->hasRole('merchant')) {
                $schedules->where('merchant_id', Auth::user()->id);
            } else {
                $schedules->where('merchant_id', Auth::user()->merchant_id);
            }
        }
        if ($schedule_id) {
            $schedules->where('id', $schedule_id);
        } elseif ($route_id) {
            $schedules->where('route_id', $route_id);
        }

        $schedules = $schedules->paginate(10);

        $routeQuery = VehicleRoute::with(['startingPoint', 'endingPoint', 'boardingVias'])->get();

        $routes = [];
        foreach ($routeQuery as $query) {
            $row['id'] = $query->id;
            $row['name'] = $query->route_name;
            array_push($routes, $row);
        }

        if (!\Session::has('user.carts')) {
            \Session::put('user.carts', []);
        }

        $carts = \Session::get('user.carts');

        return view('admin.others.quickbook', compact('routes', 'schedules', 'trip_date', 'route_id', 'type', 'carts', 'schedule_id'))->withTitle('Quick booking');
    }

    public function addToCart(AddToCartRequest $request)
    {
        $data = ['success' => false, 'message' => 'Cannot add to cart'];
        try {
            $item = ScheduleCabinMapping::with(['cabinType', 'schedule.startFrom', 'schedule.stopTo', 'schedule.boardingVias.ghat', 'schedule.vehicle.merchant'])->findOrFail($request->item_id);

            if (!$this->cart->validate($item)) {
                throw new \Exception('Your selected item is not available or not eligible for booking');
            }

            if ($this->cart->add($item)) {
                $this->cart->save($item);
                $data['success'] = true;
                $data['carts'] = session()->get('user.carts');
                $data['message'] = 'Your item has been added to cart';
            } else {
                throw new \Exception('Opps! something went wrong.');
            }

        } catch (\Exception $e) {
            $data['message'] = $e->getMessage();
        }
        return response()->json($data, $this->success);
    }

    public function addDeckCart(Request $request)
    {
        $data = ['success' => false, 'message' => 'Your item cannot be locked'];
        //validation rules
        $validator = Validator::make($request->all(), [
            'deck_id' => 'bail|required|integer|exists:deck_fares,id',
            'trip_id' => 'bail|required|integer|exists:vehicle_schedules,id',
            'passengers' => 'bail|required|integer'
        ]);

        //validation fails
        if ($validator->fails())
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);

        $schedule = VehicleSchedule::with(['merchant', 'launch', 'startingPoint', 'endingPoint', 'boardingVias'])->findOrFail($request->trip_id);

        if ($schedule->schedule_date >= date('Y-m-d')) {
            $item = DeckFare::find($request->deck_id);

            if ($item) {
                $vat_applicable_to = $schedule->launch['merchant']['vat_applicable_to'];
                $vat_amount = abs(getOption('vat_amount'));
                $vat = ($vat_applicable_to == 'customer') ? abs(($item->fare * $request->passengers) * ($vat_amount / 100)) : 0;
                $service_charge_counter = 0;
                $service_charge = 0;
                $service_charge_type = 'percent';
                if (auth()->user()->type != 'merchant') {
                    $charges = $this->calculation->getCharges($item->toArray(), 'counter');
                    $service_charge_counter = $charges['amount'];
                    $service_charge = $charges['total'];
                    $service_charge_type = $charges['type'] ?? 'percent';
                }
                $discounted = 0;

                if ($schedule->discounts) {
                    if (Auth::user()->type == 'admin') {
                        $userType = 'jolzan';
                    } else {
                        $userType = 'merchant';
                    }
                    foreach ($schedule->discounts as $discount) {
                        $calculated = ($discount->type == 'p') ? (($item->fare * $request->passengers) * ($discount->amount / 100)) : abs($discount->amount * $request->passengers);
                        if (($userType == $discount->applicable_to) || $discount->applicable_to == 'both') {
                            $discounted += ($discount->is_deck) ? $calculated : 0;
                        }
                    }
                }
                $cartItem = [
                    'type' => 'deck',
                    'trip_id' => $schedule->id,
                    'trip_date' => date('Y-m-d H:i:s', strtotime($schedule->schedule_date)),
                    'vehicle_id' => $schedule->vehicle_id,
                    'vehicle_name' => $schedule->vehicle['name'],
                    'route_name' => ($schedule->schedule_type == 'reverse') ? $item->departureTo->ghat->name . ' - ' . $item->departureFrom->ghat->name : $item->departureFrom->ghat->name . ' - ' . $item->departureTo->ghat->name,
                    'cabin_no' => $item->id,
                    'cabin_id' => $item->id,
                    'item_id' => '',
                    'vat_amount' => $vat_amount,
                    'charge_amount' => $service_charge_counter,
                    'charge_type' => $service_charge_type ?? 'percent',
                    'vat_applicable_to' => $vat_applicable_to,
                    'fare' => round(((($schedule->schedule_type == 'reverse') ? $item->reverse_fare : $item->fare) * $request->passengers)),
                    'total_passenger' => $request->passengers,
                    'total_vat' => abs($vat),
                    'total_charge' => abs($service_charge),
                    'discount' => $discounted,
                    'cabin_is_ac' => 0,
                    'status' => 2,
                    'passenger' => ['name' => '', 'mobile' => '', 'person' => $request->passengers],
                    'stoppages' => [],
                    'boardingPoint' => ['id' => $item->departure_from, 'name' => $item->departureFrom['name']],
                    'is_honorium' => 0,
                    'honorium_charge' => 0,
                    'honorium_type' => $schedule->vehicle->merchant['honorium_type'],
                    'incentive' => 0,
                    'incentive_type' => 'percent'
                ];

                $user = Auth::user();
                if ($user->hasRole('supervisor')) {
                    $mapping = collect($user->supervisorMappings)->where('vehicle_id', $item->vehicle_id)->first();
                    $cartItem['incentive'] = $mapping->supervisor_incentive;
                    $cartItem['incentive_type'] = ($mapping->incentive_type == 'percent') ? 'percent' : 'fixed';
                }

                //push stoppages
                if ($schedule->schedule_type == 'reverse') {

                    $cartItem['route_name'] = $schedule->endingPoint['ghat']['name'] . ' - ' . $schedule->startingPoint['ghat']['name'];
                    $cartItem['boardingPoint'] = ['id' => $schedule->endingPoint['ghat']['id'], 'name' => $schedule->endingPoint['ghat']['name']];
                    array_push($cartItem['stoppages'], ['id' => $schedule->endingPoint['ghat']['id'], 'name' => $schedule->endingPoint['ghat']['name']]);
                } else {
                    $cartItem['boardingPoint'] = ['id' => $schedule->startingPoint['ghat']['id'], 'name' => $schedule->startingPoint['ghat']['name']];
                    array_push($cartItem['stoppages'], ['id' => $schedule->startingPoint['ghat']['id'], 'name' => $schedule->startingPoint['ghat']['name']]);
                }

                if ($schedule->boardingVias) {
                    foreach ($schedule->boardingVias as $stoppage) {
                        array_push($cartItem['stoppages'], ['id' => $stoppage['id'], 'name' => $stoppage['name']]);
                    }
                }

                if (!\Session::has('user.carts')) {
                    \Session::put('user.carts', []);
                }
                \Session::push('user.carts', $cartItem);

                $data['carts'] = \Session::get('user.carts');
                $data['success'] = true;
                $data['message'] = 'Your deck ticket has been added successfully';
            }
        }
        return response()->json($data, $this->success);
    }

    public function removeCartItem(Request $request)
    {
        $data = ['success' => false, 'message' => 'Your item cannot be removed'];
        //validation rules
        $validator = Validator::make($request->all(), [
            'item_index' => 'bail|required|integer'
        ]);

        //validation fails
        if ($validator->fails())
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);

        if (!\Session::has('user.carts')) {
            \Session::put('user.carts', []);
        }
        $cartItems = \Session::get('user.carts');

        if ($cartItems) {
            $cart = $cartItems[$request->item_index];

            if ($cart['type'] !== 'deck') {
                CabinLock::where('mapping_id', $cart['item_id'])->get()->each(function($item, $key) {
                    $item->delete();
                });
            }
            if(array_key_exists($request->item_index, $cartItems)) {
                unset($cartItems[$request->item_index]);
                \Session::put('user.carts', $cartItems);
            }

            $data['carts'] = $cartItems;
            $data['success'] = true;
            $data['message'] = 'Your item has been successfully removed';
        } else {
            $data['success'] = true;
            $data['message'] = 'Your item has been successfully removed';
        }

        return response()->json($data, $this->success);
    }

    public function bookingConfirm(QuickBookRequest $request)
    {
        $data = ['success' => false, 'message' => 'Your order cannot be confirmed'];

        $cartItems = session()->get('user.carts');
        try {
            if (!$cartItems) {
                throw new \Exception('No items found in your cart');
            }
            $validated = $this->booking->validate(collect($cartItems)->filter(function($item, $k) {
                return $item['type'] != 'deck';
            })->pluck('item_id')->toArray());
            if($validated['status'] === false) {
                throw new \Exception($validated['message']);
            }

            return $this->booking->confirm2($cartItems, $data);
        } catch (\Exception $e) {
            $data['message'] = $e->getMessage();
        }
        return response()->json($data, $this->success);
    }

    public function bookingConfirm2(Request $request)
    {
        $data = ['success' => false, 'message' => 'Your order cannot be confirmed'];
        //validation rules
        $validator = Validator::make($request->all(), [
            'customer_name' => 'bail|required',
            'customer_mobile' => ['bail', 'required', new BDMobile()],
            'payment_method' => 'bail|required|in:cash,bkash,rocket,nagad',
            'paid_amount' => 'bail|required|numeric|min:0',
            'trx_id' => 'bail|nullable|string'
        ]);

        //validation fails
        if ($validator->fails())
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);

        $cartItems = \Session::get('user.carts');

        $customer_token = Hash::make(Auth::user()->email);
        $lockItems = CabinLock::select('cabin_id')->where('customer_token', $customer_token)->get();

        if ($cartItems) {
            DB::beginTransaction();
            try {
                $user = Auth::user();
                $newCustomer = 0;
                $customer = User::firstOrNew(['mobile' => $request->customer_mobile]);
                $customer->name = ($customer->id) ? $customer->name : $request->customer_name;
                $customer->mobile = $request->customer_mobile;
                // $customer->username = ( !$customer->username ) ? $request->customer_mobile : $customer->username;
                if (!$customer->id) {
                    $customer->password = '123456789';
                    $newCustomer = 1;
                }
                $customer->save();

                if ($newCustomer) {
                    $role = Role::where('name', 'customer')->first();
                    $customer->assignRole($role);
                    event(new UserCreated($customer, 'office'));
                }
                $vat_amount = abs(getOption('vat_amount', 0));
                $charge_amount = abs(getOption('service_charge_counter', 0));

                $booking = Booking::create([
                    'booking_date' => date('Y-m-d'),
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                    'total_amount' => 0,
                    'total_discount' => 0,
                    'total_payable' => 0,
                    'vat_amount' => $vat_amount,
                    'vat_total' => 0,
                    'charge_amount' => $charge_amount,
                    'charge_total' => 0,
                    'booking_party' => ($user->type == 'merchant') ? 'merchant' : 'jolzan',
                    'platform' => 'web',
                    'status' => 'COMPLETE'
                ]);

                $itemIds = [];
                $booking_items = [];
                foreach ($cartItems as $item) {
                    $trip_date = date('Y-m-d', strtotime($item['trip_date']));

                    $booking->total_amount += abs($item['fare']);
                    $booking->total_discount += abs($item['discount']);
                    if ($item['vat_applicable_to'] == 'customer') {
                        $booking->vat_total += abs($item['fare'] * ($item['vat_amount'] / 100));
                    }
                    $passenger = [
                        'type' => 'self',
                        'name' => $customer->name,
                        'mobile' => $customer->mobile,
                        'person' => ($item['passenger']) ? $item['passenger']['person'] : 1
                    ];
                    if (Auth::user()->type != 'merchant') {
                        $booking->charge_total += abs($item['total_charge']);
                    } else {
                        $charge_amount = 0;
                    }
                    array_push($booking_items, [
                        'booking_id' => $booking->id,
                        'vehicle_id' => $item['vehicle_id'],
                        'customer_id' => $booking->customer_id,
                        'booking_type' => $item['type'],
                        'cabin_id' => (in_array($item['type'], ['cabin', 'seat'])) ? $item['cabin_id'] : null,
                        'deck_fare_id' => ($item['type'] == 'deck') ? $item['cabin_id'] : null,
                        'price' => abs($item['fare']),
                        'vat_applicable_to' => $item['vat_applicable_to'],
                        'route_name' => $item['route_name'],
                        'trip_id' => $item['trip_id'],
                        'trip_date' => $trip_date,
                        'booking_date' => $booking->booking_date,
                        'discount' => $item['discount'],
                        'boarding_point' => (isset($item['boardingPoint'])) ? json_encode($item['boardingPoint']) : null,
                        'passenger' => json_encode($passenger),
                        'vat_amount' => $vat_amount,
                        'charge_amount' => $charge_amount,
                        'charge_type' => $item['charge_type'] ?? 'percent',
                        'status' => 1,
                        'is_honorium' => (int)$item['is_honorium'],
                        'honorium_charge' => abs($item['honorium_charge']),
                        'honorium_type' => $item['honorium_type'],
                        'booking_party' => $booking->booking_party,
                        'incentive' => abs($item['incentive']),
                        'incentive_type' => $item['incentive_type']
                    ]);
                }
//                dd($booking_items);
                //save items
                BookingItem::insert($booking_items);

                //update order with total amount
                $booking->total_amount = abs($booking->total_amount);
                // dd( $booking->total*($vat_amount / 100) );
                $booking->total_payable = abs(($booking->total_amount + $booking->vat_total + $booking->charge_total) - $booking->total_discount);
                $dues = round($booking->total_payable - $request->paid_amount, 2);
                if ($dues > 0) {
                    $booking->status = 'ADVANCE';
                }

//                dd($booking);
                // DB::rollback();
                // return $booking;
                if ($booking->save()) {
                    //set payment record
                    $payment = Payment::firstOrnew([
                        'booking_id' => $booking->id
                    ]);
                    $payment->booking_id = $booking->id;
                    $payment->transaction_id = uniqid($booking->id . '_', false);
                    if ($request->trx_id) {
                        $payment->bank_tran_id = $request->trx_id;
                    }
                    $payment->customer_id = $booking->customer_id;
                    $payment->payment_method = $request->payment_method;
                    $payment->status = ($dues > 0) ? 'advance' : 'success';
                    $payment->paid_amount = abs($request->paid_amount);
                    $payment->store_amount = abs($request->paid_amount);
                    $payment->dues = $dues;
                    $payment->save();

                    //Payment collector
                    PaymentCollector::create([
                        'booking_id' => $booking->id,
                        'payment_id' => $payment->id,
                        'supervisor_id' => $user->id,
                        'amount' => $payment->paid_amount,
                        'payment_type' => $payment->payment_method,
                        'remarks' => ($booking->total_payable == $payment->paid_amount) ? 'Full payment' : 'Partial payment'
                    ]);

                    collect($booking_items)->each(function ($item, $key) {
                        if ($item['booking_type'] != 'deck') {
                            ScheduleCabinMapping::where(['cabin_id' => $item['cabin_id'], 'schedule_id' => $item['trip_id']])->update(['booked' => 1]);
                        }
                    });

                    \Session::put('user.carts', []);
                    DB::commit();
                    $qrstring = ($payment->dues > 0) ? $booking->id . '@' . round($payment->dues) : $booking->id;
                    $qrCode = \QrCode::size(500)
                        ->format('png')
                        // ->color(33, 152, 118)
                        ->size(500)
                        ->merge(public_path('default/logo-icon.png'), .1, true)
                        ->generate((string)$qrstring, public_path('qrs/' . $booking->id . '.png'));
                    $order = Booking::with(['bookingItems.launch', 'bookingItems.trip', 'bookingItems.customer', 'bookingItems.item.cabinType'])->find($booking->id);
                    $message = 'Ticket-' . $order->id . '%0A';
                    $scheduleSms = [];
                    if ($order->bookingItems) {
                        foreach ($order->bookingItems as $item) {
                            $scheduleSms[$item->trip_id][] = $item;
                        }
                    }
                    if ($scheduleSms) {
                        foreach ($scheduleSms as $key => $items) {
                            $message .= $items[0]->launch['name'] . '<>';
                            if ($items[0]->trip) {
                                $message .= date('d-m-Y h:iA', strtotime($items[0]->trip['leaving_at']));
                            }
                            if ($items[0]->customer) {
                                $message .= '<>' . $items[0]->customer['mobile'];
                            }
                            foreach ($items as $k => $item) {
//                                if ($k > 0) {
//                                    $message .= ',';
//                                }
                                $passenger = json_decode($item->passenger);
                                if ($item->booking_type != 'deck') {
                                    $message .= '<>' . $item->item['cabinType']['name'] . ' ' . $item->item['type'] . ' (' . $item->item['cabinType']['letter'] . '-' . $item->item['cabin_no'] . ')';
                                } else {
                                    $message .= '<>Deck(' . $passenger->person . ')';
                                }
                            }
                        }
                    }
                    $message .= '%0ASafe travels!';
                    sendSMS([
                        'mobile' => $customer->mobile,
                        'message' => $message
                    ]);
                    $data['success'] = true;
                    $data['order_id'] = $booking->id;
                    $data['invoice'] = route('invoice.download', $booking->id);
                    $data['trans_id'] = $payment->transaction_id;
                    $data['message'] = 'Your order has been confirmed.';
                }
            } catch (\Exception $e) {
                DB::rollback();
                $data['message'] = $e->getMessage() . $e->getFile() . $e->getLine();
            }
        } else {
            $data['message'] = 'Your cart is empty';
        }
        return response()->json($data, $this->success);
    }

    public function confirmation(Request $request)
    {
        return view('admin.others.confirmation')->withTitle('Booking confirmation');
    }
}
