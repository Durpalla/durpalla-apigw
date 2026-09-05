<?php

namespace App\Services\Hotel;

use App\Constants\AppConst;
use App\Helpers\LogHelper;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\HotelRoomType;
use App\Models\Payment;
use App\Models\PaymentCollector;
use App\Services\Hotel\HotelInventoryService;
use App\Services\Hotel\ChildRuleEngine;
use App\Services\Telemetry\BusinessMetrics;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Repositories\Hotel\BookingHotelItemRepositoryInterface;
use App\Models\HotelRoom;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\RoomRatePlan;
use App\Services\Hotel\Contracts\HotelSupplierInterface;
use App\Services\Hotel\Suppliers\LocalHotelSupplier;
use App\Services\Hotel\Suppliers\RateHawkSupplier;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MerchantDeskBookingService
{
    protected $bookingHotelItemRepository;
    protected $childRuleEngine;
    protected HotelInventoryService $sharedInventory;

    public function __construct(
        BookingHotelItemRepositoryInterface $bookingHotelItemRepository,
        HotelInventoryService $sharedInventory,
    ) {
        $this->bookingHotelItemRepository = $bookingHotelItemRepository;
        $this->sharedInventory = $sharedInventory;
    }

    /**
     * Get all hotel bookings.
     */
    public function getAll()
    {
        try {
            $bookings = \App\Models\Booking::where('service_type', 'hotel')
                ->with(['hotelItems.hotel', 'hotelItems.roomType', 'customer'])
                ->latest()
                ->get();
            return response()->success($bookings);
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'HOTEL_BOOKING_GET_ALL_EXCEPTION'
            ]);
            return response()->failed(['message' => 'Failed to fetch bookings']);
        }
    }

    /**
     * Get hotel booking by ID.
     */
    public function find($id)
    {
        try {
            $booking = \App\Models\Booking::where('service_type', 'hotel')
                ->with(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan', 'customer', 'supplier'])
                ->findOrFail($id);
            return response()->success($booking);
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'HOTEL_BOOKING_FIND_EXCEPTION',
                'booking_id' => $id
            ]);
            return response()->failed(['message' => 'Booking not found']);
        }
    }

    /**
     * Create a hotel booking from a validated payload (e.g. FormRequest or merchant API).
     */
    public function createWithValidatedData(array $data)
    {
        try {
            $checkIn = Carbon::parse($data['check_in_date']);
            $checkOut = Carbon::parse($data['check_out_date']);
            $nights = (int) $checkIn->diffInDays($checkOut);

            if ($checkOut->lte($checkIn) || $nights < 1) {
                return response()->failed(['message' => 'Check-out date must be after check-in date']);
            }

            if (empty($data['rooms']) || ! is_array($data['rooms'])) {
                return response()->failed(['message' => 'At least one room is required']);
            }

            DB::beginTransaction();

            $guestName = trim((string) ($data['guest_name'] ?? ''));
            $guestMobile = trim((string) ($data['guest_mobile'] ?? ''));
            $guestEmail = trim((string) ($data['guest_email'] ?? ''));
            $hasWalkInGuest = $guestName !== '' || $guestMobile !== '';
            // Walk-in desk/admin: store guest contact on booking; do NOT invent a Customer by mobile
            // (OTP login would otherwise claim those bookings when the guest self-registers).
            $customerId = array_key_exists('customer_id', $data) && $data['customer_id']
                ? (int) $data['customer_id']
                : ($hasWalkInGuest ? null : (auth()->id() ?: null));

            if ($customerId === null && ! $hasWalkInGuest) {
                DB::rollBack();

                return response()->failed(['message' => 'Select a customer or enter guest name and mobile.']);
            }
            if ($hasWalkInGuest && $guestMobile === '') {
                DB::rollBack();

                return response()->failed(['message' => 'Guest mobile is required for walk-in bookings.']);
            }

            // Create unified booking record
            $booking = Booking::create([
                'booking_date' => now()->format('Y-m-d'),
                'service_type' => 'hotel',
                'from_date' => $checkIn->format('Y-m-d'),
                'to_date' => $checkOut->format('Y-m-d'),
                'customer_id' => $customerId,
                'guest_name' => $guestName !== '' ? $guestName : null,
                'guest_mobile' => $guestMobile !== '' ? $guestMobile : null,
                'guest_email' => $guestEmail !== '' ? $guestEmail : null,
                'total_amount' => 0, // Will be calculated
                'total_discount' => $data['discount_amount'] ?? 0,
                'total_payable' => 0, // Will be calculated
                'payment_status' => 0, // pending
                'status' => AppConst::BOOKING_PENDING,
                'platform' => $data['platform'] ?? 'web',
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_booking_reference' => null, // Will be set after supplier booking
            ]);

            if (auth()->user() instanceof \Illuminate\Database\Eloquent\Model) {
                \App\Support\AuthActor::setBookedBy($booking, auth()->user());
                $booking->save();
            }

            $totalAmount = 0;
            $bookingItems = [];
            // Aggregate shared inventory by module hotel_rooms.id (mod_hr_* in hotel_inventory).
            $localUnitsByRoomProduct = [];

            // Process each room booking
            foreach ($data['rooms'] as $roomData) {
                $hotelId = $roomData['hotel_id'];
                $roomTypeId = $roomData['room_type_id'];
                $ratePlanId = $roomData['rate_plan_id'];
                $adults = $roomData['adults'] ?? $data['adults'] ?? 1;
                $children = $roomData['children'] ?? $data['children'] ?? 0;
                $childrenAges = $roomData['children_ages'] ?? [];
                $moduleRoomId = (int) ($roomData['room_id'] ?? 0);

                $hotel = Hotel::findOrFail($hotelId);
                $ratePlan = RoomRatePlan::findOrFail($ratePlanId);

                // Get unit price
                $unitPrice = $this->getUnitPrice($hotel, $roomTypeId, $ratePlan, $data, $moduleRoomId ?: null);

                // Calculate child price
                $childPrice = 0;
                if ($children > 0 && ! empty($childrenAges)) {
                    $childRuleEngine = new ChildRuleEngine(
                        $hotel,
                        $ratePlan,
                        $unitPrice,
                        $childrenAges,
                        $nights
                    );

                    $validation = $childRuleEngine->validate();
                    if (! $validation['valid']) {
                        DB::rollBack();

                        return response()->failed(['message' => implode(', ', $validation['errors'])]);
                    }

                    $childPrice = $childRuleEngine->calculateChildPrice();
                }

                $roomTotal = ($unitPrice * $nights) + $childPrice;
                $totalAmount += $roomTotal;

                if ($this->tracksLocalInventory($hotel, $data)) {
                    if ($moduleRoomId <= 0) {
                        DB::rollBack();

                        return response()->failed(['message' => 'room_id is required to reserve shared inventory.']);
                    }
                    $localUnitsByRoomProduct[$moduleRoomId] = ($localUnitsByRoomProduct[$moduleRoomId] ?? 0) + 1;
                }

                // Create booking hotel item
                $bookingItem = $this->bookingHotelItemRepository->create([
                    'booking_id' => $booking->id,
                    'hotel_id' => $hotelId,
                    'room_id' => $roomData['room_id'] ?? null,
                    'room_type_id' => $roomTypeId,
                    'rate_plan_id' => $ratePlanId,
                    'check_in_date' => $checkIn->format('Y-m-d'),
                    'check_out_date' => $checkOut->format('Y-m-d'),
                    'nights' => $nights,
                    'adults' => $adults,
                    'children' => $children,
                    'children_ages' => $childrenAges,
                    'unit_price' => $unitPrice,
                    'child_price' => $childPrice,
                    'total_price' => $roomTotal,
                    'supplier' => $hotel->source,
                ]);

                $bookingItems[] = $bookingItem;
            }

            foreach ($localUnitsByRoomProduct as $moduleRoomId => $units) {
                try {
                    $this->checkAndReserveInventory((int) $moduleRoomId, $checkIn, $checkOut, (int) $units);
                } catch (\Exception $e) {
                    DB::rollBack();

                    return response()->failed(['message' => $e->getMessage() ?: 'Room not available for selected dates']);
                }
            }

            if ($localUnitsByRoomProduct === [] && $this->requiresLocalInventory($data)) {
                DB::rollBack();

                return response()->failed(['message' => 'Unable to reserve hotel inventory for this booking.']);
            }

            // Calculate totals
            $serviceCharge = $data['service_charge'] ?? 0;
            $vatAmount = $data['vat_amount'] ?? 0;
            $vatTotal = ($totalAmount + $serviceCharge) * ($vatAmount / 100);
            $discountAmount = $data['discount_amount'] ?? 0;
            $totalPayable = $totalAmount + $serviceCharge + $vatTotal - $discountAmount;

            // Update booking totals
            $booking->update([
                'total_amount' => $totalAmount + $serviceCharge + $vatTotal,
                'total_discount' => $discountAmount,
                'total_payable' => $totalPayable,
                'vat_amount' => $vatAmount,
                'vat_total' => $vatTotal,
                'charge_amount' => 0, // Service charge
                'charge_total' => $serviceCharge,
            ]);

            // If supplier booking required, call supplier API
            if ($booking->supplier_id) {
                $supplierResult = $this->bookWithSupplier($booking, $data);

                if (! $supplierResult['success']) {
                    DB::rollBack();

                    return response()->failed(['message' => $supplierResult['message'] ?? 'Supplier booking failed']);
                }

                $booking->update([
                    'supplier_booking_reference' => $supplierResult['supplier_booking_reference'] ?? null,
                    'status' => ($supplierResult['status'] ?? '') === 'confirmed'
                        ? AppConst::BOOKING_CONFIRMED
                        : AppConst::BOOKING_PENDING,
                ]);
            } else {
                // Local booking - auto confirm
                $booking->update(['status' => AppConst::BOOKING_CONFIRMED]);
            }

            try {
                $this->applyDeskPayment($booking, (float) $totalPayable, $data);
            } catch (\InvalidArgumentException $e) {
                DB::rollBack();

                return response()->failed(['message' => $e->getMessage()]);
            }

            DB::commit();

            $booking->load(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan', 'customer', 'payment', 'collections']);

            BusinessMetrics::recordBooking('hotel', 'success');

            return response()->success($booking, 'Hotel booking created successfully');
        } catch (\Throwable $exception) {
            DB::rollBack();
            BusinessMetrics::recordBooking('hotel', 'failed');
            LogHelper::exception($exception, [
                'keyword' => 'HOTEL_BOOKING_CREATE_EXCEPTION',
            ]);

            $message = $this->friendlyCreateFailureMessage($exception);

            return response()->failed(['message' => $message]);
        }
    }

    /**
     * Create a hotel booking (web/admin FormRequest).
     */
    public function create($request)
    {
        return $this->createWithValidatedData($request->validated());
    }

    /**
     * Create an UNPAID hotel booking ("admin invoice") for the quick-booking tool.
     *
     * Mirrors createWithValidatedData() but leaves the booking PENDING with a payment
     * token + deadline and a pending Payment row, so a token-secured payment link can be
     * sent to the customer. Local inventory is reserved immediately; the pending-expiry
     * job releases it if the deadline passes. Returns the Booking model.
     *
     * @throws \Exception on validation failure.
     */
    public function createUnpaidInvoice(array $data): Booking
    {
        $checkIn = Carbon::parse($data['check_in_date']);
        $checkOut = Carbon::parse($data['check_out_date']);

        if ($checkOut->lte($checkIn)) {
            throw new \Exception('Check-out date must be after check-in date');
        }
        if (empty($data['rooms'])) {
            throw new \Exception('No rooms selected for booking.');
        }

        $nights = $checkIn->diffInDays($checkOut);

        return DB::transaction(function () use ($data, $checkIn, $checkOut, $nights) {
            $guestName = trim((string) ($data['guest_name'] ?? $data['customer_name'] ?? ''));
            $guestMobile = trim((string) ($data['guest_mobile'] ?? $data['customer_mobile'] ?? ''));
            $guestEmail = trim((string) ($data['guest_email'] ?? $data['customer_email'] ?? ''));
            $customerId = ! empty($data['customer_id']) ? (int) $data['customer_id'] : null;

            if ($customerId === null) {
                if ($guestMobile === '') {
                    throw new \Exception('Select a customer or enter guest name and mobile.');
                }
            }

            $booking = Booking::create([
                'booking_date' => now()->format('Y-m-d'),
                'service_type' => 'hotel',
                'from_date' => $checkIn->format('Y-m-d'),
                'to_date' => $checkOut->format('Y-m-d'),
                'customer_id' => $customerId,
                'guest_name' => $guestName !== '' ? $guestName : null,
                'guest_mobile' => $guestMobile !== '' ? $guestMobile : null,
                'guest_email' => $guestEmail !== '' ? $guestEmail : null,
                'total_amount' => 0,
                'total_discount' => 0,
                'total_payable' => 0,
                'payment_status' => 0,
                'status' => AppConst::BOOKING_PENDING,
                'booking_party' => 'durpalla',
                'platform' => $data['platform'] ?? 'web',
                'payment_token' => (string) Str::uuid(),
                'payment_deadline' => now()->addMinutes((int) getOption('admin_invoice_window', 1440)),
            ]);
            \App\Support\AuthActor::setBookedBy($booking, auth()->user());

            $totalAmount = 0;
            // Aggregate shared inventory by module hotel_rooms.id (mod_hr_*).
            $localUnitsByRoomProduct = [];

            foreach ($data['rooms'] as $roomData) {
                $hotelId = $roomData['hotel_id'];
                $roomTypeId = $roomData['room_type_id'];
                $ratePlanId = $roomData['rate_plan_id'];
                $adults = $roomData['adults'] ?? $data['adults'] ?? 1;
                $children = $roomData['children'] ?? $data['children'] ?? 0;
                $childrenAges = $roomData['children_ages'] ?? [];
                $moduleRoomId = (int) ($roomData['room_id'] ?? 0);

                $hotel = Hotel::findOrFail($hotelId);
                $ratePlan = RoomRatePlan::findOrFail($ratePlanId);
                $unitPrice = $this->getUnitPrice($hotel, $roomTypeId, $ratePlan, $data, $moduleRoomId ?: null);

                $childPrice = 0;
                if ($children > 0 && !empty($childrenAges)) {
                    $childRuleEngine = new ChildRuleEngine($hotel, $ratePlan, $unitPrice, $childrenAges, $nights);
                    $validation = $childRuleEngine->validate();
                    if (!$validation['valid']) {
                        throw new \Exception(implode(', ', $validation['errors']));
                    }
                    $childPrice = $childRuleEngine->calculateChildPrice();
                }

                $roomTotal = ($unitPrice * $nights) + $childPrice;
                $totalAmount += $roomTotal;

                if ($this->tracksLocalInventory($hotel, $data)) {
                    if ($moduleRoomId <= 0) {
                        throw new \Exception('room_id is required to reserve shared inventory.');
                    }
                    $localUnitsByRoomProduct[$moduleRoomId] = ($localUnitsByRoomProduct[$moduleRoomId] ?? 0) + 1;
                }

                $this->bookingHotelItemRepository->create([
                    'booking_id' => $booking->id,
                    'hotel_id' => $hotelId,
                    'room_id' => $roomData['room_id'] ?? null,
                    'room_type_id' => $roomTypeId,
                    'rate_plan_id' => $ratePlanId,
                    'check_in_date' => $checkIn->format('Y-m-d'),
                    'check_out_date' => $checkOut->format('Y-m-d'),
                    'nights' => $nights,
                    'adults' => $adults,
                    'children' => $children,
                    'children_ages' => $childrenAges,
                    'unit_price' => $unitPrice,
                    'child_price' => $childPrice,
                    'total_price' => $roomTotal,
                    'supplier' => $hotel->source,
                ]);
            }

            foreach ($localUnitsByRoomProduct as $moduleRoomId => $units) {
                $this->checkAndReserveInventory((int) $moduleRoomId, $checkIn, $checkOut, (int) $units);
            }

            $serviceCharge = $data['service_charge'] ?? 0;
            $vatAmount = $data['vat_amount'] ?? 0;
            $vatTotal = ($totalAmount + $serviceCharge) * ($vatAmount / 100);
            $discountAmount = $data['discount_amount'] ?? 0;
            $totalPayable = $totalAmount + $serviceCharge + $vatTotal - $discountAmount;

            $booking->update([
                'total_amount' => $totalAmount + $serviceCharge + $vatTotal,
                'total_discount' => $discountAmount,
                'total_payable' => $totalPayable,
                'vat_amount' => $vatAmount,
                'vat_total' => $vatTotal,
                'charge_amount' => 0,
                'charge_total' => $serviceCharge,
            ]);

            Payment::create([
                'booking_id' => $booking->id,
                'transaction_id' => strtoupper(uniqid((string) $booking->id, false)),
                'customer_id' => $booking->customer_id,
                'payment_method' => null,
                'status' => 'pending',
                'paid_amount' => 0,
                'store_amount' => 0,
                'dues' => $totalPayable,
            ]);

            $booking->load(['hotelItems.hotel', 'customer', 'payment']);

            BusinessMetrics::recordBooking('hotel', 'success');

            return $booking;
        });
    }

    /**
     * Resolve or create a customer from admin-supplied contact info.
     */
    protected function resolveCustomer(array $data): Customer
    {
        $mobile = $data['customer_mobile'] ?? null;
        if (!$mobile) {
            throw new \Exception('Customer mobile is required.');
        }
        $customer = Customer::firstOrNew(['mobile' => $mobile]);
        if (!$customer->id) {
            $customer->name = $data['customer_name'] ?? $mobile;
            $customer->password = Hash::make(Str::random(8));
            $customer->status = 1;
        }
        if (!empty($data['customer_email']) && empty($customer->email)) {
            $customer->email = $data['customer_email'];
        }
        $customer->mobile = $mobile;
        $customer->save();

        return $customer;
    }

    /**
     * Get unit price for room.
     */
    protected function getUnitPrice(Hotel $hotel, $roomTypeId, RoomRatePlan $ratePlan, array $data, $roomId = null): float
    {
        // For local / unset-source hotels, use room base_price
        $source = strtolower(trim((string) ($hotel->source ?? '')));
        if ($source === '' || $source === 'local') {
            $roomQuery = $hotel->rooms()->where('room_type_id', $roomTypeId);
            if ($roomId) {
                $roomQuery->where('id', (int) $roomId);
            }
            $room = $roomQuery->first() ?: $hotel->rooms()->where('room_type_id', $roomTypeId)->first();

            return (float) ($room->base_price ?? 0);
        }

        // For supplier hotels, price comes from search/book_token
        if (isset($data['book_token'])) {
            $tokenData = decrypt($data['book_token']);

            return (float) ($tokenData['price'] ?? $tokenData['unit_price'] ?? 0);
        }

        return 0;
    }

    /**
     * Prefer actionable create errors over a generic failure message.
     */
    protected function friendlyCreateFailureMessage(\Throwable $exception): string
    {
        $raw = $exception->getMessage();

        if (stripos($raw, 'status') !== false && (stripos($raw, 'enum') !== false || stripos($raw, 'truncated') !== false || stripos($raw, '1265') !== false)) {
            return 'Invalid booking status for hotel reservation. Please run pending database migrations.';
        }

        if (stripos($raw, 'Only ') === 0 && stripos($raw, 'available') !== false) {
            return $raw;
        }

        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return 'Hotel, room type, or rate plan was not found.';
        }

        if (config('app.debug') && $raw !== '') {
            return 'Failed to create hotel booking: '.$raw;
        }

        return 'Failed to create hotel booking';
    }

    /**
     * Normalize hotel booking status for comparisons (CONFIRMED / checked-in / CHECKED_IN → …).
     */
    protected function normalizeHotelStatus(?string $status): string
    {
        return strtolower(str_replace('_', '-', trim((string) $status)));
    }

    /**
     * Reserve shared hotel_inventory via mod_hr_{hotel_rooms.id}.
     *
     * @throws \Exception when fewer than $units rooms are available on any night.
     */
    protected function checkAndReserveInventory(int $moduleRoomId, Carbon $checkIn, Carbon $checkOut, int $units = 1): void
    {
        $apiRt = $this->resolveApiRoomTypeForModuleRoom($moduleRoomId);
        try {
            $this->sharedInventory->sell($apiRt, $checkIn, $checkOut, $units);
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }
    }

    /**
     * Merchant desk / local (or null source) hotels track capacity in shared hotel_inventory.
     *
     * @param  array<string, mixed>  $data
     */
    protected function tracksLocalInventory(Hotel $hotel, array $data): bool
    {
        // Hold confirm already moved held → sold on hotel_inventory.
        if (! empty($data['skip_inventory_reserve'])) {
            return false;
        }

        $source = strtolower(trim((string) ($hotel->source ?? '')));
        if ($source === '' || $source === 'local') {
            return true;
        }

        return ($data['platform'] ?? '') === 'merchant_desk';
    }

    /**
     * Merchant desk bookings must always reserve shared inventory (unless hold already did).
     *
     * @param  array<string, mixed>  $data
     */
    protected function requiresLocalInventory(array $data): bool
    {
        return ($data['platform'] ?? '') === 'merchant_desk'
            && empty($data['skip_inventory_reserve']);
    }

    /**
     * Resolve / upsert hotel_room_types row for module hotel_rooms.id.
     */
    protected function resolveApiRoomTypeForModuleRoom(int $moduleRoomId): HotelRoomType
    {
        $hotelRoom = HotelRoom::query()->find($moduleRoomId);
        if (! $hotelRoom) {
            throw new \Exception('Hotel room not found for inventory reservation.');
        }

        $code = 'mod_hr_'.$hotelRoom->id;
        $title = trim((string) ($hotelRoom->name ?? ''));
        if ($title === '') {
            $title = 'Room '.$hotelRoom->id;
        }

        $payload = [
            'title' => $title,
            'max_occupancy' => max(1, (int) ($hotelRoom->max_occupancy ?? 2)),
            'base_price_per_night' => (float) ($hotelRoom->base_price ?? 0),
            'currency' => 'BDT',
            'status' => 1,
        ];
        if (Schema::hasColumn((new HotelRoomType)->getTable(), 'is_active')) {
            $payload['is_active'] = 1;
        }

        return HotelRoomType::query()->updateOrCreate(
            [
                'hotel_id' => (int) $hotelRoom->hotel_id,
                'code' => $code,
            ],
            $payload,
        );
    }

    /**
     * Book with supplier API.
     */
    protected function bookWithSupplier(Booking $booking, array $data): array
    {
        $supplier = Supplier::findOrFail($booking->supplier_id);
        
        $supplierService = match($supplier->code) {
            'ratehawk' => new RateHawkSupplier($supplier),
            default => null,
        };

        if (!$supplierService) {
            return ['success' => false, 'message' => 'Unknown supplier'];
        }

        // Recheck rate before booking
        if (isset($data['book_token'])) {
            $tokenData = decrypt($data['book_token']);
            $rateKey = $tokenData['rate_key'] ?? null;

            if ($rateKey) {
                $recheck = $supplierService->recheckRate($rateKey);
                if (!$recheck['valid']) {
                    return ['success' => false, 'message' => 'Rate expired or invalid'];
                }
            }
        }

        // Prepare booking payload
        $payload = $this->prepareSupplierBookingPayload($booking, $data);

        return $supplierService->book($payload);
    }

    /**
     * Prepare payload for supplier booking API.
     */
    protected function prepareSupplierBookingPayload(Booking $booking, array $data): array
    {
        // This would be supplier-specific
        // Simplified for now
        return [
            'rate_key' => $data['rate_key'] ?? null,
            'customer' => [
                'first_name' => $booking->customer->name ?? '',
                'last_name' => '',
                'email' => $booking->customer->email ?? '',
            ],
            'rooms' => [],
        ];
    }

    /**
     * Cancel a hotel booking.
     */
    public function cancel($id, $reason = null)
    {
        try {
            $booking = Booking::where('service_type', 'hotel')
                ->with(['hotelItems.hotel', 'payment'])
                ->findOrFail($id);
            
            if ($this->normalizeHotelStatus($booking->status) === 'cancelled') {
                return response()->failed(['message' => 'Booking is already cancelled']);
            }

            DB::beginTransaction();

            // Cancel with supplier if needed
            if ($booking->supplier_id && $booking->supplier_booking_reference) {
                $supplier = Supplier::find($booking->supplier_id);
                if ($supplier) {
                    $supplierService = match($supplier->code) {
                        'ratehawk' => new RateHawkSupplier($supplier),
                        default => null,
                    };

                    if ($supplierService) {
                        $result = $supplierService->cancel($booking->supplier_booking_reference);
                        if (!$result['success']) {
                            DB::rollBack();
                            return response()->failed(['message' => 'Supplier cancellation failed']);
                        }
                    }
                }
            }

            // Release inventory for local / merchant-tracked hotels
            foreach ($booking->hotelItems as $item) {
                $hotel = $item->hotel;
                if (! $hotel) {
                    continue;
                }
                $source = strtolower(trim((string) ($hotel->source ?? '')));
                if ($source === '' || $source === 'local') {
                    $this->releaseInventory($item);
                }
            }

            $cancellation = $this->createHotelCancellationRecord($booking, $reason);

            $booking->update([
                'status' => AppConst::BOOKING_CANCELLED,
            ]);

            DB::commit();

            if ($cancellation && (float) ($cancellation->refund_amount ?? $cancellation->total_refundable ?? 0) > 0) {
                dispatch(new \App\Jobs\RefundExecutionJob((int) $cancellation->id));
            }

            BusinessMetrics::recordBooking('hotel', 'cancelled');

            return response()->success($booking->fresh(['hotelItems.hotel']), 'Booking cancelled successfully');
        } catch (\Exception $exception) {
            DB::rollBack();
            BusinessMetrics::recordBooking('hotel', 'cancel_failed');
            LogHelper::exception($exception, [
                'keyword' => 'HOTEL_BOOKING_CANCEL_EXCEPTION',
                'booking_id' => $id
            ]);
            return response()->failed(['message' => 'Failed to cancel booking']);
        }
    }

    /**
     * Create a booking_cancellations audit row with merchant tier refund math.
     */
    private function createHotelCancellationRecord(Booking $booking, ?string $reason = null): ?\App\Models\BookingCancellation
    {
        $hotelItem = $booking->hotelItems->first();
        $merchantId = $hotelItem?->hotel?->merchant_id ? (int) $hotelItem->hotel->merchant_id : null;
        $checkIn = $hotelItem?->check_in_date ?? $booking->from_date;
        $eventAt = $checkIn ? Carbon::parse((string) $checkIn)->startOfDay() : null;

        $context = app(\App\Services\CancellationPolicyContext::class);
        $policy = app(\App\Services\MerchantCancellationPolicyResolver::class);
        $vatRefundable = $context->isVatRefundable($merchantId);
        $chargeRefundable = $context->isChargeRefundable($merchantId);

        $base = (float) ($booking->total_payable ?? 0);
        if (! $vatRefundable) {
            $base -= (float) ($booking->vat_total ?? 0);
        }
        if (! $chargeRefundable) {
            $base -= (float) ($booking->charge_total ?? 0);
        }
        $base = max(0, $base);

        $refundPercent = $eventAt
            ? $policy->refundPercent($merchantId, $eventAt, 'hotel')
            : 0.0;
        $refundable = round($base * $refundPercent / 100, 2);

        $dues = (float) data_get($booking, 'payment.dues', 0);
        $refundAmount = 0.0;
        if ($dues == 0) {
            $refundAmount = $refundable;
        } elseif ($dues < $refundable) {
            $refundAmount = $refundable - $dues;
        }

        $policySnapshot = [
            'service_type' => 'hotel',
            'reason' => $reason,
            'computed_at' => now()->toIso8601String(),
            'items' => [[
                'hotel_item_id' => $hotelItem?->id,
                'merchant_id' => $merchantId,
                'event_at' => $eventAt?->toIso8601String(),
                'base_amount' => $base,
                'refund_percent' => $refundPercent,
                'refundable_amount' => $refundable,
                'vat_refundable' => $vatRefundable,
                'charge_refundable' => $chargeRefundable,
            ]],
        ];

        return \App\Models\BookingCancellation::create([
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'user_id' => auth()->id(),
            'type' => 'hotel',
            'service_type' => 'hotel',
            'items' => '',
            'transaction_id' => uniqid('hotel_cancel_', true),
            'vat_refundable' => (int) $vatRefundable,
            'charge_refundable' => (int) $chargeRefundable,
            'total_refundable' => $refundable,
            'refund_percent_applied' => $refundPercent,
            'policy_snapshot' => $policySnapshot,
            'refund_amount' => $refundAmount,
            'status' => AppConst::CANCELLATION_APPROVED,
        ]);
    }

    /**
     * Mark a confirmed hotel booking as checked-in.
     *
     * Expects optional/required guest identity payload:
     * rooms: [{ hotel_item_id, guests: [{ name?, nid, mobile }] }]
     * Every booked room must include at least one guest with nid + mobile.
     *
     * @param  array<string, mixed>  $data
     */
    public function checkIn($id, array $data = [])
    {
        try {
            $booking = Booking::where('service_type', 'hotel')
                ->with(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan'])
                ->findOrFail($id);

            $status = $this->normalizeHotelStatus($booking->status);
            if (in_array($status, ['cancelled', 'checked-out', 'failed'], true)) {
                return response()->failed(['message' => 'This booking cannot be checked in.']);
            }
            if ($status === 'checked-in') {
                return response()->success($booking, 'Guest is already checked in.');
            }

            $guestMap = $this->validateAndNormalizeCheckInGuests($booking, $data);
            if ($guestMap instanceof \Illuminate\Http\JsonResponse) {
                return $guestMap;
            }

            DB::beginTransaction();

            foreach ($booking->hotelItems as $item) {
                $item->guests = $guestMap[(int) $item->id] ?? [];
                $item->save();
            }

            $booking->update(['status' => AppConst::BOOKING_CHECKED_IN]);
            DB::commit();

            $booking->load(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan', 'customer']);

            return response()->success($booking, 'Guest checked in.');
        } catch (\Exception $exception) {
            DB::rollBack();
            LogHelper::exception($exception, [
                'keyword' => 'HOTEL_BOOKING_CHECKIN_EXCEPTION',
                'booking_id' => $id,
            ]);

            return response()->failed(['message' => 'Failed to check in guest']);
        }
    }

    /**
     * Validate check-in guest identity: every room needs ≥1 guest with NID + mobile.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, list<array{name: ?string, nid: string, mobile: string}>>|\Illuminate\Http\JsonResponse
     */
    protected function validateAndNormalizeCheckInGuests(Booking $booking, array $data)
    {
        $items = $booking->hotelItems;
        if ($items->isEmpty()) {
            return response()->failed(['message' => 'This booking has no rooms to check in.']);
        }

        $roomsInput = $data['rooms'] ?? null;
        if (! is_array($roomsInput) || $roomsInput === []) {
            return response()->failed([
                'message' => 'Check-in requires guest NID and mobile for every room.',
            ]);
        }

        $byItemId = [];
        foreach ($roomsInput as $index => $roomRow) {
            if (! is_array($roomRow)) {
                continue;
            }
            $itemId = (int) ($roomRow['hotel_item_id'] ?? $roomRow['id'] ?? 0);
            if ($itemId <= 0) {
                return response()->failed([
                    'message' => 'Room #'.($index + 1).' is missing hotel_item_id.',
                ]);
            }
            $guestsRaw = $roomRow['guests'] ?? null;
            if (! is_array($guestsRaw) || $guestsRaw === []) {
                return response()->failed([
                    'message' => 'Each room needs at least one guest with NID and mobile number.',
                ]);
            }

            $normalizedGuests = [];
            foreach ($guestsRaw as $guestIndex => $guest) {
                if (! is_array($guest)) {
                    continue;
                }
                $nid = strtoupper(preg_replace('/\s+/', '', trim((string) ($guest['nid'] ?? $guest['nid_no'] ?? ''))) ?? '');
                $mobile = trim((string) ($guest['mobile'] ?? $guest['phone'] ?? ''));
                $name = trim((string) ($guest['name'] ?? ''));

                if ($nid === '' && $mobile === '' && $name === '') {
                    continue;
                }

                if ($nid === '' || ! preg_match('/^[0-9A-Z]{8,20}$/', $nid)) {
                    return response()->failed([
                        'message' => 'Room #'.($index + 1).' guest #'.($guestIndex + 1).': enter a valid NID.',
                    ]);
                }

                $mobileDigits = preg_replace('/[\s()-]/', '', $mobile) ?? '';
                if ($mobileDigits === '' || ! preg_match('/^\+?\d{8,15}$/', $mobileDigits)) {
                    return response()->failed([
                        'message' => 'Room #'.($index + 1).' guest #'.($guestIndex + 1).': enter a valid mobile number.',
                    ]);
                }

                $normalizedGuests[] = [
                    'name' => $name !== '' ? mb_substr($name, 0, 191) : null,
                    'nid' => $nid,
                    'mobile' => $mobileDigits,
                ];
            }

            if ($normalizedGuests === []) {
                return response()->failed([
                    'message' => 'Each room needs at least one guest with NID and mobile number.',
                ]);
            }

            $byItemId[$itemId] = $normalizedGuests;
        }

        foreach ($items as $item) {
            if (! isset($byItemId[(int) $item->id])) {
                $label = $item->roomType?->name ?: ('room #'.$item->id);

                return response()->failed([
                    'message' => "Missing guest NID and mobile for {$label}. Every room requires at least one guest.",
                ]);
            }
        }

        $knownIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach (array_keys($byItemId) as $itemId) {
            if (! in_array((int) $itemId, $knownIds, true)) {
                return response()->failed(['message' => 'One or more rooms do not belong to this booking.']);
            }
        }

        return $byItemId;
    }

    /**
     * Check out a guest and automatically free remaining reserved nights.
     */
    public function checkOut($id, $checkoutDate = null)
    {
        try {
            $booking = Booking::where('service_type', 'hotel')
                ->with(['hotelItems.hotel', 'payment'])
                ->findOrFail($id);

            $status = $this->normalizeHotelStatus($booking->status);
            if (in_array($status, ['cancelled', 'failed'], true)) {
                return response()->failed(['message' => 'This booking cannot be checked out.']);
            }
            if ($status === 'checked-out') {
                return response()->success($booking, 'Guest is already checked out.');
            }

            $due = $this->outstandingDues($booking);
            if ($due > 0.009) {
                return response()->failed([
                    'message' => 'Guest still owes '.number_format($due, 2).'. Collect dues before check-out.',
                ]);
            }

            $actualCheckout = $checkoutDate
                ? Carbon::parse($checkoutDate)->startOfDay()
                : Carbon::today()->startOfDay();

            DB::beginTransaction();

            foreach ($booking->hotelItems as $item) {
                if (($item->hotel->source ?? null) !== 'local') {
                    continue;
                }

                $originalCheckout = Carbon::parse($item->check_out_date)->startOfDay();
                // Free nights from actual checkout through original planned checkout.
                if ($actualCheckout->lt($originalCheckout)) {
                    $this->releaseInventory($item, $actualCheckout, $originalCheckout);
                }

                if ($actualCheckout->lt($originalCheckout)) {
                    $item->check_out_date = $actualCheckout->format('Y-m-d');
                    $nights = Carbon::parse($item->check_in_date)->diffInDays($actualCheckout);
                    $item->nights = max(0, $nights);
                    $item->save();
                }
            }

            $booking->update([
                'status' => AppConst::BOOKING_CHECKED_OUT,
                'to_date' => $actualCheckout->format('Y-m-d'),
            ]);

            DB::commit();

            $booking->load(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan', 'customer']);

            return response()->success($booking, 'Guest checked out. Remaining nights are available again.');
        } catch (\Exception $exception) {
            DB::rollBack();
            LogHelper::exception($exception, [
                'keyword' => 'HOTEL_BOOKING_CHECKOUT_EXCEPTION',
                'booking_id' => $id,
            ]);

            return response()->failed(['message' => 'Failed to check out guest']);
        }
    }

    /**
     * Extend stay nights on one or more booked rooms when inventory is available.
     *
     * @param  array{new_check_out: string, hotel_item_ids?: list<int>}  $data
     */
    public function extendStay(Booking $booking, array $data)
    {
        try {
            $booking->loadMissing(['hotelItems.hotel', 'payment']);
            $status = $this->normalizeHotelStatus($booking->status);
            if (in_array($status, ['cancelled', 'checked-out', 'failed'], true)) {
                return response()->failed(['message' => 'This booking cannot be extended.']);
            }

            $newCheckOut = Carbon::parse((string) ($data['new_check_out'] ?? ''))->startOfDay();
            $itemIds = array_values(array_filter(array_map('intval', $data['hotel_item_ids'] ?? [])));
            $items = $booking->hotelItems;
            if ($itemIds !== []) {
                $items = $items->whereIn('id', $itemIds)->values();
            }
            if ($items->isEmpty()) {
                return response()->failed(['message' => 'No rooms selected to extend.']);
            }

            DB::beginTransaction();

            $extraCharge = 0.0;
            foreach ($items as $item) {
                $oldCheckOut = Carbon::parse($item->check_out_date)->startOfDay();
                if (! $newCheckOut->gt($oldCheckOut)) {
                    DB::rollBack();

                    return response()->failed([
                        'message' => 'New check-out must be after the current check-out ('.$oldCheckOut->toDateString().').',
                    ]);
                }

                $extraNights = (int) $oldCheckOut->diffInDays($newCheckOut);
                if ($extraNights < 1) {
                    continue;
                }

                $moduleRoomId = (int) ($item->room_id ?? 0);
                $hotel = $item->hotel;
                if ($moduleRoomId > 0 && $hotel && $this->tracksLocalInventory($hotel, ['platform' => 'merchant_desk'])) {
                    try {
                        $this->checkAndReserveInventory($moduleRoomId, $oldCheckOut, $newCheckOut, 1);
                    } catch (\Exception $e) {
                        DB::rollBack();

                        return response()->failed([
                            'message' => $e->getMessage() ?: 'Room not available for the extra nights.',
                        ]);
                    }
                }

                $unitPrice = (float) ($item->unit_price ?? 0);
                $addAmount = round($unitPrice * $extraNights, 2);
                $item->check_out_date = $newCheckOut->toDateString();
                $item->nights = (int) Carbon::parse($item->check_in_date)->diffInDays($newCheckOut);
                $item->total_price = round((float) $item->total_price + $addAmount, 2);
                $item->save();
                $extraCharge += $addAmount;
            }

            $booking->unsetRelation('hotelItems');
            $booking->load('hotelItems');
            $maxOut = $booking->hotelItems
                ->map(fn ($row) => Carbon::parse($row->check_out_date)->startOfDay())
                ->sort()
                ->last();
            if ($maxOut) {
                $booking->to_date = $maxOut->toDateString();
            }

            $this->applyPayableDelta($booking, $extraCharge);
            DB::commit();

            $booking->load(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan', 'customer', 'payment', 'collections.supervisor']);

            return response()->success($booking, 'Stay extended. Extra charge: '.number_format($extraCharge, 2));
        } catch (\Exception $exception) {
            DB::rollBack();
            LogHelper::exception($exception, [
                'keyword' => 'HOTEL_BOOKING_EXTEND_EXCEPTION',
                'booking_id' => $booking->id,
            ]);

            return response()->failed(['message' => 'Failed to extend stay']);
        }
    }

    /**
     * Add more rooms to an existing hotel booking.
     *
     * @param  array{rooms: list<array<string,mixed>>, check_in_date?: string, check_out_date?: string, adults?: int, children?: int}  $data
     */
    public function addRooms(Booking $booking, array $data)
    {
        try {
            $booking->loadMissing(['hotelItems.hotel', 'payment']);
            $status = $this->normalizeHotelStatus($booking->status);
            if (in_array($status, ['cancelled', 'checked-out', 'failed'], true)) {
                return response()->failed(['message' => 'Cannot add rooms to this booking.']);
            }

            $rooms = $data['rooms'] ?? null;
            if (! is_array($rooms) || $rooms === []) {
                return response()->failed(['message' => 'At least one room is required.']);
            }

            $checkIn = Carbon::parse((string) ($data['check_in_date'] ?? $booking->from_date))->startOfDay();
            $checkOut = Carbon::parse((string) ($data['check_out_date'] ?? $booking->to_date))->startOfDay();
            if (! $checkOut->gt($checkIn)) {
                return response()->failed(['message' => 'Check-out date must be after check-in date.']);
            }
            $nights = (int) $checkIn->diffInDays($checkOut);

            DB::beginTransaction();

            $addedAmount = 0.0;
            $localUnitsByRoomProduct = [];

            foreach ($rooms as $roomData) {
                if (! is_array($roomData)) {
                    continue;
                }

                // One expanded row = one room (controller expands quantity like walk-in create).
                $hotelId = (int) ($roomData['hotel_id'] ?? 0);
                $roomTypeId = (int) ($roomData['room_type_id'] ?? 0);
                $ratePlanId = (int) ($roomData['rate_plan_id'] ?? 0);
                $moduleRoomId = (int) ($roomData['room_id'] ?? 0);
                $adults = (int) ($roomData['adults'] ?? $data['adults'] ?? 1);
                $children = (int) ($roomData['children'] ?? $data['children'] ?? 0);
                $childrenAges = $roomData['children_ages'] ?? [];

                $hotel = Hotel::findOrFail($hotelId);
                $ratePlan = RoomRatePlan::findOrFail($ratePlanId);
                $unitPrice = $this->getUnitPrice($hotel, $roomTypeId, $ratePlan, $data, $moduleRoomId ?: null);

                $childPrice = 0.0;
                if ($children > 0 && ! empty($childrenAges)) {
                    $childRuleEngine = new ChildRuleEngine($hotel, $ratePlan, $unitPrice, $childrenAges, $nights);
                    $validation = $childRuleEngine->validate();
                    if (! $validation['valid']) {
                        DB::rollBack();

                        return response()->failed(['message' => implode(', ', $validation['errors'])]);
                    }
                    $childPrice = $childRuleEngine->calculateChildPrice();
                }

                $roomTotal = ($unitPrice * $nights) + $childPrice;
                $addedAmount += $roomTotal;

                if ($this->tracksLocalInventory($hotel, ['platform' => 'merchant_desk'])) {
                    if ($moduleRoomId <= 0) {
                        DB::rollBack();

                        return response()->failed(['message' => 'room_id is required to reserve shared inventory.']);
                    }
                    $localUnitsByRoomProduct[$moduleRoomId] = ($localUnitsByRoomProduct[$moduleRoomId] ?? 0) + 1;
                }

                $this->bookingHotelItemRepository->create([
                    'booking_id' => $booking->id,
                    'hotel_id' => $hotelId,
                    'room_id' => $moduleRoomId ?: null,
                    'room_type_id' => $roomTypeId,
                    'rate_plan_id' => $ratePlanId,
                    'check_in_date' => $checkIn->format('Y-m-d'),
                    'check_out_date' => $checkOut->format('Y-m-d'),
                    'nights' => $nights,
                    'adults' => $adults,
                    'children' => $children,
                    'children_ages' => $childrenAges,
                    'unit_price' => $unitPrice,
                    'child_price' => $childPrice,
                    'total_price' => $roomTotal,
                    'supplier' => $hotel->source,
                ]);
            }

            foreach ($localUnitsByRoomProduct as $moduleRoomId => $units) {
                try {
                    $this->checkAndReserveInventory((int) $moduleRoomId, $checkIn, $checkOut, (int) $units);
                } catch (\Exception $e) {
                    DB::rollBack();

                    return response()->failed(['message' => $e->getMessage() ?: 'Room not available for selected dates']);
                }
            }

            $from = Carbon::parse((string) $booking->from_date)->startOfDay();
            $to = Carbon::parse((string) $booking->to_date)->startOfDay();
            if ($checkIn->lt($from)) {
                $booking->from_date = $checkIn->toDateString();
            }
            if ($checkOut->gt($to)) {
                $booking->to_date = $checkOut->toDateString();
            }

            $this->applyPayableDelta($booking, $addedAmount);
            DB::commit();

            $booking->load(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan', 'customer', 'payment', 'collections.supervisor']);

            return response()->success($booking, 'Rooms added. Extra charge: '.number_format($addedAmount, 2));
        } catch (\Exception $exception) {
            DB::rollBack();
            LogHelper::exception($exception, [
                'keyword' => 'HOTEL_BOOKING_ADD_ROOMS_EXCEPTION',
                'booking_id' => $booking->id,
            ]);

            return response()->failed(['message' => $exception->getMessage() ?: 'Failed to add rooms']);
        }
    }

    /**
     * Increase booking payable and payment dues after customize actions.
     */
    protected function applyPayableDelta(Booking $booking, float $delta): void
    {
        if ($delta <= 0.009) {
            $booking->save();

            return;
        }

        $booking->total_amount = round((float) $booking->total_amount + $delta, 2);
        $booking->total_payable = round((float) $booking->total_payable + $delta, 2);
        $booking->payment_status = 0;
        $booking->save();

        $payment = $booking->payment;
        if (! $payment) {
            Payment::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'paid_amount' => 0,
                'dues' => (float) $booking->total_payable,
                'payment_method' => 'pay_later',
                'payment_gateway' => 'pay_later',
                'status' => 'pending',
            ]);

            return;
        }

        $paid = (float) ($payment->paid_amount ?? 0);
        $payment->dues = max(0.0, round((float) $booking->total_payable - $paid, 2));
        $payment->status = $payment->dues < 0.01 ? AppConst::PAYMENT_SUCCESS : 'pending';
        $payment->save();
    }

    /**
     * Release shared hotel_inventory for cancelled / shortened stays.
     */
    protected function releaseInventory($bookingItem, ?Carbon $from = null, ?Carbon $toExclusive = null)
    {
        $checkIn = Carbon::parse($bookingItem->check_in_date)->startOfDay();
        $checkOut = Carbon::parse($bookingItem->check_out_date)->startOfDay();
        $from = ($from ?? $checkIn)->copy()->startOfDay();
        $toExclusive = ($toExclusive ?? $checkOut)->copy()->startOfDay();

        if ($from->lt($checkIn)) {
            $from = $checkIn->copy();
        }
        if ($toExclusive->gt($checkOut)) {
            $toExclusive = $checkOut->copy();
        }
        if ($from->gte($toExclusive)) {
            return;
        }

        $moduleRoomId = (int) ($bookingItem->room_id ?? 0);
        if ($moduleRoomId <= 0) {
            return;
        }

        try {
            $apiRt = $this->resolveApiRoomTypeForModuleRoom($moduleRoomId);
            $this->sharedInventory->revertSold($apiRt, $from, $toExclusive, 1);
        } catch (\Throwable $e) {
            // Best-effort release; do not block cancel/checkout on inventory projection issues.
            LogHelper::exception($e, [
                'keyword' => 'HOTEL_SHARED_INVENTORY_RELEASE',
                'room_id' => $moduleRoomId,
            ]);
        }
    }

    /**
     * Apply desk payment options (full / partial / none) when confirming a hotel booking.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    protected function applyDeskPayment(Booking $booking, float $totalPayable, array $data): void
    {
        $paymentInput = is_array($data['payment'] ?? null) ? $data['payment'] : [];
        $mode = strtolower(trim((string) ($paymentInput['mode'] ?? 'none')));
        if (! in_array($mode, ['full', 'partial', 'none'], true)) {
            $mode = 'none';
        }

        $method = $this->normalizeDeskPaymentMethod($paymentInput['method'] ?? null, $mode === 'none' ? 'pay_later' : 'cash');

        if ($mode === 'none') {
            $paid = 0.0;
            $method = 'pay_later';
        } elseif ($mode === 'full') {
            $paid = $totalPayable;
        } else {
            $paid = min(max(0.0, (float) ($paymentInput['amountPaid'] ?? $paymentInput['amount_paid'] ?? 0)), $totalPayable);
            if ($paid <= 0.009 || $paid >= $totalPayable - 0.009) {
                throw new \InvalidArgumentException('Partial payment must be greater than zero and less than total payable.');
            }
        }

        $dues = max(0.0, $totalPayable - $paid);
        $paymentStatus = $dues < 0.01 ? AppConst::PAYMENT_SUCCESS : 'pending';

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'paid_amount' => $paid,
            'store_amount' => $paid,
            'dues' => $dues,
            'payment_method' => $method,
            'payment_gateway' => $method,
            'status' => $paymentStatus,
            'channel' => in_array($method, ['cash', 'bank_check', 'bank_transfer', 'pay_later'], true) ? 'offline' : 'merchant',
        ]);

        if ($paid > 0.009) {
            $actorId = (int) (auth()->id() ?? 0);
            $evidence = $this->normalizePaymentEvidence($method, $paymentInput);
            $this->assertPaymentEvidence($method, $evidence);
            if ($actorId > 0) {
                PaymentCollector::create($this->paymentCollectorAttributes(
                    $booking->id,
                    $payment->id,
                    $actorId,
                    $paid,
                    $method,
                    $evidence,
                ));
            }
            $this->applyPaymentEvidenceToPayment($payment, $evidence);
            $payment->save();
        }

        $booking->payment_status = $dues < 0.01 ? 1 : 0;
        $booking->save();
        $booking->setRelation('payment', $payment);
    }

    /**
     * Record an additional collection against an existing hotel booking.
     *
     * @param  array{transaction_id?: string, account_no?: string, remarks?: string}  $evidence
     * @return array{success: bool, message: string, booking?: Booking, collected_amount?: float}
     */
    public function collectPayment(Booking $booking, float $amount, ?string $method, int $actorId, array $evidence = []): array
    {
        if (strtoupper((string) $booking->status) === AppConst::BOOKING_CANCELLED) {
            return ['success' => false, 'message' => 'Cannot collect on a cancelled booking'];
        }

        $total = (float) ($booking->total_payable ?? $booking->total_amount);
        $payment = $booking->payment;
        if (! $payment) {
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'paid_amount' => 0,
                'dues' => max(0, $total),
                'payment_method' => 'pay_later',
                'payment_gateway' => 'pay_later',
                'status' => 'pending',
            ]);
            $booking->setRelation('payment', $payment);
        }

        $paidBefore = (float) ($payment->paid_amount ?? 0);
        $dueBefore = max(0.0, $total - $paidBefore);
        if ($dueBefore < 0.01) {
            return ['success' => false, 'message' => 'No balance due'];
        }

        $amount = min($amount, $dueBefore);
        if ($amount < 0.01) {
            return ['success' => false, 'message' => 'Invalid amount'];
        }

        $method = $this->normalizeDeskPaymentMethod($method, (string) ($payment->payment_method ?: 'cash'));
        $evidence = $this->normalizePaymentEvidence($method, $evidence);
        try {
            $this->assertPaymentEvidence($method, $evidence);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        $collected = $amount;

        DB::transaction(function () use ($booking, $payment, $total, $collected, $method, $actorId, $evidence) {
            $newPaid = (float) $payment->paid_amount + $collected;
            $newDues = max(0.0, $total - $newPaid);
            $payment->paid_amount = $newPaid;
            $payment->store_amount = $newPaid;
            $payment->dues = $newDues;
            $payment->payment_method = $method;
            $payment->payment_gateway = $method;
            $payment->status = $newDues < 0.01 ? AppConst::PAYMENT_SUCCESS : 'pending';
            $this->applyPaymentEvidenceToPayment($payment, $evidence);
            $payment->save();

            if ($actorId > 0) {
                PaymentCollector::create($this->paymentCollectorAttributes(
                    $booking->id,
                    $payment->id,
                    $actorId,
                    $collected,
                    $method,
                    $evidence,
                ));
            }

            $booking->payment_status = $newDues < 0.01 ? 1 : 0;
            $booking->save();
        });

        $booking = $booking->fresh([
            'hotelItems.hotel',
            'hotelItems.roomType',
            'hotelItems.ratePlan',
            'customer',
            'payment',
            'collections.supervisor',
        ]);

        $due = $this->outstandingDues($booking);

        return [
            'success' => true,
            'message' => $due < 0.01 ? 'Payment complete.' : 'Payment recorded.',
            'booking' => $booking,
            'collected_amount' => $collected,
        ];
    }

    public function outstandingDues(Booking $booking): float
    {
        $booking->loadMissing('payment');
        $total = (float) ($booking->total_payable ?? $booking->total_amount ?? 0);
        $paid = (float) ($booking->payment?->paid_amount ?? 0);

        return max(0.0, round($total - $paid, 2));
    }

    protected function normalizeDeskPaymentMethod(mixed $method, string $default): string
    {
        $m = is_string($method) ? strtolower(trim($method)) : '';
        $allowed = ['cash', 'card', 'bkash', 'nagad', 'bank_check', 'bank_transfer', 'pay_later'];

        return in_array($m, $allowed, true) ? $m : (in_array($default, $allowed, true) ? $default : 'cash');
    }

    /**
     * Card / mobile wallet / bank desk collections require settlement evidence.
     *
     * @return list<string>
     */
    public function methodsRequiringPaymentEvidence(): array
    {
        return ['card', 'bkash', 'nagad', 'bank_check', 'bank_transfer'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{transaction_id: string, account_no: string, remarks: string}
     */
    protected function normalizePaymentEvidence(string $method, array $input): array
    {
        return [
            'transaction_id' => trim((string) ($input['transaction_id'] ?? $input['trx_id'] ?? $input['bank_tran_id'] ?? '')),
            'account_no' => trim((string) ($input['account_no'] ?? $input['account_number'] ?? $input['wallet_no'] ?? $input['card_no'] ?? '')),
            'remarks' => trim((string) ($input['remarks'] ?? $input['note'] ?? '')),
        ];
    }

    /**
     * @param  array{transaction_id: string, account_no: string, remarks: string}  $evidence
     */
    protected function assertPaymentEvidence(string $method, array $evidence): void
    {
        if (! in_array($method, $this->methodsRequiringPaymentEvidence(), true)) {
            return;
        }
        if ($evidence['transaction_id'] === '') {
            throw new \InvalidArgumentException('Transaction ID is required for '.$method.' payments.');
        }
        if ($evidence['account_no'] === '') {
            throw new \InvalidArgumentException('Wallet / card / account number is required for '.$method.' payments.');
        }
        if ($evidence['remarks'] === '') {
            throw new \InvalidArgumentException('Remarks are required for '.$method.' payments.');
        }
    }

    /**
     * @param  array{transaction_id: string, account_no: string, remarks: string}  $evidence
     * @return array<string, mixed>
     */
    protected function paymentCollectorAttributes(
        int $bookingId,
        int $paymentId,
        int $supervisorId,
        float $amount,
        string $method,
        array $evidence,
    ): array {
        $row = [
            'booking_id' => $bookingId,
            'payment_id' => $paymentId,
            'supervisor_id' => $supervisorId,
            'amount' => $amount,
            'payment_type' => $method,
            'remarks' => $evidence['remarks'] !== '' ? $evidence['remarks'] : null,
        ];
        if (Schema::hasColumn('payment_collectors', 'transaction_id')) {
            $row['transaction_id'] = $evidence['transaction_id'] !== '' ? $evidence['transaction_id'] : null;
        }
        if (Schema::hasColumn('payment_collectors', 'account_no')) {
            $row['account_no'] = $evidence['account_no'] !== '' ? $evidence['account_no'] : null;
        }

        return $row;
    }

    /**
     * Mirror latest collection evidence onto the payment row for reporting.
     *
     * @param  array{transaction_id: string, account_no: string, remarks: string}  $evidence
     */
    protected function applyPaymentEvidenceToPayment(Payment $payment, array $evidence): void
    {
        if ($evidence['transaction_id'] !== '') {
            $payment->bank_tran_id = $evidence['transaction_id'];
            $payment->external_reference = $evidence['transaction_id'];
            if (empty($payment->transaction_id)) {
                $payment->transaction_id = $evidence['transaction_id'];
            }
        }
        if ($evidence['account_no'] !== '') {
            $payment->account_no = $evidence['account_no'];
        }
    }
}
