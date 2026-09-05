<?php

namespace App\Services\Transport;

use App\Services\Transport\Contracts\TransportSupplierInterface;
use App\Services\Transport\DTO\TransportSearchRequestDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Supplier;

class TransportSearchService
{
    protected array $suppliers = [];

    private bool $suppliersLoaded = false;

    public function __construct()
    {
        // Lazy-load suppliers on first search — constructor must not hit DB.
    }

    /**
     * Search: vehicle_type=bus returns ALL bus results (local + third-party);
     * same for launch, train, air. Results from every matching supplier are merged.
     * Results are cached per request key (from/to/date/type/filters) when search_cache_ttl_seconds > 0.
     */
    public function search(TransportSearchRequestDTO $request): array
    {
        $this->ensureSuppliersLoaded();

        $ttl = (int) config('transport.search_cache_ttl_seconds', 0);
        $cacheKey = $ttl > 0 ? $this->searchCacheKey($request) : null;

        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $allResults = [];
        $vehicleType = $this->normalizeVehicleType($request->vehicleType);

        foreach ($this->suppliers as $supplier) {
            if ($vehicleType && $supplier->getTransportType() !== $vehicleType) {
                continue;
            }

            try {
                $results = $supplier->search($request);

                if (! empty($results)) {
                    $allResults = array_merge($allResults, $results);
                }
            } catch (\Throwable $e) {
                Log::error('Transport supplier search failed', [
                    'supplier' => method_exists($supplier, 'getSupplierCode')
                        ? $supplier->getSupplierCode()
                        : class_basename($supplier),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $normalized = $this->normalizeTripResults($allResults);
        $filtered = $this->applyFilters($normalized, $request);

        if ($cacheKey !== null && $ttl > 0) {
            Cache::put($cacheKey, $filtered, $ttl);
        }

        return $filtered;
    }

    private function ensureSuppliersLoaded(): void
    {
        if ($this->suppliersLoaded) {
            return;
        }
        $this->suppliersLoaded = true;

        try {
            if (! \Schema::hasTable('suppliers')) {
                return;
            }

            $codes = config('transport.supplier_codes', [
                'local_bus', 'local_launch', 'local_train', 'local_air',
            ]);

            $query = Supplier::where('is_active', true);

            if (\Schema::hasColumn('suppliers', 'domain')) {
                $query->where(function ($q) use ($codes) {
                    $q->where('domain', Supplier::DOMAIN_TRANSPORT)
                        ->orWhereIn('code', $codes);
                });
            } else {
                $query->whereIn('code', $codes);
            }

            foreach ($query->get() as $supplier) {
                try {
                    $instance = $this->createSupplierInstance($supplier);
                    if ($instance) {
                        $this->suppliers[] = $instance;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to load transport supplier', [
                        'supplier' => $supplier->code,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('transport_suppliers_load_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Build a cache key for this search request (from/to/date/type/filters).
     */
    protected function searchCacheKey(TransportSearchRequestDTO $request): string
    {
        $parts = [
            $request->fromLocationId,
            $request->toLocationId,
            $request->travelDate,
            $request->returnDate ?? '',
            $request->vehicleType ?? '',
            $request->adults,
            $request->children,
            json_encode($request->filters ?? []),
        ];

        return 'transport_search:'.md5(implode('|', $parts));
    }

    /**
     * Ensure every result has trip_id (view/route expect it). Local suppliers use schedule_id.
     */
    protected function normalizeTripResults(array $results): array
    {
        return array_map(function ($row) {
            if (! isset($row['trip_id']) && isset($row['schedule_id'])) {
                $row['trip_id'] = $row['schedule_id'];
            }

            return $row;
        }, $results);
    }

    protected function applyFilters(array $results, TransportSearchRequestDTO $request): array
    {
        $filters = $request->filters ?? [];

        if (isset($filters['max_price'])) {
            $results = array_filter($results, fn ($result) => ($result['base_fare'] ?? 0) <= $filters['max_price']);
        }

        if (isset($filters['min_price'])) {
            $results = array_filter($results, fn ($result) => ($result['base_fare'] ?? 0) >= $filters['min_price']);
        }

        if (isset($filters['departure_after'])) {
            $results = array_filter($results, function ($result) use ($filters) {
                $leavingAt = is_string($result['leaving_at'])
                    ? \Carbon\Carbon::parse($result['leaving_at'])
                    : $result['leaving_at'];

                return $leavingAt->gte(\Carbon\Carbon::parse($filters['departure_after']));
            });
        }

        usort($results, function ($a, $b) {
            $timeA = is_string($a['leaving_at']) ? \Carbon\Carbon::parse($a['leaving_at']) : $a['leaving_at'];
            $timeB = is_string($b['leaving_at']) ? \Carbon\Carbon::parse($b['leaving_at']) : $b['leaving_at'];

            return $timeA->gt($timeB) ? 1 : -1;
        });

        return array_values($results);
    }

    protected function normalizeVehicleType(?string $vehicleType): ?string
    {
        if (! $vehicleType) {
            return null;
        }
        $alias = config('transport.vehicle_type_alias', ['boat' => 'launch']);

        return $alias[$vehicleType] ?? $vehicleType;
    }

    protected function createSupplierInstance(Supplier $supplier): ?TransportSupplierInterface
    {
        $classes = config('transport.supplier_classes', []);
        $class = $classes[$supplier->code] ?? null;

        if ($class && class_exists($class)) {
            return app($class);
        }

        if (! str_starts_with($supplier->code ?? '', 'local_')) {
            Log::debug('Transport: unknown supplier (add to config transport.supplier_classes)', ['code' => $supplier->code]);
        }

        return null;
    }
}
