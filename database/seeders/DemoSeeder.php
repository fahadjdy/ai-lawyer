<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleType;
use App\Models\Client;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds a fully populated demo firm so the UI has realistic content out of the box.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::create([
            'name' => 'Sterling & Associates',
            'slug' => 'sterling-associates',
            'email' => 'contact@sterling.legal',
            'phone' => '+91 22 4000 1000',
            'registration_no' => 'BAR/MH/2015/0042',
        ]);

        // Scope all subsequent creates to this firm.
        TeamContext::set($team->id);

        $owner = $this->makeUser($team->id, 'Aarav Sterling', 'admin@lexcase.test', 'Managing Partner', RoleType::FirmOwner);
        $team->update(['owner_id' => $owner->id]);

        $partner = $this->makeUser($team->id, 'Priya Menon', 'priya@lexcase.test', 'Senior Partner', RoleType::Partner);
        $associate = $this->makeUser($team->id, 'Rohan Kapoor', 'rohan@lexcase.test', 'Associate', RoleType::Associate);
        $paralegal = $this->makeUser($team->id, 'Sara Khan', 'sara@lexcase.test', 'Paralegal', RoleType::Paralegal);

        $lawyers = collect([$owner, $partner, $associate]);

        $clients = Client::factory()
            ->count(12)
            ->create(['team_id' => $team->id, 'created_by' => $owner->id]);

        LegalCase::factory()
            ->count(24)
            ->create(['team_id' => $team->id, 'created_by' => $owner->id])
            ->each(function (LegalCase $case) use ($clients, $lawyers, $paralegal): void {
                $case->update([
                    'client_id' => $clients->random()->id,
                    'lead_lawyer_id' => $lawyers->random()->id,
                ]);

                $case->assignees()->sync($lawyers->random(rand(1, 2))->pluck('id')->all());

                Hearing::factory()
                    ->count(rand(1, 4))
                    ->create(['team_id' => $case->team_id, 'case_id' => $case->id, 'created_by' => $case->lead_lawyer_id]);

                Task::factory()
                    ->count(rand(2, 6))
                    ->create([
                        'team_id' => $case->team_id,
                        'case_id' => $case->id,
                        'assigned_to' => $lawyers->push($paralegal)->random()->id,
                        'created_by' => $case->lead_lawyer_id,
                    ]);
            });

        TeamContext::flush();

        $this->command?->info('Demo firm seeded. Login: admin@lexcase.test / password');
    }

    private function makeUser(int $teamId, string $name, string $email, string $designation, RoleType $role): User
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'team_id' => $teamId,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'designation' => $designation,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->assignRole($role->value);
        $user->team->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);

        return $user;
    }
}
