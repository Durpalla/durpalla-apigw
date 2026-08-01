<?php

namespace App\Services\Promotion\Contracts;

use App\Models\Promotion;
use App\Services\Promotion\DTO\PromotionContext;

interface PromotionRuleInterface
{
    public function passes(Promotion $promotion, PromotionContext $context): bool;

    public function message(): string;
}
