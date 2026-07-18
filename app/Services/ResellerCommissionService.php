<?php

namespace App\Services;

use App\Models\Party;
use Illuminate\Support\Facades\DB;

/**
 * Computes Durpalla's own commission, the reseller's share of it, and the net
 * amount to debit from the reseller wallet.
 *
 * Durpalla's commission per item = (price - discount) * merchant commission_rate,
 * where the rate comes from the merchant's active SaaS subscription
 * (merchants.current_subscription_id -> saas_subscriptions.commission_rate), with
 * a legacy honorium fallback. The reseller earns share% of that; it pays the net
 * (total_payable - reseller_commission) from the fund.
 */
class ResellerCommissionService
{
    /** @var array<int,float|null> */
    private array $rateCache = [];

    /**
     * @param  array<int,array{merchant_id:int,price:float,discount?:float,is_honorium?:bool,honorium_charge?:float,honorium_type?:string}>  $items
     */
    public function platformCommissionForItems(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $merchantId = (int) ($item['merchant_id'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $base = max(0.0, $price - $discount);

            $rate = $merchantId > 0 ? $this->commissionRateFor($merchantId) : null;

            if ($rate !== null) {
                $total += $base * $rate / 100;
                continue;
            }

            $total += $this->honoriumCommission($item, $base);
        }

        return round($total, 2);
    }

    public function commissionRateFor(int $merchantId): ?float
    {
        if (array_key_exists($merchantId, $this->rateCache)) {
            return $this->rateCache[$merchantId];
        }

        $rate = null;

        try {
            $subscriptionId = DB::table('merchants')->where('id', $merchantId)->value('current_subscription_id');
            if ($subscriptionId) {
                $value = DB::table('saas_subscriptions')->where('id', $subscriptionId)->value('commission_rate');
                if ($value !== null) {
                    $rate = (float) $value;
                }
            }
        } catch (\Throwable $e) {
            $rate = null;
        }

        return $this->rateCache[$merchantId] = $rate;
    }

    /**
     * @param  array<string,mixed>  $item
     */
    private function honoriumCommission(array $item, float $base): float
    {
        if (empty($item['is_honorium'])) {
            return 0.0;
        }

        $charge = (float) ($item['honorium_charge'] ?? 0);
        $type = (string) ($item['honorium_type'] ?? 'percent');

        return $type === 'fixed' ? $charge : ($base * $charge / 100);
    }

    public function resellerCommission(Party $reseller, float $platformCommission): float
    {
        return round($platformCommission * $reseller->commissionSharePercent() / 100, 2);
    }

    public function netDebit(float $totalPayable, float $resellerCommission): float
    {
        return round(max(0.0, $totalPayable - $resellerCommission), 2);
    }
}
