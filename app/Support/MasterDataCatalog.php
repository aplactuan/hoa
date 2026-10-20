<?php

namespace App\Support;

use App\Models\ChargeType;
use App\Models\Facility;
use App\Models\PropertyType;
use App\Models\RequestCategory;
use App\Models\Section;
use App\Models\Street;
use App\Models\TransactionType;
use App\Models\ViolationType;
use Illuminate\Database\Eloquent\Model;

class MasterDataCatalog
{
    /**
     * @return array<string, array{label: string, model: class-string<Model>, fields: list<string>}>
     */
    public static function all(): array
    {
        return [
            'sections' => [
                'label' => 'Sections',
                'model' => Section::class,
                'fields' => [],
            ],
            'streets' => [
                'label' => 'Streets',
                'model' => Street::class,
                'fields' => ['section_id'],
            ],
            'property-types' => [
                'label' => 'Property types',
                'model' => PropertyType::class,
                'fields' => [],
            ],
            'transaction-types' => [
                'label' => 'Transaction types',
                'model' => TransactionType::class,
                'fields' => ['default_fee'],
            ],
            'charge-types' => [
                'label' => 'Charge types',
                'model' => ChargeType::class,
                'fields' => [],
            ],
            'violation-types' => [
                'label' => 'Violation types',
                'model' => ViolationType::class,
                'fields' => ['default_fine'],
            ],
            'request-categories' => [
                'label' => 'Request categories',
                'model' => RequestCategory::class,
                'fields' => [],
            ],
            'facilities' => [
                'label' => 'Facilities',
                'model' => Facility::class,
                'fields' => ['fee', 'deposit'],
            ],
        ];
    }

    /**
     * @return array{label: string, model: class-string<Model>, fields: list<string>}
     */
    public static function find(string $key): array
    {
        $catalog = self::all()[$key] ?? null;

        if ($catalog === null) {
            abort(404);
        }

        return $catalog;
    }
}
