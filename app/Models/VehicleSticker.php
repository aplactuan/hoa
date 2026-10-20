<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\VehicleStickerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'vehicle_id', 'sticker_no', 'year', 'issued_on', 'expires_on', 'status', 'charge_id', 'created_by', 'updated_by'])]
class VehicleSticker extends Model
{
    /** @use HasFactory<VehicleStickerFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['year' => 'integer', 'issued_on' => 'date', 'expires_on' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
