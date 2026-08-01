<?php

namespace App\Services\Promotion\DTO;

use App\Models\Promotion;

class PromotionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly float $discountAmount = 0.0,
        public readonly array $itemDiscounts = [],
        public readonly ?Promotion $promotion = null,
    ) {
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }

    public static function ok(Promotion $promotion, array $itemDiscounts, string $message = 'Promotion applied successfully'): self
    {
        return new self(true, $message, array_sum($itemDiscounts), $itemDiscounts, $promotion);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'discount' => $this->discountAmount,
            'item_discounts' => $this->itemDiscounts,
            'promotion_id' => $this->promotion?->id,
            'promotion_code' => $this->promotion?->code,
        ];
    }
}
