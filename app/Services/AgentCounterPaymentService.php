<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AccountStatement;
use App\Models\Booking;
use App\Models\Gateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Agent booking pay: Fund (wallet) + Durpalla Live gateways only.
 * Catalog columns (channel, code, for_agent, …) are owned by durpalla migrations.
 */
class AgentCounterPaymentService
{
    public const METHOD_FUND = 'fund';

    public const METHOD_CASH = 'cash';

    public const CHANNEL_OFFLINE = 'offline';

    public const CHANNEL_LIVE = 'live';

    public function __construct(private readonly BalanceService $balanceService)
    {
    }

    /**
     * @return list<array{code:string,label:string,type:string,requires_trx:bool,balance?:float,gateway_id?:int,channel?:string,id?:int}>
     */
    public function availableMethods(?Agent $agent = null): array
    {
        $balance = $agent
            ? (float) $this->balanceService->getMyBalance($agent->id)
            : 0.0;

        $this->ensureDefaultOfflineGateways();

        if (! $this->hasChannelColumns()) {
            return $this->legacyFallbackMethods($balance);
        }

        $methods = [];

        foreach ($this->listForAgent() as $gateway) {
            $code = $this->normalize((string) ($gateway->code ?: ''));
            // Fund is the only agent wallet method. Cash stays a system default
            // offline gateway but is not offered for agent app checkout.
            if ($code === self::METHOD_FUND) {
                $methods[] = [
                    'id' => (int) $gateway->id,
                    'code' => self::METHOD_FUND,
                    'label' => (string) ($gateway->name ?: 'Fund'),
                    'type' => 'wallet',
                    'requires_trx' => false,
                    'balance' => $balance,
                    'gateway_id' => (int) $gateway->id,
                    'channel' => self::CHANNEL_OFFLINE,
                    'icon' => $gateway->icon,
                    'live_gateway' => false,
                    'charge_percent' => 0.0,
                ];
                continue;
            }

            if ((string) $gateway->channel !== self::CHANNEL_LIVE) {
                continue;
            }

            $percent = $gateway->resolvedChargePercent(true);
            $methods[] = [
                'id' => (int) $gateway->id,
                'code' => $code !== '' ? $code : $this->normalize((string) $gateway->name),
                'label' => (string) $gateway->name,
                'type' => 'gateway',
                'requires_trx' => false,
                'gateway_id' => (int) $gateway->id,
                'channel' => self::CHANNEL_LIVE,
                'icon' => $gateway->icon,
                'live_gateway' => true,
                'charge_percent' => $percent,
            ];
        }

        if (! collect($methods)->contains(fn ($m) => $m['code'] === self::METHOD_FUND)) {
            $fund = $this->resolveOfflineGateway(self::METHOD_FUND);
            array_unshift($methods, [
                'id' => $fund?->id ? (int) $fund->id : null,
                'code' => self::METHOD_FUND,
                'label' => (string) ($fund?->name ?: 'Fund'),
                'type' => 'wallet',
                'requires_trx' => false,
                'balance' => $balance,
                'gateway_id' => $fund?->id ? (int) $fund->id : null,
                'channel' => self::CHANNEL_OFFLINE,
                'live_gateway' => false,
                'charge_percent' => 0.0,
            ]);
        }

        return $methods;
    }

    /**
     * System-default offline gateway (fund / cash) from the gateway catalog.
     */
    public function resolveOfflineGateway(string $code): ?Gateway
    {
        $code = $this->normalize($code);
        if (! in_array($code, [self::METHOD_FUND, self::METHOD_CASH], true)) {
            return null;
        }

        $this->ensureDefaultOfflineGateways();

        $query = Gateway::query()
            ->where('code', $code)
            ->whereNull('merchant_id')
            ->where('status', 1);

        if ($this->hasChannelColumns()) {
            $query->where('channel', self::CHANNEL_OFFLINE);
        }

        return $query->orderBy('id')->first();
    }

    public function defaultGatewayId(string $code): ?int
    {
        $gateway = $this->resolveOfflineGateway($code);

        return $gateway?->id ? (int) $gateway->id : null;
    }

    /**
     * Fund and Cash are platform defaults and must always exist in gateways.
     */
    public function ensureDefaultOfflineGateways(): void
    {
        static $ensured = false;
        if ($ensured || ! $this->hasChannelColumns()) {
            return;
        }

        $defaults = [
            self::METHOD_CASH => [
                'name' => 'Cash',
                'class_name' => 'App\\Gateways\\Offline',
                'for_public' => false,
                'for_agent' => false,
                'for_merchant' => true,
                'requires_trx' => false,
                'sort_order' => 10,
            ],
            self::METHOD_FUND => [
                'name' => 'Fund',
                'class_name' => 'App\\Gateways\\Fund',
                'for_public' => false,
                'for_agent' => true,
                'for_merchant' => false,
                'requires_trx' => false,
                'sort_order' => 20,
            ],
        ];

        foreach ($defaults as $code => $attrs) {
            Gateway::query()->firstOrCreate(
                [
                    'code' => $code,
                    'merchant_id' => null,
                ],
                array_merge($attrs, [
                    'type' => 'payment',
                    'channel' => self::CHANNEL_OFFLINE,
                    'status' => 1,
                    'charge_percent' => 0,
                ])
            );
        }

        $ensured = true;
    }

