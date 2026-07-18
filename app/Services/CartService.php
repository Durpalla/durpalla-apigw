<?php


namespace App\Services;


use App\Constants\AppConst;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use Illuminate\Support\Facades\DB;
use App\Models\BookingItem;
use App\Models\CabinLock;
use App\Models\ScheduleCabinMapping;
use App\Models\VehicleSchedule;

class CartService
{
    private $tripService;
    private $calculation;

    public function __construct(
        TripService $tripService,
        CalculationService $calculation
    )
    {
        $this->tripService = $tripService;
        $this->calculation = $calculation;
    }

    /**
     * Resolve customer token for cabin locks: auth user email (base64) or guest ID from cookie/header.
     * Guest ID is set by EnsureGuestId middleware (encrypted in request as guest_unique_id).
     */
    public function getCurrentCustomerToken(): ?string
    {
        return $this->resolveCustomerToken();
    }

    private function resolveCustomerToken(): ?string
    {
        if (auth()->check()) {
            $user = auth()->user();
            return $user && isset($user->email) ? base64_encode($user->email) : null;
        }
        $encrypted = request()->input('guest_unique_id');
        if ($encrypted) {
            try {
                return decrypt($encrypted);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::debug('Guest token decrypt failed', ['message' => $e->getMessage()]);
                return null;
            }
        }
        return request()->input('customer_token');
    }

    public function add($item): bool
    {
        $customerToken = $this->resolveCustomerToken();
        if ($customerToken === null) {
            return false;
        }
        try {
            DB::transaction(function() use($item, $customerToken) {
                $lock = CabinLock::create([
                    'cabin_id' => $item->cabin_id,
                    'mapping_id' => $item->id,
                    'customer_token' => ( string ) $customerToken,
                    'trip_id' => ( int ) $item->schedule_id,
                    'expire_at' => now()->addMinutes(config('constants.cart_expires'))
                ]);
                $item->update(['is_locked' => 1, 'lock_id' => $lock->id]);
            }, 2);
        } catch (\Exception $exception) {
            return false;
        }
        return true;
    }

    public function remove()
    {

    }

