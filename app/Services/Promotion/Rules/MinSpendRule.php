<?php

namespace App\Services\Promotion\Rules;

use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Models\Promotion;

class MinSpendRule implements PromotionRuleInterface
{
    protected float $minRequired = 0.0;

    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        $this->minRequired = (float) ($promotion->min_spend_amount ?? 0);

        if ($this->minRequired <= 0) {
            return true;
        }

        return $context->subtotal >= $this->minRequired;
    }

    public function message(): string
    {
        return "A minimum spend of {$this->minRequired} is required for this promotion";
    }
}
