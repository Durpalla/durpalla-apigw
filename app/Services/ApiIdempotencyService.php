<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApiIdempotencyService
{
    /**
     * Read Idempotency-Key / X-Idempotency-Key from the request.
     */
    public function keyFromRequest(): string
    {
        $request = request();
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            $key = trim((string) $request->header('X-Idempotency-Key', ''));
        }

        return $key;
    }

    public function isValidKey(string $key): bool
    {
        $len = strlen($key);

        return $len > 0 && $len <= 64;
    }

    /**
     * Return a previously stored JSON response for this actor/scope/key, or null.
     */
    public function find(string $scope, int $actorId, string $key): ?JsonResponse
    {
        if (! Schema::hasTable('api_idempotency_keys') || ! $this->isValidKey($key)) {
            return null;
        }

        $row = DB::table('api_idempotency_keys')
            ->where('scope', $scope)
            ->where('actor_id', $actorId)
            ->where('idempotency_key', $key)
            ->first();

        if (! $row) {
            return null;
        }

        $payload = json_decode((string) ($row->response_json ?? 'null'), true);
        if (! is_array($payload)) {
            $payload = ['success' => true, 'data' => ['id' => $row->resource_id]];
        }

        return response()->json($payload, (int) ($row->status_code ?: 200));
    }

    /**
     * Persist a successful (or final) response for later retries.
     *
     * @param  array<string, mixed>  $payload
     */
    public function remember(
        string $scope,
        int $actorId,
        string $key,
        array $payload,
        int $statusCode = 200,
        ?int $resourceId = null
    ): void {
        if (! Schema::hasTable('api_idempotency_keys') || ! $this->isValidKey($key)) {
            return;
        }

        DB::table('api_idempotency_keys')->updateOrInsert(
            [
                'scope' => $scope,
                'actor_id' => $actorId,
                'idempotency_key' => $key,
            ],
            [
                'resource_id' => $resourceId,
                'status_code' => $statusCode,
                'response_json' => json_encode($payload),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
