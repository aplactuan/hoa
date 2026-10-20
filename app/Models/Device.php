<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\DeviceKind;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property DeviceKind $kind
 * @property string|null $location
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'kind', 'location', 'is_active'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DeviceKind::class,
            'is_active' => 'boolean',
        ];
    }
}
