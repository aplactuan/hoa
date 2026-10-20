<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DeviceKind;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(
        AuditAction $action,
        ?Model $model = null,
        array $old = [],
        array $new = [],
        ?string $summary = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'action' => $action,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'property_id' => $model instanceof Property
                ? $model->getKey()
                : $model?->getAttribute('property_id'),
            'user_id' => auth()->id(),
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            'ip_address' => request()->ip(),
            'device_label' => $this->deviceLabel(),
            'summary' => $summary,
        ]);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function payment(Model $model, array $old = [], array $new = [], ?string $summary = null): AuditLog
    {
        return $this->log(AuditAction::Payment, $model, $old, $new, $summary);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function void(Model $model, array $old = [], array $new = [], ?string $summary = null): AuditLog
    {
        return $this->log(AuditAction::Void, $model, $old, $new, $summary);
    }

    public function export(?Model $model = null, ?string $summary = null): AuditLog
    {
        return $this->log(AuditAction::Export, $model, summary: $summary);
    }

    public function deviceLabel(): string
    {
        $deviceId = session('device_id');

        if (is_numeric($deviceId)) {
            $name = Device::query()
                ->whereKey((int) $deviceId)
                ->where('is_active', true)
                ->value('name');

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        $user = auth()->user();

        if ($user instanceof User && $user->hasRole(RoleName::SecurityGuard)) {
            $tablet = Device::query()
                ->where('kind', DeviceKind::Tablet)
                ->where('is_active', true)
                ->value('name');

            if (is_string($tablet) && $tablet !== '') {
                return $tablet;
            }
        }

        return 'Unassigned device';
    }
}
