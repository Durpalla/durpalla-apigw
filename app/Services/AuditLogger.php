<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\Audit\AuditActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Central writer for the audit trail. All logging paths (request middleware,
 * model observers, auth listeners) funnel through here so redaction, size caps
 * and actor resolution stay consistent. Never throws — auditing must not break
 * the request it is recording.
 */
class AuditLogger
{
    public function enabled(): bool
    {
        return (bool) config('audit.enabled', true);
    }

    /**
     * Low-level writer.
     *
     * @param array<string, mixed> $data
     */
    public function record(array $data): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $actor = $data['actor'] ?? AuditActor::resolve();
            unset($data['actor']);

            if (isset($data['properties']) && is_array($data['properties'])) {
                $data['properties'] = $this->sanitize($data['properties']);
            }

            AuditLog::create(array_merge([
                'actor_type' => $actor['actor_type'] ?? 'guest',
                'actor_model' => $actor['actor_model'] ?? null,
                'actor_id' => $actor['actor_id'] ?? null,
                'actor_name' => $actor['actor_name'] ?? null,
                'actor_mobile' => $actor['actor_mobile'] ?? null,
                'guard' => $actor['guard'] ?? null,
                'ip' => $data['ip'] ?? request()->ip(),
                'user_agent' => Str::limit((string) (request()->userAgent() ?? ''), 500, ''),
                'request_id' => $data['request_id'] ?? request()->headers->get('X-Request-Id'),
                'created_at' => now(),
            ], $data));
        } catch (\Throwable $e) {
            Log::warning('audit_log_write_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record a model lifecycle event with a before/after diff.
     *
     * @param array<string, mixed> $properties
     */
    public function model(string $event, Model $model, array $properties = []): void
    {
        $this->record([
            'event' => $event,
            'subject_type' => $model::class,
            'subject_id' => $model->getKey() !== null ? (int) $model->getKey() : null,
            'description' => ucfirst($event) . ' ' . class_basename($model),
            'properties' => $this->sanitize($properties),
        ]);
    }

    /**
     * Record an auth event (login/logout/failed) for an explicit actor model.
     *
     * @param array<string, mixed> $properties
     */
    public function auth(string $event, ?Model $model, ?string $guard = null, array $properties = []): void
    {
        $this->record([
            'actor' => AuditActor::fromModel($model, null, $guard),
            'event' => $event,
            'subject_type' => $model?->getMorphClass(),
            'subject_id' => $model && $model->getKey() !== null ? (int) $model->getKey() : null,
            'description' => Str::headline($event),
            'properties' => $this->sanitize($properties),
        ]);
    }

    /**
     * Redact sensitive keys, strip invalid UTF-8, and cap the overall payload size.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        $redact = array_map('strtolower', (array) config('audit.redact', []));

        $walk = function ($value) use (&$walk, $redact) {
            if (is_string($value)) {
                return $this->toValidUtf8($value);
            }

            if (! is_array($value)) {
                return $value;
            }

            $out = [];
            foreach ($value as $k => $v) {
                if (is_string($k) && in_array(strtolower($k), $redact, true)) {
                    $out[$k] = '***';
                    continue;
                }
                $out[$k] = $walk($v);
            }

            return $out;
        };

        $clean = $walk($data);

        // Re-encode with substitution so Eloquent's array→JSON cast never fails on
        // leftover invalid bytes (e.g. Windows-1252 form input, binary scraps).
        $encoded = json_encode($clean, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return ['_unencodable' => true];
        }

        $max = (int) config('audit.request.max_payload_chars', 8000);
        if ($max > 0 && strlen($encoded) > $max) {
            return ['_truncated' => true, 'preview' => Str::limit($encoded, $max, '…')];
        }

        return json_decode($encoded, true) ?? ['_unencodable' => true];
    }

    /**
     * Drop or replace bytes that are not valid UTF-8 so json_encode cannot fail.
     */
    private function toValidUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($converted)) {
            return $converted;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
