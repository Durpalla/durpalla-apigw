<?php

namespace App\Services\Promotion\Rules;

use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Models\Promotion;

/**
 * When one or more auto-discounts have already been applied to the context,
 * a code-based promotion can only be added on top if it is flagged stackable.
 * The engine sets `alreadyDiscounted` on the context items via the
 * `_has_auto_discount` marker.
 */
class StackingRule implements PromotionRuleInterface
{
    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        $hasExistingDiscount = false;
        foreach ($context->items as $item) {
            if (! empty($item['_has_auto_discount'])) {
                $hasExistingDiscount = true;
                break;
            }
        }

        if (! $hasExistingDiscount) {
            return true;
        }

        return (bool) $promotion->stackable;
    }

    public function message(): string
    {
        return 'This promotion cannot be combined with existing discounts';
    }
}
