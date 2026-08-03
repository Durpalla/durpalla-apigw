<?php


namespace App\Services;


use App\Constants\AppConst;

class CalculationService
{
    private $numberFormat;
    public function __construct()
    {
        $this->numberFormat = getOption('number_format', 'actual')    . 'Format';
    }

    public function actualFormat($num): string
    {
        return sprintf("%.2f", $num);
    }

    public function ceilFormat($num)
    {
        return ceil($num);
    }

    public function floorFormat($num)
    {
        return floor($num);
    }

    public function format($num)
    {
        return call_user_func([$this, $this->numberFormat], $num);
    }

    public function calculateItemTotal(array $item)
    {
        $total = $item['price'] - $this->calculateItemDiscount($item);
        if($item['booking_party'] != 'merchant') {
            $total += $this->calculateItemCharge($item);
        }
        if ($this->resolveVatApplicableTo() === 'customer') {
            $total += $this->calculateItemVat($item);
        }

        return call_user_func([$this, $this->numberFormat], $total);
    }

    /**
     * Global Options: who pays VAT (customer | merchant | vendor).
     * Merchants cannot override this.
     */
    public function resolveVatApplicableTo(): string
    {
        $value = strtolower((string) getOption('vat_applicable_to', 'customer'));
        if (! in_array($value, ['customer', 'merchant', 'vendor'], true)) {
            return 'customer';
        }

        return $value;
    }

    /**
     * Global Options VAT rate (%). Merchants cannot override this.
     */
    public function resolveVatRate(): float
    {
        return abs((float) getOption('vat_amount', 0));
    }

    /**
     * VAT applies to service charge only — never to ticket fare.
     * Rate + applicability come from Durpalla Options.
     */
    public function calculateItemVat(array $item)
    {
        if ($this->resolveVatApplicableTo() !== 'customer') {
            return call_user_func([$this, $this->numberFormat], 0);
        }

        $charge = (float) $this->calculateItemCharge($item);
        $vatAmount = $this->resolveVatRate();

        return call_user_func([$this, $this->numberFormat], ($charge * ($vatAmount / 100)));
    }

    public function calculateItemCharge(array $item)
    {
        return ($item['charge_type'] == 'percent') ? call_user_func([$this, $this->numberFormat], ($item['price'] * ($item['charge_amount'] / 100))) : call_user_func([$this, $this->numberFormat],$item['charge_amount']);
    }

    public function calculateItemDiscount(array $item)
    {
        return ($item['discount_type'] === 'percent') ?
            call_user_func([$this, $this->numberFormat],($item['price'] * ($item['discount'] / 100)), 2) :
            call_user_func([$this, $this->numberFormat],$item['discount'], 2);
    }

    public function calculateRefundableAmount(array $item, $charge_refundable = false)
    {
        $vat = (getOption('vat_refundable', 0)) ? $this->calculateItemVat($item) : 0;
        $charge = ( $charge_refundable || getOption('charge_refundable', 0)) ? $this->calculateItemCharge($item) : 0;
        $discount = $this->calculateItemDiscount($item);

        return call_user_func([$this, $this->numberFormat], ($item['price'] + $vat + $charge - $discount));
    }

    public function itemDepartureAt(array $item): ?\Illuminate\Support\Carbon
    {
        $tripDateRaw = $item['trip_date'] ?? null;
        $leavingRaw = $item['trip']['leaving_at'] ?? null;

        if (! $tripDateRaw && ! $leavingRaw) {
            return null;
        }

        try {
            if ($leavingRaw) {
                $leaving = \Illuminate\Support\Carbon::parse((string) $leavingRaw);
                // Trip schedule stores a full departure datetime on leaving_at.
                if ($leaving->year > 2000 && preg_match('/\d{4}-\d{2}-\d{2}/', (string) $leavingRaw)) {
                    return $leaving;
                }
            }

            if ($tripDateRaw) {
                $tripDate = \Illuminate\Support\Carbon::parse((string) $tripDateRaw);
                if ($leavingRaw) {
                    $time = \Illuminate\Support\Carbon::parse((string) $leavingRaw);

                    return $tripDate->copy()->setTime(
                        (int) $time->hour,
                        (int) $time->minute,
                        (int) $time->second
                    );
                }

                return $tripDate;
            }

            return \Illuminate\Support\Carbon::parse((string) $leavingRaw);
        } catch (\Exception) {
            return null;
        }
    }

    public function itemMerchantId(array $item): ?int
    {
        $merchantId = $item['trip']['merchant_id'] ?? $item['merchant_id'] ?? null;

        return $merchantId ? (int) $merchantId : null;
    }

    public function policyRefundPercent(array $item): float
    {
        $departure = $this->itemDepartureAt($item);
        if (! $departure) {
            return 0.0;
        }

        return app(MerchantCancellationPolicyResolver::class)->refundPercent(
            $this->itemMerchantId($item),
            $departure,
            'transport'
        );
    }

    public function calculatePolicyRefundableAmount(array $item, bool $charge_refundable = false): float
    {
        $base = (float) $this->calculateRefundableAmount($item, $charge_refundable);
        $percent = $this->policyRefundPercent($item);

        return call_user_func([$this, $this->numberFormat], $base * $percent / 100);
    }

    public function isItemCancellableByPolicy(array $item): bool
    {
        $departure = $this->itemDepartureAt($item);

        return $departure !== null && $departure->isFuture();
    }

