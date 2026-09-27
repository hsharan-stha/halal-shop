<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PreferencesController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.settings', ['user' => $request->user()->load('settings')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(config('shop.locales')))],
            'theme' => ['required', Rule::in(['system', 'light', 'dark'])],
            'marketing_emails' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $user->forceFill(['locale' => $data['locale']])->save();
        $user->settings()->updateOrCreate(['user_id' => $user->id], [
            'theme' => $data['theme'],
            'marketing_emails' => $request->boolean('marketing_emails'),
        ]);

        $request->session()->put('locale', $data['locale']);

        return back()->with('success', __('shop.account.settings_updated'));
    }
}
