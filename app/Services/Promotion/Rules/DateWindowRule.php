<?php

namespace App\Services\Promotion\Rules;

use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Models\Promotion;

class DateWindowRule implements PromotionRuleInterface
{
    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        $now = now();

        return $promotion->starts_at <= $now && $promotion->ends_at >= $now;
    }

    public function message(): string
    {
        return 'This promotion is not valid at this time';
    }
}
