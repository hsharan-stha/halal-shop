<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '090'.fake()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'locale' => 'ja',
            'timezone' => 'Asia/Tokyo',
            'status' => UserStatus::Active,
            'is_staff' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function customer(): static
    {
        return $this->withRole(RoleSlug::Customer);
    }

    public function withRole(RoleSlug $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->syncRoles([$role->value]);
        });
    }

    public function superAdmin(): static
    {
        return $this->withRole(RoleSlug::SuperAdmin);
    }
}
