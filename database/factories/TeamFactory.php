<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Support\RolePermissions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Legal';

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }

    /**
     * Mirror production: a freshly-created firm gets its own per-team roles
     * provisioned (Spatie teams mode), and Spatie is pointed at it so subsequent
     * role/permission assignments in tests resolve correctly.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Team $team): void {
            if (! config('permission.teams')) {
                return;
            }

            RolePermissions::ensurePermissions();
            RolePermissions::provision($team->id);
            app(PermissionRegistrar::class)->setPermissionsTeamId($team->id);
        });
    }
}
