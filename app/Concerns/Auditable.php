<?php

namespace App\Concerns;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditObserver::class)->created($model);
        });

        static::updated(function (Model $model): void {
            app(AuditObserver::class)->updated($model);
        });

        static::deleted(function (Model $model): void {
            app(AuditObserver::class)->deleted($model);
        });
    }

    /**
     * Attributes that must never be stored in an audit entry.
     *
     * @return list<string>
     */
    public function auditExcluded(): array
    {
        return [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'created_at',
            'updated_at',
        ];
    }
}
