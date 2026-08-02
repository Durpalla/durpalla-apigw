<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AgentCommission;
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

        if (! $this->hasChannelColumns()) {
            return $this->legacyFallbackMethods($balance);
        }

        $methods = [];

        foreach ($this->listForAgent() as $gateway) {
            $code = $this->normalize((string) ($gateway->code ?: self::METHOD_FUND));
            if ($code === self::METHOD_FUND || (string) $gateway->channel === self::CHANNEL_OFFLINE) {
                $methods[] = [
                    'id' => (int) $gateway->id,
                    'code' => self::METHOD_FUND,
                    'label' => (string) ($gateway->name ?: 'Fund'),
                    'type' => 'wallet',
                    'requires_trx' => false,
                    'balance' => $balance,
                    'gateway_id' => (int) $gateway->id,
                    'channel' => self::CHANNEL_OFFLINE,
                ];
                continue;
            }

            $methods[] = [
                'id' => (int) $gateway->id,
                'code' => $code,
                'label' => (string) $gateway->name,
                'type' => 'gateway',
                'requires_trx' => false,
                'gateway_id' => (int) $gateway->id,
                'channel' => self::CHANNEL_LIVE,
            ];
        }

        if (! collect($methods)->contains(fn ($m) => $m['code'] === self::METHOD_FUND)) {
            array_unshift($methods, [
                'code' => self::METHOD_FUND,
                'label' => 'Fund',
                'type' => 'wallet',
                'requires_trx' => false,
                'balance' => $balance,
                'channel' => self::CHANNEL_OFFLINE,
            ]);
        }

        return $methods;
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
            ->whereNull('merchant_id')
            ->where('channel', self::CHANNEL_LIVE)
            ->where('status', 1)
            ->where('for_agent', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Gateway $g) => [
                'id' => (int) $g->id,
                'name' => (string) $g->name,
                'code' => (string) ($g->code ?: $this->normalize($g->name)),
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

        $account->balance = (float) $account->balance - $amount;
        $account->save();

        Cache::forget('my_balance_'.$agent->id);

        AgentCommission::query()->create([
            'user_id' => $agent->id,
            'commission_date' => now()->toDateString(),
            'purpose' => 'booking',
            'type' => 'debit',
            'total_sale' => $amount,
            'amount' => $amount,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Gateway>
     */
    private function listForAgent()
    {
        $fund = Gateway::query()
            ->where('status', 1)
            ->whereNull('merchant_id')
            ->where('code', self::METHOD_FUND)
            ->where('channel', self::CHANNEL_OFFLINE)
            ->first();

        $live = Gateway::query()
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
        $methods = [[
            'code' => self::METHOD_FUND,
            'label' => 'Fund',
            'type' => 'wallet',
            'requires_trx' => false,
            'balance' => $balance,
        ]];
        foreach ($this->legacyActiveGateways() as $gateway) {
            $methods[] = [
                'code' => $this->normalize($gateway['name']),
                'label' => $gateway['name'],
                'type' => 'gateway',
                'requires_trx' => false,
                'gateway_id' => $gateway['id'],
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
