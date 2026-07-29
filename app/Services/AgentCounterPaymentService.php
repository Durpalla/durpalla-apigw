<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentBalance;
use App\Models\AgentCommission;
use App\Models\Booking;
use App\Models\Gateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AgentCounterPaymentService
{
    public const METHOD_FUND = 'fund';

    public function __construct(private readonly BalanceService $balanceService)
    {
    }

    /**
     * @return list<array{code:string,label:string,type:string,requires_trx:bool,balance?:float,gateway_id?:int}>
     */
    public function availableMethods(?Agent $agent = null): array
    {
        $balance = $agent
            ? (float) $this->balanceService->getMyBalance($agent->id)
            : 0.0;

        // Agents earn commission by booking on behalf of customers (fund / digital desk).
        // Cash desk payment is not eligible for agent counter bookings.
        $methods = [
            [
                'code' => self::METHOD_FUND,
                'label' => 'Fund',
                'type' => 'wallet',
                'requires_trx' => false,
                'balance' => $balance,
            ],
            ['code' => 'bkash', 'label' => 'bKash', 'type' => 'desk', 'requires_trx' => true],
            ['code' => 'nagad', 'label' => 'Nagad', 'type' => 'desk', 'requires_trx' => true],
            ['code' => 'rocket', 'label' => 'Rocket', 'type' => 'desk', 'requires_trx' => true],
            ['code' => 'card', 'label' => 'Card', 'type' => 'desk', 'requires_trx' => true],
            ['code' => 'bank', 'label' => 'Bank', 'type' => 'desk', 'requires_trx' => true],
        ];

        $codes = array_column($methods, 'code');

        foreach ($this->activeGateways() as $gateway) {
            $code = $this->gatewayCode($gateway['name']);
            if (in_array($code, $codes, true)) {
                continue;
            }
            $methods[] = [
                'code' => $code,
                'label' => $gateway['name'],
                'type' => 'gateway',
                'requires_trx' => true,
                'gateway_id' => $gateway['id'],
            ];
            $codes[] = $code;
        }

        return $methods;
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
     * Live gateway = stored gateway row that can open a payment URL (not desk-only codes).
     */
    public function isLiveGateway(string $method, mixed $gatewayId = null): bool
    {
        if ($this->isFund($method)) {
            return false;
        }

        $id = (int) $gatewayId;
        if ($id > 0) {
            return Gateway::query()->where('id', $id)->where('status', 1)->exists();
        }

        $code = $this->normalize($method);
        foreach ($this->activeGateways() as $gateway) {
            if ($this->gatewayCode($gateway['name']) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gateways agents can use to top up fund (excludes fund itself).
     *
     * @return list<array{id:int,name:string,code:string}>
     */
    public function topupGateways(): array
    {
        return collect($this->activeGateways())
            ->map(fn (array $gateway) => [
                'id' => $gateway['id'],
                'name' => $gateway['name'],
                'code' => $this->gatewayCode($gateway['name']),
            ])
            ->values()
            ->all();
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
     * @return list<array{id:int,name:string}>
     */
    private function activeGateways(): array
    {
        $rows = [];

        try {
            Gateway::query()
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->each(function ($gateway) use (&$rows) {
                    $rows[] = ['id' => (int) $gateway->id, 'name' => (string) $gateway->name];
                });
        } catch (\Throwable) {
            // table may differ by environment
        }

        return collect($rows)->unique('name')->values()->all();
    }

    private function gatewayCode(string $name): string
    {
        return Str::slug($name, '_');
    }
}
