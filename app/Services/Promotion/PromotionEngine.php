<?php

namespace App\Services\Promotion;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Services\Promotion\DTO\PromotionResult;
use App\Services\Promotion\Exceptions\PromotionLimitExceededException;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Services\Promotion\Rules\ChannelRule;
use App\Services\Promotion\Rules\DateWindowRule;
use App\Services\Promotion\Rules\MinSpendRule;
use App\Services\Promotion\Rules\PerUserLimitRule;
use App\Services\Promotion\Rules\ServiceScopeRule;
use App\Services\Promotion\Rules\StackingRule;
use App\Services\Promotion\Rules\StatusRule;
use App\Services\Promotion\Rules\TargetScopeRule;
use App\Services\Promotion\Rules\UsageLimitRule;

class PromotionEngine
{
    /**
     * Eligibility rules shared by auto-discounts and code-based coupons.
     * Order matters only for the message returned on first failure.
     *
     * @return list<class-string<PromotionRuleInterface>>
     */
    protected function baseRules(): array
    {
        return [
            StatusRule::class,
            DateWindowRule::class,
            ServiceScopeRule::class,
            ChannelRule::class,
            TargetScopeRule::class,
            MinSpendRule::class,
            UsageLimitRule::class,
            PerUserLimitRule::class,
        ];
    }

    /**
     * Resolve all automatic (codeless) discounts that apply to the given
     * context. Returns a collection of ['promotion' => Promotion, 'item_discounts' => [...], 'discount' => float].
     */
    public function resolveAutoDiscounts(PromotionContext $context): Collection
    {
        $promotions = Promotion::query()
            ->with('targets')
            ->autoApplied()
            ->active()
            ->where(function ($q) use ($context) {
                $q->where('service_type', Promotion::SERVICE_ALL)
                    ->orWhere('service_type', $context->serviceType);
            })
            ->orderByDesc('priority')
            ->get();

        $results = collect();

        foreach ($promotions as $promotion) {
            if (! $this->passesRules($promotion, $context, $this->baseRules())) {
                continue;
            }

            $itemDiscounts = $this->computeItemDiscounts($promotion, $context);
            $total = array_sum($itemDiscounts);

            if ($total <= 0) {
                continue;
            }

            $results->push([
                'promotion' => $promotion,
                'item_discounts' => $itemDiscounts,
                'discount' => $total,
            ]);

            if (! $promotion->stackable) {
                break;
            }
        }

        return $results;
    }

