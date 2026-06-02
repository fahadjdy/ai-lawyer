<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegalCase>
 */
class LegalCaseFactory extends Factory
{
    protected $model = LegalCase::class;

    public function definition(): array
    {
        $courts = ['Delhi High Court', 'Bombay High Court', 'Supreme Court of India', 'District Court, Pune', 'NCLT Mumbai'];

        return [
            'uuid' => (string) Str::uuid(),
            'case_number' => sprintf('CASE-%d-%05d', now()->year, fake()->unique()->numberBetween(1, 99999)),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'case_type' => fake()->randomElement(CaseType::cases()),
            'status' => fake()->randomElement(CaseStatus::cases()),
            'priority' => fake()->randomElement(CasePriority::cases()),
            // Most matters carry a favourability read; some are left unassessed.
            'favorability' => fake()->optional(0.8)->numberBetween(15, 92),
            'court_name' => fake()->randomElement($courts),
            'court_type' => fake()->randomElement(['High Court', 'District Court', 'Tribunal', 'Supreme Court']),
            'jurisdiction' => fake()->city(),
            'judge_name' => 'Hon. '.fake()->name(),
            'opposing_party' => fake()->name(),
            'opposing_counsel' => fake()->name(),
            'filing_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'next_hearing_at' => fake()->dateTimeBetween('now', '+3 months'),
            'tags' => fake()->randomElements(['urgent', 'appeal', 'high-value', 'pro-bono', 'retainer'], fake()->numberBetween(0, 3)),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => fake()->randomElement(CaseStatus::active())]);
    }
}
