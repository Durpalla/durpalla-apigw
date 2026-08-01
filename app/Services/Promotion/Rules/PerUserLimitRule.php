<?php

namespace App\Services\Promotion\Rules;

use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Models\Promotion;
use App\Models\PromotionRedemption;

class PerUserLimitRule implements PromotionRuleInterface
{
    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        if ($promotion->usage_limit_per_user === null || $context->userId === null) {
            return true;
        }

        $used = PromotionRedemption::where('promotion_id', $promotion->id)
            ->where('user_id', $context->userId)
            ->where('status', 'applied')
            ->count();

        return $used < $promotion->usage_limit_per_user;
    }

    public function message(): string
    {
        return 'You have already used this promotion the maximum number of times';
    }
}
