<?php

namespace Database\Factories;

use App\Models\Record;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecordValue>
 */
class RecordValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'record_id' => Record::factory(),
            'parent_id' => null,
            'key' => fake()->word(),
            'label' => null,
            'value_type' => 'string',
            'value_string' => fake()->word(),
            'sort_order' => 0,
            'kind' => 'field',
        ];
    }
}