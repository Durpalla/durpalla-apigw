<?php

namespace App\Services;

use App\Constants\AppConst;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use App\Jobs\CabinMappingBookingJob;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Events\UserCreated;
use App\Models\BookingCancellationItem;
use App\Repository\Interfaces\CancellationRepositoryInterface;
use App\Models\VehicleSchedule;
use App\Models\Payment;
use App\Models\PaymentCollector;
use App\Repository\Interfaces\BookingItemRepositoryInterface;
use App\Repository\Interfaces\BookingRepositoryInterface;
use App\Models\ScheduleCabinMapping;
use App\Models\User;
use App\Events\BookingCompleteEvent;
use App\Jobs\BookingChargeAdjustmentJob;

class BookingService
{
    private BookingRepositoryInterface $booking;
    private CalculationService $calculation;
    private BookingItemRepositoryInterface $bookingItem;
    private CancellationRepositoryInterface $cancellationRepository;

    public function __construct(
        BookingRepositoryInterface      $booking,
        CalculationService              $calculation,
        BookingItemRepositoryInterface  $bookingItem,
        CancellationRepositoryInterface $cancellationRepository
    )
    {
        $this->booking = $booking;
        $this->calculation = $calculation;
        $this->bookingItem = $bookingItem;
        $this->cancellationRepository = $cancellationRepository;
    }

    public function validate(array $items): array
    {
        $data = ['status' => true, 'message' => '', 'booked' => []];
        try {
            if (empty($items)) {
                throw new \Exception('No cart items provided', 404);
            }
            $mappings = ScheduleCabinMapping::with('schedule')->whereIn('id', $items)->get();
            if (count($items) !== $mappings->count()) {
                throw new \Exception('Item does not match');
            }

            if (count($items) !== collect($mappings)->filter(function ($item, $k) use (&$data) {
                    if ($item->schedule['status'] !== AppConst::SCHEDULE_ACTIVE) {
                        return false;
                    }

                    if (!in_array(auth('api')->user()->type, ['customer', AppConst::AGENT_ROLE])) {
                        if ($item->booked || strtotime($item->schedule['operation_timeline']) < time()) {
                            array_push($data['booked'], $item->type . ' - ' . $item->cabin_no);
                        }
                        return !$item->booked && strtotime($item->schedule['operation_timeline']) > time();
                    } else {
                        if ($item->booked || $item->is_reserved || $item->ownership != 'jolzan' || strtotime($item->schedule['leaving_at']) < time()) {
                            array_push($data['booked'], $item->type . ' - ' . $item->cabin_no);
                        }
                        return !$item->booked && !$item->is_reserved && $item->ownership == 'jolzan' && strtotime($item->schedule['leaving_at']) > time();
                    }
                })->count()) {
                throw new \Exception('Some items already been booked or reserved');
            }
        } catch (\Exception $exception) {
            $data['status'] = false;
            $data['message'] = $exception->getMessage();
            if (!empty($data['booked'])) {
                $data['message'] = 'Your desired ' . implode(',', $data['booked']) . ' booked by others, please choose another';
            }
        }
        return $data;
    }

    public function validateItems(array $items): array
    {
        $data = ['status' => true, 'message' => ''];
        $exists = [];
        collect($items)->each(function ($item, $key) use (&$data, &$exists) {
            if ($item->cabin_type !== 'deck') {
                $trip = VehicleSchedule::find($item->trip_id);
                $booking = BookingItem::where(['cabin_id' => $item->cabin_id, 'trip_id' => $item->trip_id])
                    ->get()
                    ->filter(function ($item, $key) {
                        return in_array($item->status, [AppConst::BOOKING_ITEM_ACTIVE, AppConst::BOOKING_ITEM_PENDING]);
                    });
                if ($trip->status !== AppConst::SCHEDULE_ACTIVE || $booking->count() > 0) {
                    array_push($exists, $item->cabin_type . '-' . $item->cabin_no);
                    $data['status'] = false;
                }
            }
        });
        if (!empty($exists)) {
            $data['message'] = 'Your desired ' . implode(',', $exists) . ' is not available';
        }
        return $data;
    }

