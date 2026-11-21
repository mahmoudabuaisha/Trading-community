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
    public function definition(): array
    {
        $day = now()->toDateString();

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => '',
            'period_type' => 'daily',
            'period_start' => $day,
            'period_end' => $day,
            'priority' => fake()->randomElement(Task::PRIORITIES),
            'status' => 'pending',
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => Task::STATUS_COMPLETED]);
    }
}
