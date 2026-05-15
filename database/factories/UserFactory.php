<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'sales',
            'is_active' => true,
        ];
    }
}
