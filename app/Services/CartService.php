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

    private function guestPlainToken(): ?string
    {
        $encrypted = request()->input('guest_unique_id');
        if (! $encrypted) {
            return null;
        }
        try {
            return decrypt($encrypted);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::debug('Guest token decrypt failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function resolveCustomerToken(): ?string
    {
        if (auth()->check()) {
            $user = auth()->user();
            $token = $user && isset($user->email) ? base64_encode($user->email) : null;
            if ($token) {
                // Seats locked as guest before login must follow the customer.
                $this->claimGuestLocks($token);
                return $token;
            }
        }

        return $this->guestPlainToken() ?? request()->input('customer_token');
    }

    /**
     * Re-assign active guest locks to the authenticated customer token.
     */
    private function claimGuestLocks(string $userToken): void
    {
        $guest = $this->guestPlainToken();
        if (! $guest || $guest === $userToken) {
            return;
        }

        CabinLock::query()
            ->where('customer_token', (string) $guest)
            ->where('expire_at', '>', now())
            ->update(['customer_token' => (string) $userToken]);
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

            // Customer/guest cart caps: 4 seats or 2 cabins per trip at a time.
            $limitError = $this->assertCustomerCartLimit($item);
            if ($limitError !== null) {
                return $limitError;
            }
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
        return true;
    }

    /**
     * Soft-limit customer carts (agents have their own quota service).
     */
    private function assertCustomerCartLimit(ScheduleCabinMapping $item): ?string
    {
        $user = auth()->user();
        if ($user instanceof Agent || $user instanceof Merchant || $user instanceof MerchantStaff) {
            return null;
        }

        $customerToken = $this->resolveCustomerToken();
        if ($customerToken === null) {
            return null;
        }

        $type = strtolower((string) ($item->type ?? 'seat'));
        $isCabin = $type === 'cabin';
        $max = $isCabin
            ? (int) getOption('max_cabin_booking', 2)
            : (int) getOption('max_seat_booking', 4);
        if ($max <= 0) {
            return null;
        }

        $lockQuery = CabinLock::query()
            ->where('customer_token', (string) $customerToken)
            ->where('trip_id', (int) $item->schedule_id)
            ->where('expire_at', '>', now())
            ->whereHas('mapping', function ($q) use ($isCabin) {
                if ($isCabin) {
                    $q->where('type', 'cabin');
                } else {
                    $q->where('type', '!=', 'cabin');
                }
            });

        if ($lockQuery->count() >= $max) {
            return $isCabin
                ? "You can select at most {$max} cabins per trip"
                : "You can select at most {$max} seats per trip";
        }

        return null;
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

        // Do not eager-load schedule.discounts — `discounts` table is not present
        // in this DB and previously made GET /cart 500 when locks existed (empty cart on reload).
        $locks = CabinLock::with([
            'mapping.cabinType',
            'mapping.schedule.startFrom',
            'mapping.schedule.stopTo',
            'mapping.schedule.boardingVias.ghat',
            'mapping.schedule.vehicle' => static fn ($q) => $q->withTrashed()->with([
                'merchant' => static fn ($m) => $m->withTrashed(),
            ]),
        ])
            ->where('customer_token', (string) $customerToken)
            ->where('expire_at', '>', now())
            ->get();

        $items = [];
        foreach ($locks as $lock) {
            $item = $lock->mapping;
            if (! $item || ! $item->schedule) {
                continue;
            }
            try {
                $item->lock_id = $lock->id;
                $payload = $this->buildCartItem($item);
                $payload['expires_at'] = $lock->expire_at?->toIso8601String();
                $payload['price'] = $payload['fare'] ?? 0;
                $payload['meta'] = [
                    'cabin_no' => $payload['cabin_no'] ?? null,
                    'vehicle_name' => $payload['vehicle_name'] ?? null,
                    'route_name' => $payload['route_name'] ?? null,
                    'trip_date' => $payload['trip_date'] ?? null,
                    'trip_id' => $payload['trip_id'] ?? $lock->trip_id ?? null,
                    'fare' => $payload['fare'] ?? null,
                    'merchant_id' => $payload['merchant_id'] ?? null,
                    'total_charge' => $payload['total_charge'] ?? 0,
                    'total_vat' => $payload['total_vat'] ?? 0,
                    'vehicle_id' => $payload['vehicle_id'] ?? null,
                ];
                $items[] = $payload;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $items;
    }

    private function buildCartItem($item): array
    {
        $reqPlatform = request()->input('platform');
        if (! $reqPlatform && auth()->user() instanceof Agent) {
            $reqPlatform = 'agent_app';
        }
        $platform = $this->calculation->resolveChargeOptionKey($reqPlatform ?: 'web');
        $schedule = $item->schedule;
        $vehicle = $schedule?->vehicle ?? $schedule?->launch;
        $merchant = $vehicle?->merchant ?? $schedule?->merchant;
        // VAT is global Options; charge is item → merchant → Options (admin-set only).
        $vat_applicable_to = $this->calculation->resolveVatApplicableTo();
        $vat_amount = $this->calculation->resolveVatRate();
        $service_charge_counter = 0;
        $service_charge = 0;
        $service_charge_type = 'percent';
        $discounted = 0;
        $incentive = 0;
        $incentive_type = 0;
        $is_honorium = 0;
        $honorium_charge = 0;

        $chargeInput = [
            'fare' => $item->fare,
            'price' => $item->fare,
            'service_charge' => $item->service_charge,
            'service_charge_type' => $item->service_charge_type ?? 'percent',
            'merchant_service_charge' => $merchant?->getAttribute('service_charge'),
            'merchant_service_charge_type' => $merchant?->getAttribute('service_charge_type') ?? 'percent',
        ];

        if(auth()->check()) {
            $user = auth()->user();
            if (! ($user instanceof Merchant || $user instanceof MerchantStaff)) {
                $charges = $this->calculation->getCharges($chargeInput, $platform);
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
                $honorium_charge = $merchant?->honorium_service_charge ?? 0;
            }
        } else {
            $charges = $this->calculation->getCharges($chargeInput, $platform);
            $service_charge_counter = $charges['amount'];
            $service_charge = $charges['total'];
            $service_charge_type = $charges['type'];
        }

        // VAT only on service charge (not fare), from Options.
        $vat = $this->calculation->vatOnCharge((float) $service_charge);

        $startName = $schedule?->startFrom?->name ?? '';
        $endName = $schedule?->stopTo?->name ?? '';

        $cartItem = [
            'lock_id' => $item->lock_id,
            'type' => $item->type,
            'trip_id' => $item->schedule_id,
            'trip_date' => date('Y-m-d H:i:s', strtotime((string) $schedule->leaving_at)),
            'vehicle_id' => $schedule->vehicle_id,
            'vehicle_name' => $vehicle?->name ?? '',
            'route_name' => trim($startName . ' - ' . $endName, ' -'),
            'cabin_no' => ($item->cabinType) ? ($item->cabinType->letter ?? '') . '-' . $item->cabin_no : $item->cabin_no,
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
            'cabin_is_ac' => ($item->cabinType) ? (int) ($item->cabinType->is_ac ?? 0) : 0,
            'status' => 2,
            'passenger' => ['name' => '', 'mobile' => '', 'person' => $item->passenger_capacity, 'for' => '', 'type' => ''],
            'stoppages' => $this->tripService->formatStoppages($schedule),
            'boardingPoint' => ['id' => $schedule->starting_point, 'name' => $startName],
            'is_honorium' => $is_honorium,
            'honorium_charge' => $honorium_charge,
            'honorium_type' => $merchant?->honorium_type ?? null,
            'incentive' => $incentive,
            'incentive_type' => $incentive_type,
            'merchant_id' => $merchant?->id,
        ];

        return $cartItem;
    }
}
