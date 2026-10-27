<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasAttachments;
use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $acquired_on
 * @property Carbon|null $verified_on
 * @property Carbon $started_on
 * @property Carbon|null $ended_on
 * @property bool $is_registered
 */
#[Fillable(['code', 'property_id', 'user_id', 'name', 'phone', 'email', 'mailing_address', 'civil_status', 'ownership_type', 'tct_no', 'tax_declaration_no', 'deed_reference', 'acquired_on', 'is_registered', 'verified_on', 'started_on', 'ended_on', 'status', 'remarks', 'created_by', 'updated_by'])]
class Owner extends Model
{
    /** @use HasFactory<OwnerFactory> */
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['acquired_on' => 'date', 'verified_on' => 'date', 'started_on' => 'date', 'ended_on' => 'date', 'is_registered' => 'boolean'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
