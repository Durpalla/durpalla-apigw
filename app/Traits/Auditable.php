<?php

namespace App\Traits;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

/**
 * Records create / update / delete / restore events for a model, with a
 * before→after diff of the changed attributes. Actor attribution is handled by
 * AuditLogger (multi-guard aware).
 *
 * Opt in per model:  use \App\Traits\Auditable;
 * Optionally define:  protected array $auditExclude = ['some_column'];
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            app(AuditLogger::class)->model('created', $model, [
                'new' => $model->auditableAttributes($model->getAttributes()),
            ]);
        });

        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at']);
            if (empty($changes)) {
                return;
            }
            $old = [];
            foreach (array_keys($changes) as $key) {
                $old[$key] = Arr::get($model->getRawOriginal(), $key);
            }
            app(AuditLogger::class)->model('updated', $model, [
                'old' => $model->auditableAttributes($old),
                'new' => $changes,
            ]);
        });

        static::deleted(function (Model $model) {
            $event = (in_array(SoftDeletes::class, class_uses_recursive($model), true) && ! $model->isForceDeleting())
                ? 'deleted'
                : 'force_deleted';
            app(AuditLogger::class)->model($event, $model);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function (Model $model) {
                app(AuditLogger::class)->model('restored', $model);
            });
        }
    }

    /**
     * Strip globally-ignored and model-specific attributes from an attribute set.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        $ignore = array_merge(
            (array) config('audit.model.ignore_attributes', []),
            (array) ($this->auditExclude ?? [])
        );

        return Arr::except($attributes, $ignore);
    }
}
