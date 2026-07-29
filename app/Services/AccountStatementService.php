<?php

namespace App\Services;

use App\Models\AccountStatement;

class AccountStatementService
{
    public function latestBalance(string $accountType, int $accountId): float
    {
        return (float) (AccountStatement::query()
            ->forAccount($accountType, $accountId)
            ->latest('id')
            ->value('balance_after') ?? 0);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function record(
        string $accountType,
        int $accountId,
        string $direction,
        float $amount,
        ?float $balanceBefore,
        ?float $balanceAfter,
        string $source,
        ?string $reference = null,
        ?string $description = null,
        array $meta = [],
        ?string $idempotencyKey = null
    ): AccountStatement {
        $amount = round(abs($amount), 2);
        $before = $balanceBefore ?? $this->latestBalance($accountType, $accountId);

        if ($balanceAfter === null) {
            $delta = $direction === AccountStatement::DIRECTION_DEBIT ? -$amount : $amount;
            $after = round($before + $delta, 2);
        } else {
            $after = round($balanceAfter, 2);
        }

        $attributes = [
            'account_type' => $accountType,
            'account_id' => $accountId,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => round($before, 2),
            'balance_after' => $after,
            'source' => $source,
            'reference' => $reference,
            'description' => $description,
            'meta' => $meta,
        ];

        if ($idempotencyKey) {
            return AccountStatement::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                $attributes + ['idempotency_key' => $idempotencyKey]
            );
        }

        return AccountStatement::query()->create($attributes);
    }

    public function history(string $accountType, int $accountId, int $perPage = 20, array $filters = [])
    {
        $safePerPage = min(100, max(1, $perPage));
        $query = AccountStatement::query()
            ->forAccount($accountType, $accountId)
            ->latest('id');

        $this->applyFilters($query, $filters);

        return $query->paginate($safePerPage);
    }

    /**
     * Admin / filtered ledger search across accounts.
     *
     * @param  array{account_type?:string,account_id?:int|string,direction?:string,source?:string,date_from?:string,date_to?:string}  $filters
     */
    public function search(array $filters = [], int $perPage = 50)
    {
        $safePerPage = min(100, max(1, $perPage));
        $query = AccountStatement::query()->latest('id');
        $this->applyFilters($query, $filters);

        return $query->paginate($safePerPage)->withQueryString();
    }

    /**
     * @param  array{account_type?:string,account_id?:int|string,direction?:string,source?:string,date_from?:string,date_to?:string}  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }
        if (isset($filters['account_id']) && $filters['account_id'] !== '' && $filters['account_id'] !== null) {
            $query->where('account_id', (int) $filters['account_id']);
        }
        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }
}
