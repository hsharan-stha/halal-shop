<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordAuthenticationEvents
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        if ($event->user->isStaff()) {
            $this->auditLogger->log('admin.login', $event->user, actorId: $event->user->id);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User && $event->user->isStaff()) {
            $this->auditLogger->log('admin.logout', $event->user, actorId: $event->user->id);
        }
    }

    public function handleFailed(Failed $event): void
    {
        Log::warning('security.login_failed', [
            'email_hash' => hash('sha256', Str::lower((string) ($event->credentials['email'] ?? ''))),
            'ip' => request()->ip(),
            'known_user' => $event->user !== null,
        ]);
    }
}
