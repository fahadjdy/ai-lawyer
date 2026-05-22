<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $status = fake()->randomElement(TaskStatus::cases());

        return [
            'uuid' => (string) Str::uuid(),
            'title' => fake()->sentence(5),
            'description' => fake()->optional()->paragraph(),
            'status' => $status,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'due_at' => fake()->dateTimeBetween('-1 week', '+3 weeks'),
            'completed_at' => $status === TaskStatus::Done ? fake()->dateTimeBetween('-1 week', 'now') : null,
            'position' => fake()->numberBetween(0, 100),
        ];
    }
}
