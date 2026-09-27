<?php

namespace App\Actions\Auth;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterCustomer
{
    /**
     * @param  array{name: string, email: string, password: string, phone?: string|null}  $data
     */
    public function handle(array $data, string $locale): User
    {
        $user = DB::transaction(function () use ($data, $locale): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'locale' => $locale,
                'timezone' => config('app.display_timezone'),
            ]);

            $user->syncRoles([RoleSlug::Customer->value]);
            $user->settings()->create();

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
