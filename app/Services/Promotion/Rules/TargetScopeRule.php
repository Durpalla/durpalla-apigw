<?php

namespace App\Services\Promotion\Rules;

use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Models\Promotion;

/**
 * Ensures at least one cart item matches the promotion's targets. A promotion
 * with no targets is considered broadly applicable (within its service_type),
 * matching the legacy "period" coupon behaviour.
 */
class TargetScopeRule implements PromotionRuleInterface
{
    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        if ($promotion->targets->isEmpty()) {
            return true;
        }

        foreach ($context->items as $item) {
            if ($this->itemMatchesAnyTarget($promotion, $context, $item)) {
                return true;
            }
        }

        return false;
    }

    protected function itemMatchesAnyTarget(Promotion $promotion, PromotionContext $context, array $item): bool
    {
        foreach ($promotion->targets as $target) {
            $itemValue = $this->itemValueForTarget($target->target_type, $context, $item);
            if ($itemValue !== null && (int) $itemValue === (int) $target->target_id) {
                return true;
            }
        }

        return false;
    }

    protected function itemValueForTarget(string $targetType, PromotionContext $context, array $item): mixed
    {
        return match ($targetType) {
            'merchant' => $item['merchant_id'] ?? null,
            'route' => $item['route_id'] ?? null,
            'vehicle' => $item['vehicle_id'] ?? null,
            'schedule' => $item['schedule_id'] ?? null,
            'hotel' => $item['hotel_id'] ?? null,
            'city' => $item['city_id'] ?? null,
            'customer' => $context->userId,
            default => null,
        };
    }

    public function message(): string
    {
        return 'This promotion does not apply to the selected items';
    }
}
