<?php

namespace App\Support;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditService::class)->record(
                static::auditEventNameFor('created', $model),
                $model,
                null,
                $model->getAttributes(),
            );
        });

        static::updated(function (Model $model): void {
            if ($model->getChanges() === []) {
                return;
            }

            app(AuditService::class)->record(
                static::auditEventNameFor('updated', $model),
                $model,
                $model->getOriginal(),
                $model->getAttributes(),
            );
        });

        static::deleted(function (Model $model): void {
            app(AuditService::class)->record(
                static::auditEventNameFor('deleted', $model),
                $model,
                $model->getOriginal(),
                null,
            );
        });
    }

    private static function auditEventNameFor(string $action, Model $model): string
    {
        return Str::snake(class_basename($model)).'.'.$action;
    }
}
