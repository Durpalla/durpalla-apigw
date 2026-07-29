<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountStatement extends Model
{
    public const ACCOUNT_AGENT = 'agent';
    public const ACCOUNT_MERCHANT = 'merchant';

    public const DIRECTION_CREDIT = 'credit';
    public const DIRECTION_DEBIT = 'debit';

    protected $fillable = [
        'account_type',
        'account_id',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'source',
        'reference',
        'description',
        'meta',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
    ];

    public function scopeForAccount(Builder $query, string $accountType, int $accountId): Builder
    {
        return $query
            ->where('account_type', $accountType)
            ->where('account_id', $accountId);
    }
}
