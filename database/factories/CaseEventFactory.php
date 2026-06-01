<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CaseStage;
use App\Models\LegalCase;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CaseEvent>
 */
class CaseEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'case_id' => LegalCase::factory(),
            'stage' => fake()->randomElement(CaseStage::cases()),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'sections' => fake()->randomElements(['420', '406', '467', '120B', '506', '323'], fake()->numberBetween(1, 3)),
            'occurred_on' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ];
    }
}
