<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property int $id
 * @property AuditAction $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property int|null $user_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $device_label
 * @property string|null $summary
 * @property Carbon|null $created_at
 */
#[Fillable([
    'action',
    'auditable_type',
    'auditable_id',
    'property_id',
    'user_id',
    'old_values',
    'new_values',
    'ip_address',
    'device_label',
    'summary',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Audit logs cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit logs cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Hide money amounts from users who cannot view financial data.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>
     */
    public function present(?array $values): array
    {
        $values ??= [];
        $viewer = auth()->user();

        if ($viewer instanceof User && $viewer->hasPermission(Permission::FinancialView)) {
            return $values;
        }

        $hidden = config('access.financial_attributes');
        $hidden = is_array($hidden) ? $hidden : [];

        foreach ($hidden as $attribute) {
            if (is_string($attribute)) {
                unset($values[$attribute]);
            }
        }

        return $values;
    }
}
