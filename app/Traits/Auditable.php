<?php

namespace App\Traits;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public function auditModule(): string
    {
        return $this->getTable();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditValues(array $values): array
    {
        return $values;
    }

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogService::class)->recordCreated($model);
        });

        static::updated(function (Model $model): void {
            app(AuditLogService::class)->recordUpdated($model);
        });

        static::deleted(function (Model $model): void {
            app(AuditLogService::class)->recordDeleted($model);
        });
    }
}