    public function confirmFailedBooking(array $data, $booking_id)
    {
        $booking = $this->booking->find($booking_id);
        $booking->payment->update(array_merge($data, ['status' => AppConst::PAYMENT_SUCCESS]));
        $booking->bookingItems->each(function ($item, $key) {
            $item->update(['status' => AppConst::BOOKING_ITEM_ACTIVE]);
        });

        dispatch(new CabinMappingBookingJob($booking, AppConst::BOOKING_ITEM_ACTIVE));

        return $this->booking->update($data, $booking_id);
    }

    public function confirm($cartItems, &$data)
    {
        try {
            DB::transaction(function () use ($cartItems, &$data) {
                $user = auth()->user();
                $customer = $this->getCustomer($user);
                if (!$customer) {
                    throw new \Exception("Cannot create or find customer");
                }
                $vat_amount = abs(getOption('vat_amount', 0));
                $charge_amount = ($user->type == 'customer') ? abs(getOption('service_charge_web', 0)) : abs(getOption('service_charge_counter', 0));
                $bookingItems = $this->fetchBookingItems($cartItems, $customer);
                $vat_total = 0;
                $charge_total = 0;
                $total_amount = 0;
                $total_discount = 0;
                $vehicleName = '';
                $route_name = '';
                $item_list = ['cabin' => [], 'seat' => []];
                $item_count = 0;
                $vatVisibility = 1;
                $trip_date = '';
                $leaving_time = '';
                $boarding_point = null;
                collect($bookingItems)->each(function ($item, $key) use (&$vat_total, &$charge_total, &$total_amount, &$total_discount, &$vehicleName, &$route_name, &$item_list, &$item_count, &$vatVisibility, &$trip_date, &$leaving_time, &$boarding_point) {
                    $vat_total += $this->calculation->calculateVat($item['vat_amount']);
                    $charge_total += $this->calculation->calculateCharge($item['charge_amount'], $item['charge_type']);
                    $total_amount += $vat_total + $item['price'] + $charge_total;
                    $total_discount += $item['discount'];
                    $vehicleName = $item['vehicle_name'];
                    $route_name = $item['route_name'];
                    $item_count += 1;
                    $vatVisibility = $item['vat_visibility'];
                    $trip_date = $item['trip_date'];
                    $leaving_time = $item['leaving_time'];
                    $boarding_point = json_decode($item['boarding_point'], true);
                    array_push($item_list[$item['booking_type']], [
                        'type' => $item['booking_type'],
                        'cabin_no' => $item['cabin_no'] ?? '',
                        'is_ac' => $item['is_ac'] ?? 0,
                    ]);
                });
                $booking = Booking::create([
                    'booking_date' => date('Y-m-d'),
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                    'total_amount' => $total_amount,
                    'total_discount' => $total_discount,
                    'total_payable' => $total_amount - $total_discount,
                    'vat_amount' => $vat_amount,
                    'vat_total' => $vat_total,
                    'charge_amount' => $charge_amount,
                    'charge_total' => $charge_total,
                    'booking_party' => ($user->type == 'merchant') ? 'merchant' : 'jolzan',
                    'platform' => $this->normalizeBookingPlatform(request()->input('platform')),
                    'status' => (in_array($user->type, ['customer', 'agent'])) ? AppConst::BOOKING_PENDING : AppConst::BOOKING_COMPLETE
                ]);

                collect($bookingItems)->each(function ($item, $k) use ($booking, $customer, $user) {
                    $item['booking_id'] = $booking->id;
                    $item['status'] = (in_array($user->type, ['customer', 'agent'])) ? AppConst::BOOKING_ITEM_PENDING : AppConst::BOOKING_ITEM_ACTIVE;
                    $booking->bookingItems()->create($item);
                });

                $payment = $this->savePayment($booking);
                dispatch(new BookingChargeAdjustmentJob($booking, $this->calculation));
                BookingCompleteEvent::dispatch($booking);
                $data['success'] = true;
                $data['order_id'] = $booking->id;
                $data['invoice'] = URL::temporarySignedRoute(
                    'invoice.download',
                    now()->addMinutes(30),
                    ['id' => $booking->id]
                );
                $data['trans_id'] = (string)$payment->transaction_id;
                $data['message'] = 'Your order has been confirmed.';
                $booking->load(['bookingItems', 'customer']);
                $data['data'] = $booking->format();
                if ($user->type == 'supervisor') {
                    $data['advance'] = (bool)$payment->dues;
                    $data['token'] = [
                        'vehicle_name' => $vehicleName,
                        'trip_date' => $trip_date,
                        'route_name' => $route_name,
                        'pnr' => $booking->id,
                        'booking_time' => date('Y-m-d H:i:s', strtotime($booking->created_at)),
                        'leaving_at' => $leaving_time,
                        'transaction_id' => $payment->transaction_id,
                        'booking_items' => "",
                        'items' => (object)$item_list,
                        'items_count' => $item_count,
                        'supervisor_name' => $user->name,
                        'customer_name' => $customer->name,
                        'customer_mobile' => $customer->mobile,
                        'for' => ($user->id == $customer->id) ? 'self' : 'other',
                        'subtotal' => $booking->total_amount,
                        'vat_visibility' => $vatVisibility,
                        'total_vat' => $booking->vat_total,
                        'total_charge' => $booking->charge_total,
                        'total_discount' => $booking->total_discount,
                        'total' => $booking->total_payable,
                        'paid' => $payment->paid_amount,
                        'due' => round($booking->total_payable - $payment->paid_amount, 2),
                        'boarding_point' => ($boarding_point) ? $boarding_point : null,
                        'hotline' => getOption('company_hotline_code', '')
                    ];
                }
            }, 2);
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return $data;
    }

    public function confirm2($cartItems, &$data)
    {
        $user = auth()->user();
        if (request()->input('agent_id')) {
            $user = User::find(request()->input('agent_id'));
        }
        $customer = $this->getCustomer($user);
        if (!$customer) {
            return $data;
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
            if ($user->type == AppConst::AGENT_TYPE) {
                $incentive = $user->incentive->incentive;
                $incentive_type = $user->incentive->incentive_type;
            } else {
                $incentive = abs($item['incentive']);
                $incentive_type = $item['incentive_type'];
            }
            BookingItem::create([
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
                'incentive' => $incentive,
                'incentive_type' => $incentive_type
            ]);
        }

        //update order with total amount
        $booking->total_amount = abs($booking->total_amount);
        // dd( $booking->total*($vat_amount / 100) );
        $booking->total_payable = abs(($booking->total_amount + $booking->vat_total + $booking->charge_total) - $booking->total_discount);
        $dues = round($booking->total_payable - request()->paid_amount, 2);
        if ($dues > 0) {
            $booking->status = 'ADVANCE';
        }


        if ($booking->save()) {
            //set payment record
            $payment = Payment::firstOrnew([
                'booking_id' => $booking->id
            ]);
            $payment->booking_id = $booking->id;
            $payment->transaction_id = uniqid($booking->id . '_', false);
            if (request()->trx_id) {
                $payment->bank_tran_id = request()->trx_id;
            }
            $payment->customer_id = $booking->customer_id;
            $payment->payment_method = request()->payment_method;
            $payment->status = ($dues > 0) ? 'advance' : 'success';
            $payment->paid_amount = abs(request()->paid_amount);
            $payment->store_amount = abs(request()->paid_amount);
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

            BookingCompleteEvent::dispatch($booking);
            $data['success'] = true;
            $data['order_id'] = $booking->id;
            $data['invoice'] = URL::temporarySignedRoute(
                'invoice.download',
                now()->addMinutes(30),
                ['id' => $booking->id]
            );
            $data['trans_id'] = $payment->transaction_id;
            $data['message'] = 'Your order has been confirmed.';
        }

        return $data;
    }

    private function getCustomer($user)
    {
        if (in_array($user->type, ['supervisor', AppConst::AGENT_ROLE]) && !request()->input('agent_id')) {
            $customer = $user;
        } else {
            $customer = User::where(['mobile' => request()->input('customer_mobile')])->first();
            if (!$customer) {
                try {
                    $customer = User::create([
                        'name' => request()->input('customer_name'),
                        'mobile' => request()->input('customer_mobile'),
                        'password' => Hash::make(Str::random(8))
                    ]);

                    event(new UserCreated($customer, 'office'));
                } catch (\Exception $exception) {
                    $customer = $user;
                }
            }
        }
        return $customer;
    }

    /**
     * bookings.platform ENUM includes android, web, counter, office, supervisor_app, merchant_desk, ios
     * (see durpalla database migrations). Map informal labels to stored values.
     */
    private function normalizeBookingPlatform(?string $raw): string
    {
        $p = strtolower(trim((string) ($raw ?? '')));

        return match ($p) {
            'web' => 'web',
            'android' => 'android',
            'ios', 'iphone' => 'ios',
            'counter' => 'counter',
            'office' => 'office',
            'supervisor_app' => 'supervisor_app',
            'merchant_desk' => 'merchant_desk',
            'mobile', 'flutter', 'app' => 'android',
            default => 'android',
        };
    }

    private function fetchBookingItems($cartItems, $customer): array
    {
        $user = auth()->user();
        $chargeType = getOption('service_charge_platform', 'global');
        $vatAmount = getOption('vat_amount', 0);
        $platform = $this->normalizeBookingPlatform(request()->input('platform'));
        return collect($cartItems)->map(function ($item, $key) use ($customer, $chargeType, $vatAmount, $platform, $user) {
            $item = (array)$item;
            $passenger = $item['passengers'] ?? [];
            if ($item['for_self']) {
                $passenger = ['type' => 'self', 'name' => $customer->name, 'mobile' => $customer->mobile, 'person' => 1];
            } else {
                $passenger['type'] = 'other';
            }

            if ($item['type'] == 'deck') {
                return [
                    'booking_type' => 'deck'
                ];
            } else {
                $mapping = ScheduleCabinMapping::with(['schedule.vehicle.merchant', 'cabinType', 'cabin', 'schedule.startingPoint.ghat', 'schedule.endingPoint.ghat'])->find($item['item_id']);
                if (is_array($passenger)) {
                    $passenger['person'] = ($mapping->cabinType) ? $mapping->cabinType['capacity'] : 1;
                }
                $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
                $rawCabinNo = $meta['cabin_no'] ?? ($item['cabin_no'] ?? null) ?? $mapping->cabin?->cabin_no ?? '';
                $data = [
                    'customer_id' => $customer->id,
                    'mapping_id' => $mapping->id,
                    'booking_type' => $item['type'],
                    'is_ac' => $mapping->cabinType['is_ac'],
                    'vehicle_id' => $mapping->vehicle_id,
                    'cabin_id' => $mapping->cabin_id,
                    'cabin_no' => ($mapping->cabinType) ? $mapping->cabinType['letter'] . '-' . $rawCabinNo : (string) $rawCabinNo,
                    'price' => $mapping->fare,
                    'vat_visibility' => $mapping->schedule['vehicle']['merchant']['vat_visibility'],
                    'vat_applicable_to' => $mapping->schedule['vehicle']['merchant']['vat_applicable_to'],
                    'route_name' => $mapping->schedule['startingPoint']['ghat']['name'] . ' - ' . $mapping->schedule['endingPoint']['ghat']['name'],
                    'vehicle_name' => $mapping->schedule['vehicle']['name'],
                    'trip_id' => $mapping->schedule_id,
                    'trip_date' => $mapping->schedule['schedule_date'],
                    'leaving_time' => $mapping->schedule['leaving_at'],
                    'booking_date' => date('Y-m-d'),
                    'discount' => $item['discount'] ?? 0,
                    'boarding_point' => (isset($item['boardingPoint'])) ? json_encode($item['boardingPoint']) : null,
                    'passenger' => json_encode($passenger),
                    'vat_amount' => $vatAmount,
                    'charge_amount' => ($chargeType == 'global') ? getOption('service_charge_' . $platform, 0) : $mapping->service_charge,
                    'charge_type' => ($chargeType == 'global') ? 'percent' : $mapping->service_charge_type,
                    'status' => 1,
                    'is_honorium' => (int)$mapping->is_honorium,
                    'honorium_charge' => $mapping->schedule['vehicle']['merchant']['honorium_service_charge'],
                    'honorium_type' => $mapping->schedule['vehicle']['merchant']['honorium_type'],
                    'incentive' => 0,
                    'incentive_type' => 'percent',
                ];
                if ($user->type == AppConst::AGENT_ROLE) {
                    $data['incentive'] = $user->incentive->incentive;
                    $data['incentive_type'] = $user->incentive->incentive_type;
                }
                return $data;
            }
        })->toArray();
    }

    private function savePayment($booking)
    {
        $dues = (request()->input('paid_amount')) ? $booking->total_payable - request('paid_amount') : 0;
        return Payment::create([
            'booking_id' => $booking->id,
            'transaction_id' => strtoupper(uniqid($booking->id, false)),
            'bank_tran_id' => (request()->trx_id) ? request()->trx_id : null,
            'customer_id' => $booking->customer_id,
            'payment_method' => request()->payment_method ? request()->payment_method : null,
            'status' => auth()->user()->type == 'customer' ? 'pending' : (($dues > 0) ? 'advance' : 'success'),
            'paid_amount' => request()->paid_amount ? request()->paid_amount : $booking->total_payable,
            'store_amount' => request()->paid_amount ? request()->paid_amount : 0,
            'dues' => $dues
        ]);
    }

    public function iAmNotBlacker($bookingItems, $customerID): bool
    {
        $notBlacker = true;
        collect($bookingItems)->groupBy('trip_id')
            ->each(function ($item, $tripID) use (&$notBlacker, $customerID) {
                $item->groupBy('booking_type')
                    ->each(function ($item, $type) use (&$notBlacker, $tripID, $customerID) {
                        if (BookingItem::where(['trip_id' => $tripID, 'booking_type' => $type, 'customer_id' => $customerID])->count() > getOption('max_' . $type . '_booking', 2)) {
                            $notBlacker = false;
                        }
                    });
            });
        return $notBlacker;
    }

    public function cancelBooking($booking, bool $charge_refundable = false)
    {
        $user = auth()->user();
        $cancellableItems = $booking->bookingItems->filter(function ($item, $key) {
            return $item->status === AppConst::BOOKING_ITEM_ACTIVE;
        });

        $cancelType = 't';
        if ($booking->bookingItems->count() < $cancellableItems->count()) {
            $cancelType = 'p';
        }
        $cancellation = $this->cancellationRepository->create([
            'booking_id' => $booking->id,
            'type' => $cancelType,
            'customer_id' => $booking->customer_id,
            'user_id' => $user->id,
            'transaction_id' => uniqid(),
            'items' => $cancellableItems->implode('id', ','),
            'vat_refundable' => (int)getOption('is_vat_refundable'),
            'charge_refundable' => ($charge_refundable) ? 1 : 0,
            'total_refundable' => $cancellableItems->map(function ($item, $key) {
                return [
                    'refundable' => $this->calculation->calculateRefundableAmount($item->toArray(), true)
                ];
            })->sum('refundable')
        ]);
        $cancellableItems->each(function ($item, $key) use ($cancellation, $user, $booking) {
            BookingCancellationItem::create([
                'booking_cancellation_id' => $cancellation->id,
                'booking_item_id' => $item->id,
                'customer_id' => $booking->customer_id,
                'officer_id' => $user->id,
                'refundable_amount' => $this->calculation->calculateRefundableAmount($item->toArray(), true)
            ]);
        });

        return true;
    }

    public function checkPaymentTransaction(Booking $booking)
    {
        dd($booking);

//                        BookingFailedEvent::dispatch($booking);
    }
}
