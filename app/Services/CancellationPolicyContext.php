<?php

namespace App\Services;

use App\Models\Merchant;
use Illuminate\Support\Carbon;

/**
 * Resolves merchant-aware refund flags and event datetime for policy calculation.
 */
class CancellationPolicyContext
{
    public function isVatRefundable(?int $merchantId): bool
    {
        if ($merchantId) {
            $merchant = Merchant::query()->find($merchantId);
            if ($merchant && $merchant->vat_refundable !== null) {
                return (bool) $merchant->vat_refundable;
            }
        }

        return (bool) getOption('is_vat_refundable', 0);
    }

    public function isChargeRefundable(?int $merchantId): bool
    {
        if ($merchantId) {
            $merchant = Merchant::query()->find($merchantId);
            if ($merchant && $merchant->charge_refundable !== null) {
                return (bool) $merchant->charge_refundable;
            }
        }

        return (bool) getOption('is_charge_refundable', 0);
    }

    public function resolveServiceType(array $item): string
    {
        $type = strtolower((string) ($item['service_type']
            ?? $item['booking']['service_type']
            ?? $item['type']
            ?? 'transport'));

        if (in_array($type, ['hotel', 'hotels'], true)) {
            return 'hotel';
        }

        return 'transport';
    }

    public function itemMerchantId(array $item): ?int
    {
        $merchantId = $item['trip']['merchant_id']
            ?? $item['hotel']['merchant_id']
            ?? $item['merchant_id']
            ?? null;

        return $merchantId ? (int) $merchantId : null;
    }

    public function itemEventAt(array $item, ?string $serviceType = null): ?Carbon
    {
        $serviceType = $serviceType ?: $this->resolveServiceType($item);

        if ($serviceType === 'hotel') {
            return $this->hotelCheckInAt($item);
        }

        return $this->transportDepartureAt($item);
    }

    private function hotelCheckInAt(array $item): ?Carbon
    {
        $checkIn = $item['check_in']
            ?? $item['check_in_date']
            ?? $item['hotel_item']['check_in']
            ?? null;

        if (! $checkIn) {
            return null;
        }

        try {
            return Carbon::parse((string) $checkIn)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    private function transportDepartureAt(array $item): ?Carbon
    {
        $tripDateRaw = $item['trip_date'] ?? null;
        $leavingRaw = $item['trip']['leaving_at'] ?? null;

        if (! $tripDateRaw && ! $leavingRaw) {
            return null;
        }

        try {
            if ($leavingRaw) {
                $leaving = Carbon::parse((string) $leavingRaw);
                if ($leaving->year > 2000 && preg_match('/\d{4}-\d{2}-\d{2}/', (string) $leavingRaw)) {
                    return $leaving;
                }
            }

            if ($tripDateRaw) {
                $tripDate = Carbon::parse((string) $tripDateRaw);
                if ($leavingRaw) {
                    $time = Carbon::parse((string) $leavingRaw);

                    return $tripDate->copy()->setTime(
                        (int) $time->hour,
                        (int) $time->minute,
                        (int) $time->second
                    );
                }

                return $tripDate;
            }

            return Carbon::parse((string) $leavingRaw);
        } catch (\Exception) {
            return null;
        }
    }
}
