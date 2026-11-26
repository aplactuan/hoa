<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\PenaltyAmnestyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A penalty waiver: late penalties assessed on or before the end date are held back. Dues paid in full
 * by the end date keep no penalty; dues still unpaid get every held penalty reinstated the next day.
 *
 * @property int $id
 * @property string $name
 * @property Carbon $ends_on
 * @property bool $is_active
 */
#[Fillable(['name', 'ends_on', 'is_active', 'created_by'])]
class PenaltyAmnesty extends Model
{
    /** @use HasFactory<PenaltyAmnestyFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ends_on' => 'date', 'is_active' => 'boolean'];
    }
}
