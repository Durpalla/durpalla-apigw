<?php

namespace App\Services;

use App\Helpers\CommonHelper;
use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AccountStatement;
use App\Models\AgentFundTopup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Gateway;

class AgentFundTopupService
{
    public function __construct(
        private readonly AgentCounterPaymentService $payments,
        private readonly AccountStatementService $statements,
    ) {
    }

    /**
     * @return array{success:bool,message:string,data:array<string,mixed>}
     */
    public function options(Agent $agent): array
    {
        return [
            'success' => true,
            'message' => '',
            'data' => [
                'balance' => (float) app(BalanceService::class)->getMyBalance($agent->id),
                'gateways' => $this->payments->topupGateways(),
                'bank_transfer' => [
                    'enabled' => true,
                    'account_name' => (string) getOption('company_name', 'Durpalla'),
                    'account_no' => (string) getOption('company_bank_account', ''),
                    'bank_name' => (string) getOption('company_bank_name', ''),
                    'branch' => (string) getOption('company_bank_branch', ''),
                    'instructions' => (string) getOption(
                        'agent_fund_bank_transfer_note',
                        'Transfer amount and submit reference. Admin will approve and adjust fund.'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array{success:bool,message:string,data?:array<string,mixed>}
     */
    public function initiateGateway(Agent $agent, float $amount, int $gatewayId, ?Request $request = null): array
    {
        $request = $request ?? request();
        if ($amount <= 0) {
            return ['success' => false, 'message' => __('Invalid amount')];
        }

        $allowedIds = collect($this->payments->topupGateways())->pluck('id')->all();
        if (! in_array($gatewayId, $allowedIds, true)) {
            return ['success' => false, 'message' => __('Invalid payment gateway')];
        }

        $gateway = Gateway::query()->find($gatewayId);
        if (! $gateway) {
            return ['success' => false, 'message' => __('Invalid payment gateway')];
        }

        $topup = AgentFundTopup::create([
            'user_id' => $agent->id,
            'amount' => round($amount, 2),
            'method' => 'gateway',
            'gateway_id' => $gatewayId,
            'status' => 'pending_payment',
            'transaction_ref' => 'AFT'.time().$agent->id.random_int(100, 999),
        ]);

        // Gateway handlers expect payment-like fields/methods.
        $topup->transaction_id = $topup->transaction_ref;
        $topup->paid_amount = (float) $topup->amount;
        $data = ['success' => false, 'message' => __('Could not start payment')];

        try {
            $handler = CommonHelper::purseGateway($gateway);
            $handler->create($topup, $request, $data);
        } catch (\Throwable $e) {
            $topup->update(['status' => 'failed', 'note' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $paymentUrl = $data['paymentURL']
            ?? ($data['data']['paymentURL'] ?? null)
            ?? ($data['bkashURL'] ?? null)
            ?? null;

        $status = ! empty($data['success']) && $paymentUrl ? 'pending_payment' : 'failed';
        $topup->update([
            'payment_url' => $paymentUrl,
            'status' => $status,
            'meta' => ['gateway_response' => $data],
        ]);

        if (! $paymentUrl) {
            return ['success' => false, 'message' => $data['message'] ?? __('Could not start payment')];
        }

        return [
            'success' => true,
            'message' => __('Payment started'),
            'data' => [
                'topup_id' => $topup->id,
                'payment_url' => $paymentUrl,
                'status' => $topup->status,
            ],
        ];
    }

    /**
     * @return array{success:bool,message:string,data?:array<string,mixed>}
     */
    public function createBankTransferRequest(Agent $agent, float $amount, ?string $reference, ?string $note): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => __('Invalid amount')];
        }
        $topup = AgentFundTopup::create([
            'user_id' => $agent->id,
            'amount' => round($amount, 2),
            'method' => 'bank_transfer',
            'status' => 'pending_admin',
            'bank_reference' => $reference ?: null,
            'note' => $note ?: null,
            'transaction_ref' => 'BFT'.time().$agent->id.random_int(100, 999),
        ]);

        return [
            'success' => true,
            'message' => __('Bank transfer request submitted for admin approval'),
            'data' => ['topup_id' => $topup->id, 'status' => $topup->status],
        ];
    }

    /**
     * @return array{success:bool,message:string,data?:array<string,mixed>}
     */
    public function status(Agent $agent, int $topupId): array
    {
        $topup = AgentFundTopup::query()
            ->where('id', $topupId)
            ->where('user_id', $agent->id)
            ->first();
        if (! $topup) {
            return ['success' => false, 'message' => __('Top-up request not found')];
        }

        if ($topup->method === 'gateway' && $topup->status === 'pending_payment' && $topup->gateway_id) {
            $gateway = Gateway::query()->find($topup->gateway_id);
            if ($gateway) {
                $topup->transaction_id = $topup->transaction_ref;
                $topup->paid_amount = (float) $topup->amount;
                try {
                    $handler = CommonHelper::purseGateway($gateway);
                    $verify = ['success' => false];
                    $handler->verify($topup, request(), $verify);
                } catch (\Throwable $e) {
                    // Keep pending; user can retry.
                }
                if (($topup->status ?? '') === 'success') {
                    $this->creditIfNeeded($topup);
                } elseif (($topup->status ?? '') === 'failed') {
                    // no-op
                }
                $topup->refresh();
            }
        }

        return [
            'success' => true,
            'message' => $topup->status === 'success'
                ? __('Fund added successfully')
                : ($topup->status === 'pending_admin'
                    ? __('Waiting for admin approval')
                    : __('Payment pending')),
            'data' => [
                'topup_id' => $topup->id,
                'status' => $topup->status,
                'amount' => (float) $topup->amount,
                'credited_at' => $topup->credited_at,
            ],
        ];
    }

    public function approveBankTransfer(AgentFundTopup $topup, int $adminId): AgentFundTopup
    {
        if ($topup->method !== 'bank_transfer' || $topup->status !== 'pending_admin') {
            return $topup;
        }
        $topup->update([
            'status' => 'success',
            'approved_by' => $adminId,
            'approved_at' => now(),
        ]);
        $this->creditIfNeeded($topup);
        return $topup->refresh();
    }

    private function creditIfNeeded(AgentFundTopup $topup): void
    {
        if ($topup->status !== 'success' || $topup->credited_at) {
            return;
        }

        DB::transaction(function () use ($topup) {
            $fresh = AgentFundTopup::query()->lockForUpdate()->find($topup->id);
            if (! $fresh || $fresh->credited_at || $fresh->status !== 'success') {
                return;
            }

            $balance = AgentBalance::query()->firstOrNew(['user_id' => $fresh->user_id]);
            $before = (float) ($balance->balance ?? 0);
            $balance->balance = $before + (float) $fresh->amount;
            $balance->save();
            Cache::forget('my_balance_'.$fresh->user_id);
            $after = (float) $balance->balance;

            $source = $fresh->method === 'bank_transfer' ? 'fund_add_bank_transfer' : 'fund_add_gateway';
            $this->statements->record(
                accountType: AccountStatement::ACCOUNT_AGENT,
                accountId: (int) $fresh->user_id,
                direction: AccountStatement::DIRECTION_CREDIT,
                amount: (float) $fresh->amount,
                balanceBefore: $before,
                balanceAfter: $after,
                source: $source,
                reference: 'topup:'.$fresh->id,
                description: 'Fund top-up #'.$fresh->id,
                meta: [
                    'topup_id' => (int) $fresh->id,
                    'method' => (string) $fresh->method,
                    'gateway_id' => $fresh->gateway_id ? (int) $fresh->gateway_id : null,
                ],
                idempotencyKey: 'agent:fund:add:topup:'.$fresh->id
            );

            $fresh->credited_at = now();
            $fresh->save();
        });
    }
}
