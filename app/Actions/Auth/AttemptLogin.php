<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Credential verification shared by the web and API login flows.
 * Rate limited per email+IP; suspended accounts are rejected.
 */
class AttemptLogin
{
    public function handle(string $email, string $password, string $ip, bool $staffOnly = false): User
    {
        $throttleKey = 'login:'.Str::lower($email).'|'.$ip;
        $maxAttempts = (int) settings('security.login_max_attempts', 5);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            Log::warning('security.login_throttled', ['email_hash' => hash('sha256', Str::lower($email)), 'ip' => $ip]);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey), 'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60)]),
            ]);
        }

        $user = User::query()->where('email', Str::lower($email))->first();

        if (! $user || ! Hash::check($password, $user->password) || ($staffOnly && ! $user->isStaff())) {
            RateLimiter::hit($throttleKey, 60);
            event(new Failed('web', $user, ['email' => $email]));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('auth.suspended')]);
        }

        RateLimiter::clear($throttleKey);

        return $user;
    }
}
