<?php

namespace App\Services;

use App\Constants\AppConst;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Jobs\CabinMappingBookingJob;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Events\UserCreated;
use App\Models\BookingCancellationItem;
use App\Repository\Interfaces\CancellationRepositoryInterface;
use App\Models\VehicleSchedule;
use App\Models\Payment;
use App\Models\PaymentCollector;
use App\Repository\Interfaces\BookingItemRepositoryInterface;
use App\Repository\Interfaces\BookingRepositoryInterface;
use App\Support\BookingInvoice;
use App\Models\ScheduleCabinMapping;
use App\Models\User;
use App\Events\BookingCompleteEvent;
use App\Jobs\BookingChargeAdjustmentJob;
use App\Support\AuthActor;
use App\Services\Promotion\DTO\PromotionContext;
use App\Services\Promotion\PromotionEngine;
use App\Services\Promotion\Exceptions\PromotionLimitExceededException;

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

                    $authUser = auth('customer')->user() ?? auth('api')->user() ?? auth()->user();
                    $isCustomerOrAgent = $authUser instanceof Customer
                        || $authUser instanceof Agent
                        || (isset($authUser->type) && in_array($authUser->type, ['customer', AppConst::AGENT_ROLE], true));
                    if (! $isCustomerOrAgent) {
                        if ($item->booked || strtotime($item->schedule['operation_timeline']) < time()) {
                            array_push($data['booked'], $item->type . ' - ' . $item->cabin_no);
                        }
                        return !$item->booked && strtotime($item->schedule['operation_timeline']) > time();
                    } else {
                        if ($item->booked || $item->is_reserved || $item->ownership != 'durpalla' || strtotime($item->schedule['leaving_at']) < time()) {
                            array_push($data['booked'], $item->type . ' - ' . $item->cabin_no);
                        }
                        return !$item->booked && !$item->is_reserved && $item->ownership == 'durpalla' && strtotime($item->schedule['leaving_at']) > time();
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
                $charge_amount = ($user instanceof Customer) ? abs(getOption('service_charge_web', 5)) : abs(getOption('service_charge_counter', 5));
                $bookingItems = $this->fetchBookingItems($cartItems, $customer);
                $appliedPromotion = $this->applyCouponToBookingItems($bookingItems);
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
                    $total_amount += abs($item['price']);
                    $charge_total += (float) $this->calculation->calculateItemCharge($item);
                    $vat_total += (float) $this->calculation->calculateItemVat($item);
                    $total_discount += abs($item['discount'] ?? 0);
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
                $total_payable = abs(($total_amount + $charge_total + $vat_total) - $total_discount);
                $booking = Booking::create([
                    'booking_date' => date('Y-m-d'),
                    'customer_id' => $customer->id,
                    'total_amount' => $total_amount,
                    'total_discount' => $total_discount,
                    'total_payable' => $total_payable,
                    'vat_amount' => $vat_amount,
                    'vat_total' => $vat_total,
                    'charge_amount' => $charge_amount,
                    'charge_total' => $charge_total,
                    'booking_party' => ($user instanceof Merchant || $user instanceof MerchantStaff) ? 'merchant' : 'durpalla',
                    'platform' => $this->normalizeBookingPlatform(request()->input('platform')),
                    'status' => ($user instanceof Customer || $user instanceof Agent) ? AppConst::BOOKING_PENDING : AppConst::BOOKING_COMPLETE,
                ]);
                AuthActor::setBookedBy($booking, $user);
                $booking->save();

                collect($bookingItems)->each(function ($item, $k) use ($booking, $customer, $user) {
                    $item['booking_id'] = $booking->id;
                    $item['status'] = ($user instanceof Customer || $user instanceof Agent) ? AppConst::BOOKING_ITEM_PENDING : AppConst::BOOKING_ITEM_ACTIVE;
                    $booking->bookingItems()->create($item);
                });

                if ($appliedPromotion !== null && $total_discount > 0) {
                    try {
                        app(PromotionEngine::class)->redeem(
                            $appliedPromotion,
                            $booking->id,
                            $user?->id,
                            (float) $total_discount
                        );
                    } catch (PromotionLimitExceededException $e) {
                        throw new \Exception($e->getMessage());
                    }
                }

                $payment = $this->savePayment($booking);
                dispatch(new BookingChargeAdjustmentJob($booking, $this->calculation));
                BookingCompleteEvent::dispatch($booking);
                $data['success'] = true;
                $data['order_id'] = $booking->id;
                $data['booking_id'] = $booking->id;
                $data['total_payable'] = $booking->total_payable;
                $data['total_discount'] = $booking->total_discount;
                $data['charge_total'] = $booking->charge_total;
                $data['vat_total'] = $booking->vat_total;
                $data['invoice'] = BookingInvoice::signedUrl($booking, 30);
                $data['trans_id'] = (string)$payment->transaction_id;
                $data['message'] = 'Your order has been confirmed.';
                $booking->load(['bookingItems', 'customer']);
                $data['data'] = $booking->format();
                if (($user instanceof MerchantStaff && $user->isSupervisor()) || (isset($user->type) && $user->type == 'supervisor')) {
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
            $user = Agent::find(request()->input('agent_id')) ?: User::find(request()->input('agent_id'));
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
            'total_amount' => 0,
            'total_discount' => 0,
            'total_payable' => 0,
            'vat_amount' => $vat_amount,
            'vat_total' => 0,
            'charge_amount' => $charge_amount,
            'charge_total' => 0,
            'booking_party' => ($user instanceof Merchant || $user instanceof MerchantStaff) ? 'merchant' : 'durpalla',
            'platform' => 'web',
            'status' => 'COMPLETE'
        ]);
        AuthActor::setBookedBy($booking, $user);
        $booking->save();

        $itemIds = [];
        $booking_items = [];
        foreach ($cartItems as $item) {
            $trip_date = date('Y-m-d', strtotime($item['trip_date']));

            $booking->total_amount += abs($item['fare']);
            $booking->total_discount += abs($item['discount']);
            $passenger = [
                'type' => 'self',
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'person' => ($item['passenger']) ? $item['passenger']['person'] : 1
            ];
            if (! ($user instanceof Merchant || $user instanceof MerchantStaff)) {
                $itemCharge = abs($item['total_charge'] ?? 0);
                $booking->charge_total += $itemCharge;
                if ($this->calculation->resolveVatApplicableTo() === 'customer') {
                    $booking->vat_total += abs(
                        $itemCharge * (($item['vat_amount'] ?? $this->calculation->resolveVatRate()) / 100)
                    );
                }
            } else {
                $charge_amount = 0;
            }
            if ($user instanceof Agent) {
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
                'vat_applicable_to' => $this->calculation->resolveVatApplicableTo(),
                'route_name' => $item['route_name'],
                'trip_id' => $item['trip_id'],
                'trip_date' => $trip_date,
                'booking_date' => $booking->booking_date,
                'discount' => $item['discount'],
                'boarding_point' => (isset($item['boardingPoint'])) ? json_encode($item['boardingPoint']) : null,
                'passenger' => json_encode($passenger),
                'vat_amount' => $this->calculation->resolveVatRate(),
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
            $data['invoice'] = BookingInvoice::signedUrl($booking, 30);
            $data['trans_id'] = $payment->transaction_id;
            $data['message'] = 'Your order has been confirmed.';
        }

        return $data;
    }

    private function getCustomer($user)
    {
        // Authenticated customer is already the booking customer.
        if ($user instanceof Customer) {
            return $user;
        }

        $requestMobile = request()->input('customer_mobile');

        // Agents must book on behalf of a real customer — never fall back to
        // (or explicitly reuse) their own mobile number.
        if ($user instanceof Agent) {
            $ownMobile = trim((string) ($user->mobile ?? ''));
            $givenMobile = trim((string) $requestMobile);
            if ($givenMobile === '') {
                throw new \Exception("Customer mobile number is required");
            }
            if ($ownMobile !== '' && $givenMobile === $ownMobile) {
                throw new \Exception("You cannot use your own mobile number. Please enter the customer's mobile number.");
            }
        }

        $mobile = $requestMobile ?: ($user->mobile ?? null);
        $name = request()->input('customer_name') ?: ($user->name ?? null);

        if (! $mobile) {
            return null;
        }

        $customer = Customer::where('mobile', $mobile)->first();
        if (! $customer) {
            try {
                $customer = Customer::create([
                    'name' => $name ?: $mobile,
                    'mobile' => $mobile,
                    'password' => Hash::make(Str::random(8)),
                    'status' => 1,
                ]);
                try {
                    event(new UserCreated($customer, 'office'));
                } catch (\Throwable $e) {
                    // ignore listener type mismatches
                }
            } catch (\Exception $exception) {
                return null;
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
            'counter', 'agent', 'agent_app' => 'counter',
            'office' => 'office',
            'supervisor_app' => 'supervisor_app',
            'merchant_desk' => 'merchant_desk',
            'mobile', 'flutter', 'app' => 'android',
            default => 'android',
        };
    }

    /**
     * Apply optional coupon from request onto booking item discounts.
     *
     * @param  list<array<string, mixed>>  $bookingItems
     * @return \App\Models\Promotion|null
     */
    private function applyCouponToBookingItems(array &$bookingItems)
    {
        $code = trim((string) request()->input('coupon', ''));
        if ($code === '' || $bookingItems === []) {
            return null;
        }

        $promoItems = [];
        foreach ($bookingItems as $item) {
            $promoItems[] = [
                'type' => $item['booking_type'] ?? $item['type'] ?? null,
                'amount' => (float) ($item['price'] ?? 0),
                'merchant_id' => $item['merchant_id'] ?? null,
                'route_id' => $item['route_id'] ?? null,
                'vehicle_id' => $item['vehicle_id'] ?? null,
                'schedule_id' => $item['trip_id'] ?? null,
            ];
        }

        $context = PromotionContext::fromArray([
            'user_id' => auth()->id(),
            'service_type' => request()->input('service_type', 'transport'),
            'channel' => request()->input('channel', 'web'),
            'items' => $promoItems,
        ]);

        $result = app(PromotionEngine::class)->applyCode($context, $code);
        if (! $result->success || ! $result->promotion) {
            throw new \Exception($result->message ?: 'Invalid coupon code');
        }

        foreach ($result->itemDiscounts as $index => $discount) {
            if (! isset($bookingItems[$index])) {
                continue;
            }
            $bookingItems[$index]['discount'] = abs((float) $discount);
            $bookingItems[$index]['discount_type'] = 'coupon';
            $bookingItems[$index]['promotion_id'] = $result->promotion->id;
        }

        return $result->promotion;
    }

    private function fetchBookingItems($cartItems, $customer): array
    {
        $user = auth()->user();
        $vatAmount = getOption('vat_amount', 0);
        $platform = $this->normalizeBookingPlatform(request()->input('platform'));
        return collect($cartItems)->map(function ($item, $key) use ($customer, $vatAmount, $platform, $user) {
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
                $merchant = $mapping->schedule['vehicle']['merchant'] ?? null;
                $chargePlatform = $this->calculation->resolveChargeOptionKey(
                    request()->input('platform', $platform)
                );
                $charges = $this->calculation->getCharges([
                    'fare' => $mapping->fare,
                    'price' => $mapping->fare,
                    'service_charge' => $mapping->service_charge,
                    'service_charge_type' => $mapping->service_charge_type,
                    'merchant_service_charge' => is_object($merchant) ? $merchant->getAttribute('service_charge') : ($merchant['service_charge'] ?? null),
                    'merchant_service_charge_type' => is_object($merchant) ? ($merchant->getAttribute('service_charge_type') ?? 'percent') : ($merchant['service_charge_type'] ?? 'percent'),
                ], $chargePlatform);
                $data = [
                    'customer_id' => $customer->id,
                    'mapping_id' => $mapping->id,
                    'booking_type' => $item['type'],
                    'is_ac' => $mapping->cabinType['is_ac'],
                    'vehicle_id' => $mapping->vehicle_id,
                    'cabin_id' => $mapping->cabin_id,
                    'cabin_no' => ($mapping->cabinType) ? $mapping->cabinType['letter'] . '-' . $rawCabinNo : (string) $rawCabinNo,
                    'price' => $mapping->fare,
                    'vat_visibility' => $merchant['vat_visibility'] ?? 1,
                    'vat_applicable_to' => $this->calculation->resolveVatApplicableTo(),
                    'route_name' => $mapping->schedule['startingPoint']['ghat']['name'] . ' - ' . $mapping->schedule['endingPoint']['ghat']['name'],
                    'vehicle_name' => $mapping->schedule['vehicle']['name'],
                    'trip_id' => $mapping->schedule_id,
                    'trip_date' => $mapping->schedule['schedule_date'],
                    'leaving_time' => $mapping->schedule['leaving_at'],
                    'booking_date' => date('Y-m-d'),
                    'discount' => $item['discount'] ?? 0,
                    'promotion_id' => $item['promotion_id'] ?? null,
                    'boarding_point' => (isset($item['boardingPoint'])) ? json_encode($item['boardingPoint']) : null,
                    'passenger' => json_encode($passenger),
                    'vat_amount' => $this->calculation->resolveVatRate(),
                    'charge_amount' => $charges['amount'],
                    'charge_type' => $charges['type'],
                    'status' => 1,
                    'is_honorium' => (int)$mapping->is_honorium,
                    'honorium_charge' => $merchant['honorium_service_charge'] ?? 0,
                    'honorium_type' => $merchant['honorium_type'] ?? null,
                    'incentive' => 0,
                    'incentive_type' => 'percent',
                    'merchant_id' => is_object($merchant) ? $merchant->id : ($merchant['id'] ?? null),
                ];
                if ($user instanceof Agent) {
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
            'status' => (auth()->user() instanceof Customer) ? 'pending' : (($dues > 0) ? 'advance' : 'success'),
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
                        $defaultMax = strtolower((string) $type) === 'cabin' ? 2 : 4;
                        if (BookingItem::where([
                            'trip_id' => $tripID,
                            'booking_type' => $type,
                            'customer_id' => $customerID,
                        ])->count() > getOption('max_' . $type . '_booking', $defaultMax)) {
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
