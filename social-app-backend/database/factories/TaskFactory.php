<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'active' => true,
            'author_id' => User::factory(),
        ];
    }

    /**
     * Soft-deleted / inactive task.
     */
    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
