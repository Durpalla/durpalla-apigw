<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Party;
use App\Models\Payment;
use App\Models\ScheduleCabinMapping;
use App\Models\WalletTransaction;
use App\Support\AuthActor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates CONFIRMED transport bookings for an API-reseller party, settled from
 * its prepaid wallet.
 *
 * The wallet is debited the NET amount (total_payable - reseller_commission),
 * where the reseller commission is a share of Durpalla's own commission. The
 * booking draws from Durpalla public quota (booking_party = 'durpalla') and is
 * attributed to the reseller via party_id.
 */
class ResellerBookingService
{
    public function __construct(
        private readonly ResellerWalletService $wallet,
        private readonly ResellerCommissionService $commission,
        private readonly CalculationService $calculation,
    ) {
    }

    /**
     * @param array{customer_name?:string, customer_mobile:string, customer_email?:string, items:array<int,array{item_id:int}>} $data
     */
    public function create(Party $partner, array $data): Booking
    {
        $items = array_values(array_filter($data['items'] ?? [], fn ($i) => ! empty($i['item_id'])));
        if (empty($items)) {
            throw new \InvalidArgumentException('No bookable items provided.');
        }

        $mappingIds = array_map(fn ($i) => (int) $i['item_id'], $items);

        return DB::transaction(function () use ($partner, $data, $mappingIds) {
            $mappings = ScheduleCabinMapping::with(['schedule.vehicle.merchant', 'schedule.startFrom', 'schedule.stopTo', 'cabinType', 'cabin'])
                ->whereIn('id', $mappingIds)
                ->lockForUpdate()
                ->get();

            if ($mappings->count() !== count($mappingIds)) {
                throw new \RuntimeException('One or more selected items could not be found.');
            }

            foreach ($mappings as $mapping) {
                $this->assertPubliclySellable($mapping);
            }

            $customer = $this->resolveCustomer($data);

            $booking = new Booking([
                'booking_date' => date('Y-m-d'),
                'customer_id' => $customer->id,
                'total_amount' => 0,
                'total_discount' => 0,
                'total_payable' => 0,
                'vat_amount' => abs((float) getOption('vat_amount', 0)),
                'vat_total' => 0,
                'charge_amount' => abs((float) getOption('service_charge_web', 0)),
                'charge_total' => 0,
                'booking_party' => AppConst::PARTY_DURPALLA,
                'party_id' => $partner->id,
                'platform' => 'reseller_api',
                'status' => AppConst::BOOKING_PENDING,
                'payment_status' => 0,
                'service_type' => 'transport',
                'payment_token' => (string) Str::uuid(),
            ]);
            AuthActor::setBookedBy($booking, $partner);
            $booking->save();

            $totalAmount = 0;
            $vatTotal = 0;
            $chargeTotal = 0;
            $tripDates = [];
            $commissionItems = [];

            foreach ($mappings as $mapping) {
                $schedule = $mapping->schedule;
                $merchant = $schedule->vehicle['merchant'] ?? null;
                $vatApplicableTo = $this->calculation->resolveVatApplicableTo();
                $charges = $this->calculation->getCharges([
                    'fare' => $mapping->fare,
                    'price' => $mapping->fare,
                    'service_charge' => $mapping->service_charge,
                    'service_charge_type' => $mapping->service_charge_type ?? 'percent',
                    'merchant_service_charge' => is_object($merchant)
                        ? $merchant->getAttribute('service_charge')
                        : ($merchant['service_charge'] ?? null),
                    'merchant_service_charge_type' => is_object($merchant)
                        ? ($merchant->getAttribute('service_charge_type') ?? 'percent')
                        : ($merchant['service_charge_type'] ?? 'percent'),
                ], 'web');
                $chargeAmount = $charges['amount'];
                $chargeTypeVal = $charges['type'];

                $price = (float) $mapping->fare;
                $charge = (float) $charges['total'];
                $vat = $this->calculation->vatOnCharge($charge);

                $totalAmount += $price;
                $vatTotal += $vat;
                $chargeTotal += $charge;
                $tripDates[] = $schedule->schedule_date;

                $commissionItems[] = [
                    'merchant_id' => (int) ($schedule->merchant_id ?? $mapping->merchant_id ?? 0),
                    'price' => $price,
                    'discount' => 0,
                    'is_honorium' => (bool) $mapping->is_honorium,
                    'honorium_charge' => (float) ($merchant['honorium_service_charge'] ?? 0),
                    'honorium_type' => (string) ($merchant['honorium_type'] ?? 'percent'),
                ];

                $passenger = [
                    'type' => 'other',
                    'name' => $customer->name,
                    'mobile' => $customer->mobile,
                    'person' => $mapping->cabinType['capacity'] ?? 1,
                ];

                $booking->bookingItems()->create([
                    'vehicle_id' => $mapping->vehicle_id,
                    'customer_id' => $customer->id,
                    'booking_type' => $mapping->type,
                    'cabin_id' => $mapping->cabin_id,
                    'mapping_id' => $mapping->id,
                    'price' => $price,
                    'vat_applicable_to' => $vatApplicableTo,
                    'route_name' => ($schedule->startFrom['name'] ?? '').' - '.($schedule->stopTo['name'] ?? ''),
                    'trip_id' => $mapping->schedule_id,
                    'trip_date' => $schedule->schedule_date,
                    'booking_date' => date('Y-m-d'),
                    'discount' => 0,
                    'passenger' => $passenger,
                    'vat_amount' => abs((float) getOption('vat_amount', 0)),
                    'charge_amount' => $chargeAmount,
                    'charge_type' => $chargeTypeVal,
                    'status' => AppConst::BOOKING_ITEM_ACTIVE,
                    'is_honorium' => (int) $mapping->is_honorium,
                    'honorium_charge' => $merchant['honorium_service_charge'] ?? 0,
                    'honorium_type' => $merchant['honorium_type'] ?? 'percent',
                    'booking_party' => AppConst::PARTY_DURPALLA,
                    'party_id' => $partner->id,
                    'incentive' => 0,
                    'incentive_type' => 'percent',
                    'item_type' => 'transport',
                ]);
            }

            // Reserve inventory durably.
            ScheduleCabinMapping::whereIn('id', $mappingIds)->update([
                'booked' => 1,
                'booking_id' => $booking->id,
            ]);

            $totalPayable = round($totalAmount + $vatTotal + $chargeTotal, 2);
            sort($tripDates);

            // Commission + net fund debit.
            $platformCommission = $this->commission->platformCommissionForItems($commissionItems);
            $resellerCommission = $this->commission->resellerCommission($partner, $platformCommission);
            $sharePercent = $partner->commissionSharePercent();
            $netDebit = $this->commission->netDebit($totalPayable, $resellerCommission);

            $booking->update([
                'total_amount' => $totalAmount,
                'vat_total' => $vatTotal,
                'charge_total' => $chargeTotal,
                'total_payable' => $totalPayable,
                'from_date' => $tripDates[0] ?? null,
                'to_date' => end($tripDates) ?: null,
                'platform_commission_amount' => $platformCommission,
                'reseller_commission_amount' => $resellerCommission,
                'commission_share_percent' => $sharePercent,
                'wallet_debit_amount' => $netDebit,
                'status' => AppConst::BOOKING_COMPLETE,
                'payment_status' => 1,
            ]);

            // Debit the prepaid wallet (throws if insufficient => whole tx rolls back).
            $this->wallet->debit(
                (int) $partner->id,
                $netDebit,
                WalletTransaction::SOURCE_BOOKING,
                'booking_'.$booking->id,
                [
                    'booking_id' => $booking->id,
                    'total_payable' => $totalPayable,
                    'reseller_commission' => $resellerCommission,
                ],
                'Booking #'.$booking->id.' (net of commission)',
                $partner
            );

            Payment::create([
                'booking_id' => $booking->id,
                'transaction_id' => strtoupper(uniqid((string) $booking->id, false)),
                'customer_id' => $customer->id,
                'payment_method' => 'fund',
                'status' => AppConst::PAYMENT_SUCCESS,
                'paid_amount' => $totalPayable,
                'store_amount' => $netDebit,
                'dues' => 0,
            ]);

            $booking = $booking->load(['bookingItems', 'customer', 'payment']);
            // Ledger + cabin map / SMS after the wallet debit commits.
            app(BookingCompletionService::class)->dispatchCompleteEvent($booking);

            return $booking;
        }, 3);
    }

