<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Record>
 */
class RecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->optional()->sentence(3),
            'note' => null,
        ];
    }
}