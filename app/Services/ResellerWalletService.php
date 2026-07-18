<?php

namespace App\Services;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\ResellerWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Prepaid wallet operations for API-partner (reseller) parties. Shared DB with
 * durpalla-admin; this is the write path used at booking / cancellation time.
 *
 * All mutations lock the wallet row, write a ledger row with balance_before /
 * balance_after, and are idempotent per (source, reference).
 */
class ResellerWalletService
{
    private function cacheKey(int $partyId): string
    {
        return 'reseller_wallet_balance_'.$partyId;
    }

    public function getBalance(int $partyId): float
    {
        return (float) (ResellerWallet::where('party_id', $partyId)->value('balance') ?? 0);
    }

    public function credit(int $partyId, float $amount, string $source, ?string $reference = null, array $meta = [], ?string $description = null, $performedBy = null): WalletTransaction
    {
        return $this->mutate($partyId, WalletTransaction::TYPE_CREDIT, $amount, $source, $reference, $meta, $description, $performedBy);
    }

    public function debit(int $partyId, float $amount, string $source, ?string $reference = null, array $meta = [], ?string $description = null, $performedBy = null): WalletTransaction
    {
        return $this->mutate($partyId, WalletTransaction::TYPE_DEBIT, $amount, $source, $reference, $meta, $description, $performedBy);
    }

    private function mutate(int $partyId, string $type, float $amount, string $source, ?string $reference, array $meta, ?string $description, $performedBy): WalletTransaction
    {
        $amount = round(abs($amount), 2);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Wallet amount must be greater than zero.');
        }

        return DB::transaction(function () use ($partyId, $type, $amount, $source, $reference, $meta, $description, $performedBy) {
            if ($reference !== null) {
                $existing = WalletTransaction::where('source', $source)
                    ->where('reference', $reference)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $wallet = ResellerWallet::where('party_id', $partyId)->lockForUpdate()->first();
            if (! $wallet) {
                ResellerWallet::create(['party_id' => $partyId, 'balance' => 0]);
                $wallet = ResellerWallet::where('party_id', $partyId)->lockForUpdate()->first();
            }

            $before = (float) $wallet->balance;

            if ($type === WalletTransaction::TYPE_DEBIT) {
                if ($before < $amount) {
                    throw new InsufficientWalletBalanceException(
                        'Insufficient fund balance. Available: '.number_format($before, 2).', required: '.number_format($amount, 2).'.'
                    );
                }
                $after = round($before - $amount, 2);
            } else {
                $after = round($before + $amount, 2);
            }

            $wallet->balance = $after;
            $wallet->save();

            $performer = $this->resolvePerformer($performedBy);

            $tx = WalletTransaction::create([
                'party_id' => $partyId,
                'type' => $type,
                'source' => $source,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => $reference,
                'meta' => $meta ?: null,
                'performed_by' => $performer['id'],
                'performed_by_type' => $performer['type'],
                'description' => $description,
            ]);

            Cache::forget($this->cacheKey($partyId));

            return $tx;
        });
    }

    private function resolvePerformer($performedBy): array
    {
        if ($performedBy instanceof \Illuminate\Database\Eloquent\Model) {
            return ['id' => $performedBy->getKey(), 'type' => $performedBy::class];
        }
        if (is_int($performedBy)) {
            return ['id' => $performedBy, 'type' => null];
        }

        return ['id' => null, 'type' => null];
    }
}