    private function assertPubliclySellable(ScheduleCabinMapping $mapping): void
    {
        $schedule = $mapping->schedule;

        if (! $schedule || $schedule->status !== AppConst::SCHEDULE_ACTIVE) {
            throw new \RuntimeException('One or more selected trips are not available.');
        }
        if ($mapping->ownership !== AppConst::PARTY_DURPALLA) {
            throw new \RuntimeException('Selected items are not part of the Durpalla public quota.');
        }
        if ($mapping->booked || $mapping->is_reserved) {
            throw new \RuntimeException('One or more selected items are no longer available.');
        }
        if (strtotime((string) $schedule->leaving_at) < time()) {
            throw new \RuntimeException('One or more selected trips have already departed.');
        }
    }

    private function resolveCustomer(array $data): Customer
    {
        $mobile = $data['customer_mobile'] ?? null;
        if (! $mobile) {
            throw new \InvalidArgumentException('customer_mobile is required.');
        }

        $customer = Customer::firstOrNew(['mobile' => $mobile]);
        if (! $customer->id) {
            $customer->name = $data['customer_name'] ?? $mobile;
            $customer->password = Hash::make(Str::random(12));
            $customer->status = 1;
        }
        if (! empty($data['customer_email']) && empty($customer->email)) {
            $customer->email = $data['customer_email'];
        }
        $customer->save();

        return $customer;
    }
}
