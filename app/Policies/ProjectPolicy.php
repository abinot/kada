<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'فهرست نیازمندان',
            'فهرست خیرین',
            'فهرست کار',
            'گزارش ماهانه',
        ]).' '.fake()->numberBetween(1, 999);

        return [
            'team_id' => Team::factory(),
            'name' => $name,
            'description' => fake()->optional()->paragraph(),
            'settings' => null,
            // uuid و id خودکار
        ];
    }
}