    /**
     * Validate item for lock. Returns true if valid, or error message string if invalid.
     */
    public function validate(ScheduleCabinMapping $item): bool|string
    {
        try {
            $user = request()->user();

            if (!$this->validTrip($item->schedule)) {
                return 'Trip is not valid (past or departs within 30 minutes)';
            }

            if ($item->is_locked || $item->booked || $item->is_reserved) {
                return 'Your selected item is not available (already locked, booked, or reserved)';
            }
            if (!$user && ($item->ownership ?? '') == 'merchant') {
                return 'Your selected item is not available (merchant items require login)';
            }

            if ($user && \App\Support\AuthActor::isCustomerOrAgent($user) && ($item->ownership ?? '') == 'merchant') {
                return 'Your selected item is not available (merchant items not bookable by customer/agent)';
            }

            if ($user && \App\Support\AuthActor::isSupervisor($user) && ($item->ownership ?? '') != 'merchant') {
                return 'Your selected item is not available (supervisor can only book merchant items)';
            }

            if (BookingItem::where(['cabin_id' => $item->cabin_id, 'trip_id' => $item->schedule_id, 'status' => AppConst::BOOKING_ITEM_ACTIVE])->count()) {
                return 'Your selected item has already been booked';
            }
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
        return true;
    }

    private function validTrip(VehicleSchedule $schedule): bool
    {
        if(auth()->check()) {
            if(strtotime($schedule->operation_timeline) > time()) {
                return true;
            }
        } elseif($schedule->leaving_at >= date('Y-m-d H:i:s', strtotime('+30 minute'))) {
            return true;
        }
        return false;
    }

    public function save($item): array
    {
        $cartItem = $this->buildCartItem($item);

        if (!session()->has('user.carts')) {
            session()->put('user.carts', []);
        }
        session()->push('user.carts', $cartItem);
        return $cartItem;
    }

    /**
     * Active cart rows for the current guest or authenticated customer.
     */
    public function listItems(): array
    {
        $customerToken = $this->resolveCustomerToken();
        if ($customerToken === null) {
            return [];
        }

        $locks = CabinLock::with([
            'mapping.cabinType',
            'mapping.schedule.startFrom',
            'mapping.schedule.stopTo',
            'mapping.schedule.boardingVias.ghat',
            'mapping.schedule.vehicle.merchant',
            'mapping.schedule.discounts',
            'mapping.schedule.launch',
        ])
            ->where('customer_token', (string) $customerToken)
            ->where('expire_at', '>', now())
            ->get();

        $items = [];
        foreach ($locks as $lock) {
            $item = $lock->mapping;
            if (! $item) {
                continue;
            }
            $item->lock_id = $lock->id;
            $payload = $this->buildCartItem($item);
            $payload['expires_at'] = $lock->expire_at?->toIso8601String();
            $payload['price'] = $payload['fare'];
            $payload['meta'] = [
                'cabin_no' => $payload['cabin_no'] ?? null,
                'vehicle_name' => $payload['vehicle_name'] ?? null,
                'route_name' => $payload['route_name'] ?? null,
                'trip_date' => $payload['trip_date'] ?? null,
                'fare' => $payload['fare'] ?? null,
            ];
            $items[] = $payload;
        }

        return $items;
    }

    private function buildCartItem($item): array
    {
        $platform = (request()->platform !== null && request()->platform !== 'android') ? request()->platform : 'mobile';
        $vat_applicable_to = $item->schedule->launch['merchant']['vat_applicable_to'];
        $vat_amount = abs(getOption('vat_amount', 0));
        $vat = 0;
        if ($vat_applicable_to == 'customer') {
            $vat = abs($item->fare * ($vat_amount / 100));
        }
        $service_charge_counter = 0;
        $service_charge = 0;
        $service_charge_type = 'percent';
        $discounted = 0;
        $incentive = 0;
        $incentive_type = 0;
        $is_honorium = 0;
        $honorium_charge = 0;
        if(auth()->check()) {
            $user = auth()->user();
            if (! ($user instanceof Merchant || $user instanceof MerchantStaff)) {
                $charges = $this->calculation->getCharges($item->toArray(), $platform);
                $service_charge_counter = $charges['amount'];
                $service_charge = $charges['total'];
                $service_charge_type = $charges['type'];
            }

            if (($user instanceof MerchantStaff && $user->isSupervisor()) || (isset($user->type) && $user->type == 'supervisor')) {
                $supervisor = collect($user->supervisorMappings)->where('vehicle_id', $item->schedule->vehicle_id)->first();
                $incentive = $supervisor->supervisor_incentive;
                $incentive_type = ($supervisor->incentive_type == 'percent') ? 'percent' : 'fixed';
            }

            if ($user instanceof Agent) {
                $incentive = $user->incentive->incentive;
                $incentive_type = $user->incentive->incentive_type;
            }

            if (($user instanceof Merchant || $user instanceof MerchantStaff) && $item->honorium) {
                $is_honorium = 1;
                $honorium_charge = $item->launch['merchant']['honorium_service_charge'];
            }

            if ($item->schedule->discounts) {
                if ($user instanceof Agent || (isset($user->type) && in_array($user->type, [AppConst::AGENT_TYPE, 'admin'], true))) {
                    $userType = 'durpalla';
                } else {
                    $userType = 'merchant';
                }
                foreach ($item->schedule->discounts as $discount) {
                    $calculated = ($discount->type == 'p') ? ($item->fare * ($discount->amount / 100)) : $discount->amount;
                    if (($userType == $discount->applicable_to) || $discount->applicable_to == 'both') {
                        switch ($item->type) {
                            case 'cabin':
                                $discounted += ($discount->is_cabin) ? $calculated : 0;
                                break;
                            case 'seat':
                                $discounted += ($discount->is_seat) ? $calculated : 0;
                                break;
                        }
                    }
                }
            }
        } else {
            $charges = $this->calculation->getCharges($item->toArray(), $platform);
            $service_charge_counter = $charges['amount'];
            $service_charge = $charges['total'];
            $service_charge_type = $charges['type'];
        }
        $cartItem = [
            'lock_id' => $item->lock_id,
            'type' => $item->type,
            'trip_id' => $item->schedule_id,
            'trip_date' => date('Y-m-d H:i:s', strtotime($item->schedule->leaving_at)),
            'vehicle_id' => $item->schedule->vehicle_id,
            'vehicle_name' => $item->schedule->vehicle['name'],
            'route_name' => $item->schedule->startFrom['name'] . ' - ' . $item->schedule->stopTo['name'],
            'cabin_no' => ($item['cabinType']) ? $item['cabinType']['letter'] . '-' . $item['cabin_no'] : $item['cabin_no'],
            'item_id' => $item->id,
            'cabin_id' => $item->cabin_id,
            'fare' => abs($item->fare),
            'total_vat' => abs($vat),
            'total_charge' => abs($service_charge),
            'discount' => $discounted,
            'vat_amount' => $vat_amount,
            'charge_amount' => $service_charge_counter,
            'charge_type' => $service_charge_type ?? 'percent',
            'vat_applicable_to' => $vat_applicable_to,
            'cabin_is_ac' => ($item['cabinType']) ? $item->cabinType['is_ac'] : 0,
            'status' => 2,
            'passenger' => ['name' => '', 'mobile' => '', 'person' => $item->passenger_capacity, 'for' => '', 'type' => ''],
            'stoppages' => $this->tripService->formatStoppages($item->schedule),
            'boardingPoint' => ['id' => $item->schedule->starting_point, 'name' => $item->schedule->startFrom['name']],
            'is_honorium' => $is_honorium,
            'honorium_charge' => $honorium_charge,
            'honorium_type' => $item->schedule->vehicle->merchant['honorium_type'],
            'incentive' => $incentive,
            'incentive_type' => $incentive_type
        ];

        return $cartItem;
    }
}