    /**
     * Validate and compute the discount for a customer-entered code.
     */
    public function applyCode(PromotionContext $context, string $code): PromotionResult
    {
        $promotion = Promotion::query()
            ->with('targets')
            ->codeBased()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])
            ->first();

        if (! $promotion) {
            return PromotionResult::fail(__('Invalid coupon code'));
        }

        $rules = array_merge($this->baseRules(), [StackingRule::class]);

        foreach ($rules as $ruleClass) {
            /** @var PromotionRuleInterface $rule */
            $rule = new $ruleClass();
            if (! $rule->passes($promotion, $context)) {
                return PromotionResult::fail($rule->message());
            }
        }

        $itemDiscounts = $this->computeItemDiscounts($promotion, $context);
        $total = array_sum($itemDiscounts);

        if ($total <= 0) {
            return PromotionResult::fail(__('Coupon is valid but not applicable to your items'));
        }

        return PromotionResult::ok($promotion, $itemDiscounts);
    }

    /**
     * Compute per-item discount amounts. Keys are the item index in the
     * context, values are the money amount (already resolved from percent/fixed
     * and capped by max_discount_amount at the promotion level).
     */
    protected function computeItemDiscounts(Promotion $promotion, PromotionContext $context): array
    {
        $applicableTypes = $promotion->applicable_item_types; // null = all types
        $discounts = [];

        foreach ($context->items as $index => $item) {
            $itemType = $item['type'] ?? null;

            if (is_array($applicableTypes) && ! empty($applicableTypes)
                && $itemType !== null && ! in_array($itemType, $applicableTypes, true)) {
                continue;
            }

            if (! $this->itemMatchesTargets($promotion, $context, $item)) {
                continue;
            }

            $amount = (float) ($item['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $discount = $promotion->discount_type === 'percent'
                ? $amount * ((float) $promotion->discount_value / 100)
                : (float) $promotion->discount_value;

            $discount = min($discount, $amount); // never exceed the line price
            $discounts[$index] = round($discount, 2);
        }

        // Apply promotion-level max cap across the whole promotion.
        if ($promotion->max_discount_amount !== null && array_sum($discounts) > $promotion->max_discount_amount) {
            $discounts = $this->scaleToCap($discounts, (float) $promotion->max_discount_amount);
        }

        return $discounts;
    }

    protected function itemMatchesTargets(Promotion $promotion, PromotionContext $context, array $item): bool
    {
        if ($promotion->targets->isEmpty()) {
            return true;
        }

        foreach ($promotion->targets as $target) {
            $itemValue = match ($target->target_type) {
                'merchant' => $item['merchant_id'] ?? null,
                'route' => $item['route_id'] ?? null,
                'vehicle' => $item['vehicle_id'] ?? null,
                'schedule' => $item['schedule_id'] ?? null,
                'hotel' => $item['hotel_id'] ?? null,
                'city' => $item['city_id'] ?? null,
                'customer' => $context->userId,
                default => null,
            };

            if ($itemValue !== null && (int) $itemValue === (int) $target->target_id) {
                return true;
            }
        }

        return false;
    }

    protected function scaleToCap(array $discounts, float $cap): array
    {
        $total = array_sum($discounts);
        if ($total <= 0) {
            return $discounts;
        }

        $ratio = $cap / $total;
        $scaled = [];
        $running = 0.0;
        $keys = array_keys($discounts);
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                // absorb rounding remainder into the last item
                $scaled[$key] = round($cap - $running, 2);
            } else {
                $value = round($discounts[$key] * $ratio, 2);
                $scaled[$key] = $value;
                $running += $value;
            }
        }

        return $scaled;
    }

    /**
     * Atomically record a redemption within the caller's DB transaction.
     * Locks the promotion row, re-checks the total usage limit, increments the
     * counter and writes the ledger entry.
     *
     * @throws PromotionLimitExceededException
     */
    public function redeem(Promotion $promotion, ?int $bookingId, ?int $userId, float $amount): PromotionRedemption
    {
        $locked = Promotion::whereKey($promotion->id)->lockForUpdate()->first();

        if ($locked->usage_limit_total !== null && $locked->redeemed_count >= $locked->usage_limit_total) {
            throw new PromotionLimitExceededException('This promotion has reached its usage limit');
        }

        $locked->increment('redeemed_count');

        return PromotionRedemption::create([
            'promotion_id' => $locked->id,
            'user_id' => $userId,
            'booking_id' => $bookingId,
            'discount_amount' => round($amount, 2),
            'currency' => $locked->currency,
            'status' => 'applied',
            'applied_at' => now(),
        ]);
    }

    /**
     * Reverse all applied redemptions tied to a booking (e.g. on cancellation),
     * releasing usage counters.
     */
    public function reverse(int $bookingId): int
    {
        return DB::transaction(function () use ($bookingId) {
            $redemptions = PromotionRedemption::where('booking_id', $bookingId)
                ->where('status', 'applied')
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($redemptions as $redemption) {
                $promotion = Promotion::whereKey($redemption->promotion_id)->lockForUpdate()->first();
                if ($promotion && $promotion->redeemed_count > 0) {
                    $promotion->decrement('redeemed_count');
                }
                $redemption->update(['status' => 'reversed', 'reversed_at' => now()]);
                $count++;
            }

            return $count;
        });
    }

    protected function passesRules(Promotion $promotion, PromotionContext $context, array $rules): bool
    {
        foreach ($rules as $ruleClass) {
            /** @var PromotionRuleInterface $rule */
            $rule = new $ruleClass();
            if (! $rule->passes($promotion, $context)) {
                return false;
            }
        }

        return true;
    }
}
