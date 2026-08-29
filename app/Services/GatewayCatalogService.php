<?php

namespace App\Services;

use App\Helpers\CommonHelper;
use App\Gateways\Contracts\DeclaresGatewaySetup;
use App\Gateways\GatewayInterface;
use App\Models\Merchant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Constants\GatewayConstant;
use App\Models\Gateway;
use App\Models\GatewayCredential;
use App\Models\GatewayEndpoint;
use App\Models\GatewayParam;

class GatewayCatalogService
{
    public function forgetCache(): void
    {
        Cache::forget('gateways');
        Cache::forget('gateways.catalog');
    }

    /**
     * @return Collection<int, Gateway>
     */
    public function listForPublic(): Collection
    {
        return Gateway::query()
            ->where('status', Gateway::ACTIVE)
            ->where('type', 'payment')
            ->whereNull('merchant_id')
            ->where('channel', GatewayConstant::CHANNEL_LIVE)
            ->where('for_public', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Fund (offline) + live platform gateways for agents.
     *
     * @return Collection<int, Gateway>
     */
    public function listForAgent(): Collection
    {
        $fund = Gateway::query()
            ->where('status', Gateway::ACTIVE)
            ->whereNull('merchant_id')
            ->where('code', GatewayConstant::CODE_FUND)
            ->where('channel', GatewayConstant::CHANNEL_OFFLINE)
            ->first();

        $live = Gateway::query()
            ->where('status', Gateway::ACTIVE)
            ->where('type', 'payment')
            ->whereNull('merchant_id')
            ->where('channel', GatewayConstant::CHANNEL_LIVE)
            ->where('for_agent', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return collect($fund ? [$fund] : [])->concat($live)->values();
    }

    /**
     * Active merchant-owned gateway rows (default: payment only — used at checkout).
     *
     * @return Collection<int, Gateway>
     */
    public function listForMerchant(Merchant|int $merchant, ?string $type = GatewayConstant::TYPE_PAYMENT): Collection
    {
        $merchantId = $merchant instanceof Merchant ? (int) $merchant->id : (int) $merchant;

        $query = Gateway::query()
            ->where('merchant_id', $merchantId)
            ->where('channel', GatewayConstant::CHANNEL_MERCHANT)
            ->where('status', Gateway::ACTIVE);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Offline desk methods merchants may use for collect / attach.
     *
     * @return Collection<int, Gateway>
     */
    public function listMerchantOfflineDesk(): Collection
    {
        return Gateway::query()
            ->whereNull('merchant_id')
            ->where('channel', GatewayConstant::CHANNEL_OFFLINE)
            ->where('for_merchant', true)
            ->where('status', Gateway::ACTIVE)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Templates for merchant "Gateway namespace" (class_name) dropdown.
     *
     * @return Collection<int, Gateway>
     */
    public function listMerchantTemplates(?string $type = null): Collection
    {
        $query = Gateway::query()
            ->whereNull('merchant_id')
            ->where('channel', GatewayConstant::CHANNEL_MERCHANT)
            ->where('for_merchant', true)
            ->where('status', Gateway::ACTIVE)
            ->whereIn('type', GatewayConstant::merchantManageableTypes());

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Gateway>
     */
    public function listMerchantOwned(Merchant|int $merchant, ?string $type = null): Collection
    {
        $merchantId = $merchant instanceof Merchant ? (int) $merchant->id : (int) $merchant;

        $query = Gateway::query()
            ->where('merchant_id', $merchantId)
            ->where('channel', GatewayConstant::CHANNEL_MERCHANT)
            ->whereIn('type', GatewayConstant::merchantManageableTypes());

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Clone a listed template for the merchant. Never accepts client class_name.
     */
    public function enableForMerchant(Merchant|int $merchant, Gateway $template): Gateway
    {
        $merchantId = $merchant instanceof Merchant ? (int) $merchant->id : (int) $merchant;

        if ($template->merchant_id !== null
            || $template->channel !== GatewayConstant::CHANNEL_MERCHANT
            || (int) $template->status !== Gateway::ACTIVE
            || ! $template->for_merchant
            || ! in_array((string) $template->type, GatewayConstant::merchantManageableTypes(), true)
        ) {
            throw new \InvalidArgumentException(__('Selected gateway is not an approved merchant template.'));
        }

        $existing = Gateway::query()
            ->where('merchant_id', $merchantId)
            ->where('code', $template->code)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($merchantId, $template) {
            /** @var Gateway $clone */
            $clone = Gateway::query()->create([
                'name' => $template->name,
                'class_name' => $template->class_name,
                'code' => $template->code,
                'channel' => GatewayConstant::CHANNEL_MERCHANT,
                'type' => (string) ($template->type ?: GatewayConstant::TYPE_PAYMENT),
                'merchant_id' => $merchantId,
                'for_public' => false,
                'for_agent' => false,
                'for_merchant' => true,
                'requires_trx' => (bool) $template->requires_trx,
                'sort_order' => (int) $template->sort_order,
                'status' => Gateway::ACTIVE,
                'media_id' => $template->media_id,
            ]);

            $template->loadMissing(['endpoints', 'credentials', 'params']);
            foreach ($template->endpoints as $ep) {
                GatewayEndpoint::query()->create([
                    'gateway_id' => $clone->id,
                    'key' => $ep->key,
                    'value' => $ep->value,
                ]);
            }

            // Copy credential / param key shells only — never template secrets.
            $credKeys = $template->credentials->pluck('key')->filter()->values()->all();
            $paramKeys = $template->params->pluck('key')->filter()->values()->all();

            // Fall back to keys declared by the gateway class setup schema.
            $schema = $this->setupSchemaForClass((string) $template->class_name);
            if ($credKeys === []) {
                $credKeys = collect($schema['credentials'] ?? [])->pluck('key')->filter()->values()->all();
            }
            if ($paramKeys === []) {
                $paramKeys = collect($schema['params'] ?? [])->pluck('key')->filter()->values()->all();
            }

            foreach ($credKeys as $key) {
                GatewayCredential::query()->create([
                    'gateway_id' => $clone->id,
                    'key' => (string) $key,
                    'value' => '',
                ]);
            }
            foreach ($paramKeys as $key) {
                GatewayParam::query()->create([
                    'gateway_id' => $clone->id,
                    'key' => (string) $key,
                    'value' => '',
                    'user_id' => null,
                ]);
            }

            $this->forgetCache();

            return $clone->fresh(['credentials', 'params', 'endpoints']);
        });
    }

    /**
     * Expected credentials / params / endpoints from the gateway PHP class.
     *
     * @return array{summary:string,credentials:list<array<string,mixed>>,params:list<array<string,mixed>>,endpoints:list<array<string,mixed>>}
     */
    public function setupSchemaForClass(string $className): array
    {
        $empty = [
            'summary' => '',
            'credentials' => [],
            'params' => [],
            'endpoints' => [],
        ];

        if ($className === '' || ! class_exists($className)) {
            return $empty;
        }

        if (! is_subclass_of($className, DeclaresGatewaySetup::class)) {
            return $empty;
        }

        /** @var class-string<DeclaresGatewaySetup> $className */
        $schema = $className::setupSchema();

        return [
            'summary' => (string) ($schema['summary'] ?? ''),
            'credentials' => array_values($schema['credentials'] ?? []),
            'params' => array_values($schema['params'] ?? []),
            'endpoints' => array_values($schema['endpoints'] ?? []),
        ];
    }

    public function setupSchemaForGateway(Gateway $gateway): array
    {
        return $this->setupSchemaForClass((string) $gateway->class_name);
    }

    /**
     * Merchant may only update status (not class_name / code / channel / merchant_id).
     */
    public function updateMerchantGateway(Merchant|int $merchant, Gateway $gateway, array $attrs): Gateway
    {
        $merchantId = $merchant instanceof Merchant ? (int) $merchant->id : (int) $merchant;
        if ((int) $gateway->merchant_id !== $merchantId) {
            throw new \InvalidArgumentException(__('Gateway not found for this merchant.'));
        }

        if (array_key_exists('status', $attrs)) {
            $gateway->status = (int) $attrs['status'] ? Gateway::ACTIVE : Gateway::INACTIVE;
            $gateway->save();
            $this->forgetCache();
        }

        return $gateway->fresh();
    }

    /**
     * Remove a merchant-owned clone (not a platform template).
     */
    public function removeMerchantGateway(Merchant|int $merchant, Gateway $gateway): void
    {
        $merchantId = $merchant instanceof Merchant ? (int) $merchant->id : (int) $merchant;
        if ((int) $gateway->merchant_id !== $merchantId) {
            throw new \InvalidArgumentException(__('Gateway not found for this merchant.'));
        }

        DB::transaction(function () use ($gateway) {
            $gateway->credentials()->delete();
            $gateway->params()->delete();
            $gateway->endpoints()->delete();
            $gateway->delete();
        });

        $this->forgetCache();
    }

    /**
     * Upsert credential key/values for a merchant-owned gateway.
     *
     * @param  array<string, string>  $pairs
     */
    public function upsertMerchantCredentials(Merchant|int $merchant, Gateway $gateway, array $pairs): void
    {
        $merchantId = $merchant instanceof Merchant ? (int) $merchant->id : (int) $merchant;
        if ((int) $gateway->merchant_id !== $merchantId) {
            throw new \InvalidArgumentException(__('Gateway not found for this merchant.'));
        }

        foreach ($pairs as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $existing = GatewayCredential::query()
                ->where('gateway_id', $gateway->id)
                ->where('key', $key)
                ->first();
            if ($existing) {
                // bypass creating() encrypt by deleting + recreate, or update raw
                $existing->delete();
            }
            GatewayCredential::query()->create([
                'gateway_id' => $gateway->id,
                'key' => $key,
                'value' => (string) $value,
            ]);
        }

        $this->forgetCache();
    }

    /**
     * Upsert merchant gateway params. When $sync is true, keys missing from $pairs are deleted.
     *
     * @param  array<string, string>  $pairs
     */
    public function upsertMerchantParams(Merchant|int $merchant, Gateway $gateway, array $pairs, ?int $userId = null, bool $sync = false): void
    {
        $merchantId = $merchant instanceof Merchant ? (int) $merchant->id : (int) $merchant;
        if ((int) $gateway->merchant_id !== $merchantId) {
            throw new \InvalidArgumentException(__('Gateway not found for this merchant.'));
        }

        $kept = [];
        foreach ($pairs as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $kept[] = $key;
            GatewayParam::query()->updateOrCreate(
                ['gateway_id' => $gateway->id, 'key' => $key],
                ['value' => (string) $value, 'user_id' => $userId]
            );
        }

        if ($sync) {
            $query = GatewayParam::query()->where('gateway_id', $gateway->id);
            if ($kept !== []) {
                $query->whereNotIn('key', $kept);
            }
            $query->delete();
        }

        $this->forgetCache();
    }

    public function resolveHandler(Gateway $gateway): GatewayInterface
    {
        $handler = CommonHelper::purseGateway($gateway);
        if (method_exists($handler, 'bindGatewayEntity')) {
            $handler->bindGatewayEntity($gateway);
        }

        return $handler;
    }

    public function serializeGateway(Gateway $gateway, bool $includeSecrets = false): array
    {
        $setup = $this->setupSchemaForGateway($gateway);

        $row = [
            'id' => (int) $gateway->id,
            'name' => (string) $gateway->name,
            'code' => (string) $gateway->code,
            'class_name' => (string) $gateway->class_name,
            'channel' => (string) $gateway->channel,
            'type' => (string) $gateway->type,
            'status' => (int) $gateway->status,
            'merchant_id' => $gateway->merchant_id !== null ? (int) $gateway->merchant_id : null,
            'for_public' => (bool) $gateway->for_public,
            'for_agent' => (bool) $gateway->for_agent,
            'for_merchant' => (bool) $gateway->for_merchant,
            'requires_trx' => (bool) $gateway->requires_trx,
            'sort_order' => (int) $gateway->sort_order,
            'setup' => $setup,
        ];

        if ($includeSecrets) {
            $gateway->loadMissing(['credentials', 'params', 'endpoints']);
            $row['credentials'] = $gateway->credentials->map(fn ($c) => [
                'id' => $c->id,
                'key' => $c->key,
                'value' => '********',
            ])->values()->all();
            $row['params'] = $gateway->params->map(fn ($p) => [
                'id' => $p->id,
                'key' => $p->key,
                'value' => $p->value,
            ])->values()->all();
            // Endpoint values are admin-managed; show keys/status only to merchants.
            $row['endpoints'] = $gateway->endpoints->map(fn ($e) => [
                'id' => $e->id,
                'key' => $e->key,
                'configured' => trim((string) $e->value) !== '',
            ])->values()->all();
        }

        return $row;
    }
}
