<?php

namespace App\Services\Promotion\Rules;

use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Models\Promotion;

class StatusRule implements PromotionRuleInterface
{
    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        return $promotion->status === 'active' && $promotion->approval_status === 'approved';
    }

    public function message(): string
    {
        return 'This promotion is not currently active';
    }
}
