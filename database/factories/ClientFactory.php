<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $type = fake()->randomElement(ClientType::cases());

        return [
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'name' => fake()->name(),
            'company' => $type === ClientType::Organization ? fake()->company() : null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+91 ##########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'India',
            'postal_code' => fake()->postcode(),
            'pan' => strtoupper(fake()->bothify('?????####?')),
        ];
    }
}
