<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\BillingPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['year', 'month', 'generated_at', 'generated_by'])]
class BillingPeriod extends Model
{
    /** @use HasFactory<BillingPeriodFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'generated_at' => 'datetime'];
    }

    public function statements(): HasMany
    {
        return $this->hasMany(DuesStatement::class);
    }
}
