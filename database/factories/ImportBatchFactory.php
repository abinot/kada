<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'original_filename' => fake()->word().'.json',
            'format' => 'json',
            'record_count' => fake()->numberBetween(1, 500),
            'file_size' => fake()->numberBetween(1024, 1048576),
            'status' => 'completed',
        ];
    }
}