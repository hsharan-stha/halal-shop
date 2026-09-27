<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Catalog\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AttemptLogin $attemptLogin, WishlistService $wishlist): RedirectResponse
    {
        $user = $attemptLogin->handle($request->string('email'), $request->string('password'), (string) $request->ip());

        Auth::login($user, $request->boolean('remember'));
        $wishlist->mergeSessionInto($user);
        $request->session()->regenerate();

        $fallback = $user->isStaff() ? route('admin.dashboard') : route('account.dashboard');

        return redirect()->intended($fallback);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $wasStaff = (bool) $request->user()?->isStaff();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($wasStaff ? 'admin.login' : 'home');
    }
}
