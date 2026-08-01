<?php

namespace App\Services\Promotion\Rules;

use App\Services\Promotion\Contracts\PromotionRuleInterface;
use App\Services\Promotion\DTO\PromotionContext;
use App\Models\Promotion;

class ChannelRule implements PromotionRuleInterface
{
    public function passes(Promotion $promotion, PromotionContext $context): bool
    {
        return $promotion->channel === 'all'
            || $context->channel === 'all'
            || $promotion->channel === $context->channel;
    }

    public function message(): string
    {
        return 'This promotion is not available on this channel';
    }
}
