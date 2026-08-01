<?php

namespace App\Services\Promotion\Rules;

use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Models\Promotion;

class UsageLimitRule implements PromotionRuleInterface
{
    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        if ($promotion->usage_limit_total === null) {
            return true;
        }

        return $promotion->redeemed_count < $promotion->usage_limit_total;
    }

    public function message(): string
    {
        return 'This promotion has reached its total usage limit';
    }
}