    /**
     * Live gateways for agent fund top-up.
     *
     * @return list<array{id:int,name:string,code:string}>
     */
    public function topupGateways(): array
    {
        if (! $this->hasChannelColumns()) {
            return collect($this->legacyActiveGateways())
                ->map(fn (array $g) => [
                    'id' => $g['id'],
                    'name' => $g['name'],
                    'code' => $this->normalize($g['name']),
                ])
                ->values()
                ->all();
        }

        return Gateway::query()
            ->with('media')
            ->whereNull('merchant_id')
            ->where('channel', self::CHANNEL_LIVE)
            ->where('status', 1)
            ->where('for_agent', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Gateway $g) => [
                'id' => (int) $g->id,
                'name' => (string) $g->name,
                'code' => (string) ($g->code ?: $this->normalize($g->name)),
                'icon' => $g->icon,
            ])
            ->all();
    }

    public function allowedCodes(?Agent $agent = null): array
    {
        return array_column($this->availableMethods($agent), 'code');
    }

    public function normalize(string $method): string
    {
        return strtolower(trim($method));
    }

    public function isFund(string $method): bool
    {
        return $this->normalize($method) === self::METHOD_FUND;
    }

    /**
     * Live online gateway — requires explicit platform gateway_id with channel=live.
     */
    public function isLiveGateway(string $method, mixed $gatewayId = null): bool
    {
        $method = $this->normalize($method);
        if ($method === self::METHOD_FUND || $method === 'cash') {
            return false;
        }

        if (empty($gatewayId)) {
            return false;
        }

        if (! $this->hasChannelColumns()) {
            return Gateway::query()->where('id', (int) $gatewayId)->where('status', 1)->exists();
        }

        $g = Gateway::query()->find((int) $gatewayId);
        if (! $g || $g->merchant_id !== null || (string) $g->channel !== self::CHANNEL_LIVE) {
            return false;
        }
        if ((int) $g->status !== 1) {
            return false;
        }

        $code = $this->normalize((string) $g->code);

        return $code === $method || $method === '' || $method === $this->normalize((string) $g->name);
    }

    /**
     * Agents no longer use unverified desk digital TRX methods.
     */
    public function isUnverifiedDeskMethod(string $method, mixed $gatewayId = null): bool
    {
        return false;
    }

    public function debitFund(Agent $agent, Booking $booking): void
    {
        $amount = (float) $booking->total_payable;
        $account = AgentBalance::query()
            ->where('user_id', $agent->id)
            ->lockForUpdate()
            ->first();

        if (! $account || (float) $account->balance < $amount) {
            throw new \Exception(__('Insufficient fund balance'));
        }

        $before = (float) $account->balance;
        $account->balance = $before - $amount;
        $account->save();
        $after = (float) $account->balance;

        Cache::forget('my_balance_'.$agent->id);

        // Wallet ledger (same pattern as withdrawals) — not agent_commissions
        // (purpose enum has no "booking"; commission history is credits only).
        app(AccountStatementService::class)->record(
            accountType: AccountStatement::ACCOUNT_AGENT,
            accountId: (int) $agent->id,
            direction: AccountStatement::DIRECTION_DEBIT,
            amount: $amount,
            balanceBefore: $before,
            balanceAfter: $after,
            source: 'booking_fund',
            reference: 'booking:'.$booking->id,
            description: 'Fund payment for booking #'.$booking->id,
            meta: [
                'booking_id' => (int) $booking->id,
                'payment_method' => self::METHOD_FUND,
            ],
            idempotencyKey: 'agent:booking:fund:'.$booking->id
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Gateway>
     */
    private function listForAgent()
    {
        $fund = $this->resolveOfflineGateway(self::METHOD_FUND);
        if ($fund) {
            $fund->loadMissing('media');
        }

        $live = Gateway::query()
            ->with('media')
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('type', 'payment')->orWhereNull('type');
            })
            ->whereNull('merchant_id')
            ->where('channel', self::CHANNEL_LIVE)
            ->where('for_agent', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return collect($fund ? [$fund] : [])->concat($live)->values();
    }

    private function hasChannelColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $cached = Schema::hasColumn('gateways', 'channel') && Schema::hasColumn('gateways', 'code');
        } catch (\Throwable) {
            $cached = false;
        }

        return $cached;
    }

    /**
     * @return list<array{code:string,label:string,type:string,requires_trx:bool,balance?:float,gateway_id?:int}>
     */
    private function legacyFallbackMethods(float $balance): array
    {
        $fund = Gateway::query()
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('code', self::METHOD_FUND)
                    ->orWhereRaw('LOWER(name) = ?', [self::METHOD_FUND]);
            })
            ->orderBy('id')
            ->first();

        $methods = [[
            'id' => $fund?->id ? (int) $fund->id : null,
            'code' => self::METHOD_FUND,
            'label' => (string) ($fund?->name ?: 'Fund'),
            'type' => 'wallet',
            'requires_trx' => false,
            'balance' => $balance,
            'gateway_id' => $fund?->id ? (int) $fund->id : null,
            'charge_percent' => 0.0,
            'live_gateway' => false,
        ]];
        foreach ($this->legacyActiveGateways() as $gateway) {
            $model = Gateway::query()->find($gateway['id']);
            $code = $this->normalize($gateway['name']);
            if (in_array($code, [self::METHOD_FUND, self::METHOD_CASH], true)) {
                continue;
            }
            $methods[] = [
                'code' => $code,
                'label' => $gateway['name'],
                'type' => 'gateway',
                'requires_trx' => false,
                'gateway_id' => $gateway['id'],
                'charge_percent' => $model ? $model->resolvedChargePercent(true) : 0.0,
                'live_gateway' => true,
            ];
        }

        return $methods;
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function legacyActiveGateways(): array
    {
        try {
            return Gateway::query()
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($g) => ['id' => (int) $g->id, 'name' => (string) $g->name])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
