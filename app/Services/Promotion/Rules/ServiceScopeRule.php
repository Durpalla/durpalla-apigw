<?php

namespace App\Services\Promotion\Rules;

use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Models\Promotion;

class ServiceScopeRule implements PromotionRuleInterface
{
    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        return $promotion->service_type === Promotion::SERVICE_ALL
            || $promotion->service_type === $context->serviceType;
    }

    public function message(): string
    {
        return 'This promotion is not applicable to this service';
    }
}
