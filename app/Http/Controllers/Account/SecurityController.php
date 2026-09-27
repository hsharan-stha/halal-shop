<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Password changes plus active session and API token management.
 */
class SecurityController extends Controller
{
    public function edit(Request $request): View
    {
        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')
                ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
                ->map(fn (object $session) => (object) [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'agent' => $this->describeAgent((string) $session->user_agent),
                    'is_current' => $session->id === $request->session()->getId(),
                    'last_active' => Carbon::createFromTimestamp($session->last_activity),
                ])
            : collect();

        return view('account.security', [
            'sessions' => $sessions,
            'tokens' => $request->user()->tokens()->latest()->get(['id', 'name', 'last_used_at', 'created_at']),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], attributes: [
            'current_password' => __('shop.fields.current_password'),
            'password' => __('shop.fields.new_password'),
        ]);

        $request->user()->forceFill(['password' => $request->string('password')])->save();
        Auth::logoutOtherDevices($request->string('password'));
        $this->deleteOtherSessions($request);

        return back()->with('success', __('shop.account.password_updated'));
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices($request->string('password'));
        $this->deleteOtherSessions($request);

        return back()->with('success', __('shop.account.sessions_revoked'));
    }

    public function destroyToken(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->whereKey($tokenId)->delete();

        return back()->with('success', __('shop.account.token_revoked'));
    }

    private function deleteOtherSessions(Request $request): void
    {
        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
    }

    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => __('shop.account.unknown_browser'),
        };

        $platform = match (true) {
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim($browser.' · '.$platform, ' ·');
    }
}