    public function getServiceCharge(array $item, $platform = 'web')
    {
        $charges = $this->getCharges($item, $platform);

        return call_user_func([$this, $this->numberFormat], $charges['amount']);
    }

    /**
     * Map request/booking platform labels to options keys:
     * service_charge_web | service_charge_mobile | service_charge_counter.
     */
    public function resolveChargeOptionKey(?string $platform): string
    {
        $p = strtolower(trim((string) ($platform ?? '')));

        return match ($p) {
            'web' => 'web',
            'android', 'ios', 'mobile', 'app', 'flutter', 'iphone' => 'mobile',
            'counter', 'agent', 'agent_app', 'office', 'supervisor_app', 'merchant_desk' => 'counter',
            default => 'mobile',
        };
    }

    /**
     * Priority (Durpalla-admin configured; merchants cannot manage):
     * 1) seat/cabin/item service_charge
     * 2) merchant service_charge
     * 3) global Options platform charge (web/mobile/counter)
     *
     * @return array{amount: float, type: string, total: float}
     */
    public function getCharges(array $item, string $platform = 'web'): array
    {
        $fare = (float) ($item['fare'] ?? $item['price'] ?? 0);
        $type = 'percent';
        $amount = 0.0;
        $optionKey = $this->resolveChargeOptionKey($platform);

        if ($this->hasChargeValue($item['service_charge'] ?? null)) {
            $type = $this->normalizeChargeType($item['service_charge_type'] ?? 'percent');
            $amount = abs((float) $item['service_charge']);
        } elseif ($this->hasChargeValue($item['merchant_service_charge'] ?? null)) {
            $type = $this->normalizeChargeType($item['merchant_service_charge_type'] ?? 'percent');
            $amount = abs((float) $item['merchant_service_charge']);
        } else {
            $type = $this->normalizeChargeType(getOption('service_charge_type', 'percent'));
            $raw = getOption('service_charge_' . $optionKey, null);
            if (($raw === null || $raw === '') && $optionKey !== 'mobile') {
                $raw = getOption('service_charge_mobile', null);
            }
            $amount = ($raw === null || $raw === '') ? 5.0 : abs((float) $raw);
        }

        $total = ($type === 'percent')
            ? ($fare * ($amount / 100))
            : $amount;

        return [
            'amount' => $amount,
            'type' => $type,
            'total' => (float) call_user_func([$this, $this->numberFormat], $total),
        ];
    }

    private function hasChargeValue(mixed $value): bool
    {
        return $value !== null && $value !== '' && is_numeric($value) && (float) $value > 0;
    }

    private function normalizeChargeType(mixed $type): string
    {
        $type = strtolower((string) ($type ?: 'percent'));
        if (in_array($type, ['fixed', 'f', 'flat'], true)) {
            return 'fixed';
        }

        return 'percent';
    }

    public function createDate($date, $format = 'd/m/Y')
    {
        $date_formated = date('Y-m-d');
        if ($date) {
            $date_formated = \DateTime::createFromFormat('d/m/Y', $date)->format('Y-m-d');
        }
        return $date_formated;
    }

    /**
     * VAT on a charge amount (percent of charge, or fixed).
     */
    public function calculateVat($chargeAmount, $type = 'percent'): float
    {
        $chargeAmount = (float) $chargeAmount;
        $vatRate = $this->resolveVatRate();

        return ($type == 'percent')
            ? (float) call_user_func([$this, $this->numberFormat], ($chargeAmount * ($vatRate / 100)), 2)
            : (float) call_user_func([$this, $this->numberFormat], $chargeAmount);
    }

    /**
     * VAT amount for a computed service-charge total (Options rate + applicability).
     */
    public function vatOnCharge(float $serviceChargeTotal): float
    {
        if ($this->resolveVatApplicableTo() !== 'customer' || $serviceChargeTotal <= 0) {
            return 0.0;
        }

        return abs((float) $this->calculateVat($serviceChargeTotal, 'percent'));
    }

    public function calculateCharge($amount, $type): float
    {
        return ($type == 'percent')
            ? (float) call_user_func([$this, $this->numberFormat], ((float) $amount * ((float) getOption('vat_amount', 0) / 100)), 2)
            : (float) call_user_func([$this, $this->numberFormat], $amount);
    }

    public function calculateAgentCommission(array $item)
    {
        return ($item['incentive_type'] == 'fixed') ?  call_user_func([$this, $this->numberFormat], $item['incentive']) : call_user_func([$this, $this->numberFormat], ($item['price'] * ($item['incentive'] / 100)));
    }

    public function calculatePartnerCommission($price, $partner)
    {
        $incentive = ($partner->incentive) ? $partner->incentive->incentive : 0;
        $type = ($partner->incentive) ? $partner->incentive->incentive_type : 'percent';
        return ($type == 'fixed') ?  call_user_func([$this, $this->numberFormat], $incentive) : call_user_func([$this, $this->numberFormat], ($price * ($incentive / 100)));
    }

    public function getAgentIncentive($order, $user)
    {
        if ($user instanceof \App\Models\Agent) {
            return $order->bookingItems->map(function ($item, $key) {
                return [
                    'incentive' => ($item->incentive_type === 'percent') ? ($item->price * ($item->incentive / 100)) : $item->incentive
                ];
            })->sum('incentive');
        }
        return 0;
    }
}
