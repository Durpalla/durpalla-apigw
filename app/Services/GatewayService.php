<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Gateway;
use App\Repository\Interfaces\GatewayRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class GatewayService
{
    private const PUBLIC_CACHE_KEY = 'gateways.public.customers.v2';

    private $repository;

    public function __construct(GatewayRepositoryInterface $gatewayRepository)
    {
        $this->repository = $gatewayRepository;
    }

    public static function forgetPublicCache(): void
    {
        foreach ([
            'gateways.public.live',
            'gateways.public.live.v2',
            'gateways.public.booking',
            'gateways.public.booking.v2',
            'gateways.public.topup',
            'gateways.public.topup.v2',
            self::PUBLIC_CACHE_KEY,
        ] as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Public customers: platform live gateways with for_public=1 only.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->forPublicCustomers();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forBooking(): array
    {
        return $this->forPublicCustomers();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forPublicCustomers(): array
    {
        return Cache::remember(self::PUBLIC_CACHE_KEY, 600, function (): array {
            return $this->listForPublicCustomers();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listForPublicCustomers(): array
    {
        if (! Schema::hasColumn('gateways', 'channel')) {
            return Gateway::query()
                ->with('media')
                ->where('status', 1)
                ->whereNull('merchant_id')
                ->get()
                ->map(fn (Gateway $gateway) => $this->formatPublicGateway($gateway))
                ->values()
                ->all();
        }

        return Gateway::query()
            ->with('media')
            ->where('status', 1)
            ->whereNull('merchant_id')
            ->where('channel', 'live')
            ->where('for_public', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Gateway $gateway) => $this->formatPublicGateway($gateway))
            ->values()
            ->all();
    }

    public function getActive(): Collection
    {
        if (Schema::hasColumn('gateways', 'channel')) {
            return Gateway::query()
                ->with('media')
                ->where('status', AppConst::GATEWAY_ACTIVE)
                ->whereNull('merchant_id')
                ->where('channel', 'live')
                ->where('for_public', true)
                ->orderBy('sort_order')
                ->get();
        }

        return $this->repository->all()->where('status', AppConst::GATEWAY_ACTIVE);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPublicGateway(Gateway $gateway): array
    {
        return [
            'id' => (int) $gateway->id,
            'name' => (string) $gateway->name,
            'code' => (string) ($gateway->code ?? ''),
            'channel' => (string) ($gateway->channel ?? 'live'),
            'icon' => $gateway->icon,
            'requires_trx' => (bool) ($gateway->requires_trx ?? false),
        ];
    }
}
