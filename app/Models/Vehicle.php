<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'property_id', 'driver_name', 'type', 'make', 'model', 'color', 'plate_no', 'normalized_plate', 'parking', 'status', 'remarks', 'created_by', 'updated_by'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function stickers(): HasMany
    {
        return $this->hasMany(VehicleSticker::class);
    }
}
