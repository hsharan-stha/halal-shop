<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Catalog\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        abort_unless(feature('registration_enabled'), 404);

        return view('auth.register');
    }

    public function store(RegisterRequest $request, RegisterCustomer $registerCustomer, WishlistService $wishlist): RedirectResponse
    {
        $user = $registerCustomer->handle($request->validated(), app()->getLocale());

        Auth::login($user);
        $wishlist->mergeSessionInto($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
