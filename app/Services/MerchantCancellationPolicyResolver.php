<?php

namespace App\Services;

use App\Models\MerchantCancellationTier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the refund percentage for a cancellation from the merchant's tiers
 * (falling back to the global default, merchant_id = null) and how many hours
 * before departure/check-in the cancellation happens.
 */
class MerchantCancellationPolicyResolver
{
    /**
     * @return array<int,array{min_hours_before:int,refund_percent:float}>
     */
    public function tiersFor(?int $merchantId, string $serviceType = 'transport'): array
    {
        if (! Schema::hasTable('merchant_cancellation_tiers')) {
            return [];
        }

        $base = MerchantCancellationTier::query()->where('service_type', $serviceType);

        $tiers = $merchantId
            ? (clone $base)->where('merchant_id', $merchantId)->orderByDesc('min_hours_before')->get()
            : collect();

        if ($tiers->isEmpty()) {
            $tiers = (clone $base)->whereNull('merchant_id')->orderByDesc('min_hours_before')->get();
        }

        return $tiers->map(fn ($t) => [
            'min_hours_before' => (int) $t->min_hours_before,
            'refund_percent' => (float) $t->refund_percent,
        ])->values()->all();
    }

    public function refundPercent(?int $merchantId, $departure, string $serviceType = 'transport', ?CarbonInterface $now = null): float
    {
        $now = $now ?: Carbon::now();
        $departure = $departure instanceof CarbonInterface ? $departure : Carbon::parse((string) $departure);

        $hoursUntil = $now->diffInMinutes($departure, false) / 60;

        $tiers = $this->tiersFor($merchantId, $serviceType);
        if (empty($tiers)) {
            return 0.0;
        }

        foreach ($tiers as $tier) {
            if ($hoursUntil >= $tier['min_hours_before']) {
                return $tier['refund_percent'];
            }
        }

        return 0.0;
    }

    public function refundAmount(float $base, ?int $merchantId, $departure, string $serviceType = 'transport', ?CarbonInterface $now = null): float
    {
        return round($base * $this->refundPercent($merchantId, $departure, $serviceType, $now) / 100, 2);
    }

    /**
     * Short human-readable lines for invoices, quotes, and tickets.
     *
     * @return list<string>
     */
    public function invoicePolicyLines(?int $merchantId, string $serviceType = 'transport'): array
    {
        $tiers = $this->tiersFor($merchantId, $serviceType);
        if ($tiers === []) {
            return [];
        }

        $lines = [];
        foreach ($tiers as $tier) {
            $hours = (int) $tier['min_hours_before'];
            $pct = (int) round($tier['refund_percent']);

            if ($hours <= 0) {
                if ($pct <= 0) {
                    $lines[] = __('invoice.policy_late');
                }
                continue;
            }

            $lines[] = __('invoice.policy_cancel_before', [
                'window' => $this->formatHoursLabel($hours),
                'percent' => $pct,
            ]);
        }

        return $lines;
    }

    private function formatHoursLabel(int $hours): string
    {
        if ($hours >= 24 && $hours % 24 === 0) {
            $days = (int) ($hours / 24);

            return $days === 1
                ? __('invoice.hours_one_day')
                : __('invoice.hours_with_days', ['hours' => $hours, 'days' => $days]);
        }

        return __('invoice.hours', ['count' => $hours]);
    }
}
