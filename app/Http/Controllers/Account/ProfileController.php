<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()->load('profile')]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): RedirectResponse
    {
        $user = $updateProfile->handle($request->user(), $request->validated(), $request->file('photo'));

        $request->session()->put('locale', $user->locale);

        return back()->with('success', __('shop.account.profile_updated'));
    }
}
