<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HearingStatus;
use App\Models\Hearing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hearing>
 */
class HearingFactory extends Factory
{
    protected $model = Hearing::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'scheduled_at' => fake()->dateTimeBetween('-1 month', '+2 months'),
            'status' => fake()->randomElement(HearingStatus::cases()),
            'purpose' => fake()->randomElement(['Arguments', 'Evidence', 'Final Hearing', 'Bail Hearing', 'Cross-examination']),
            'court_room' => 'Court '.fake()->numberBetween(1, 20),
            'judge_name' => 'Hon. '.fake()->name(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 month'),
            'status' => HearingStatus::Scheduled,
        ]);
    }
}
