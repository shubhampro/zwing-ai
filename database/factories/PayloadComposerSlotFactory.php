<?php

namespace Database\Factories;

use App\Enums\PayloadComposerSlotShape;
use App\Models\PayloadComposer;
use App\Models\PayloadComposerSlot;
use App\Models\SavedSqlQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayloadComposerSlot>
 */
class PayloadComposerSlotFactory extends Factory
{
    protected $model = PayloadComposerSlot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payload_composer_id' => PayloadComposer::factory(),
            'key' => 'stockAuditItems',
            'saved_sql_query_id' => SavedSqlQuery::factory(),
            'shape' => PayloadComposerSlotShape::Array,
            'sort_order' => 0,
        ];
    }
}
