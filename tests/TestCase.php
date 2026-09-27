<?php

namespace Tests;

use App\Enums\RoleSlug;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles and permissions are reference data every test relies on.
     */
    protected bool $seed = true;

    protected string $seeder = RolePermissionSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function customer(array $attributes = []): User
    {
        return User::factory()->customer()->create($attributes);
    }

    protected function staff(RoleSlug $role = RoleSlug::Admin, array $attributes = []): User
    {
        return User::factory()->withRole($role)->create($attributes);
    }
}
