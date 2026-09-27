<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:settings.view', only: ['index', 'edit']),
            new Middleware('can:settings.update', only: ['update']),
        ];
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.edit', 'general');
    }

    public function edit(string $group, SettingsService $settings): View
    {
        abort_unless(SettingsRegistry::hasGroup($group), 404);

        return view('admin.settings.edit', [
            'group' => $group,
            'groups' => array_keys(SettingsRegistry::groups()),
            'fields' => SettingsRegistry::groups()[$group],
            'values' => $settings->group($group),
        ]);
    }

    public function update(Request $request, string $group, SettingsService $settings): RedirectResponse
    {
        abort_unless(SettingsRegistry::hasGroup($group), 404);

        $validated = $request->validate(SettingsRegistry::rules($group));

        foreach (SettingsRegistry::groups()[$group] as $key => $field) {
            if ($field['type'] === 'image' && $request->hasFile($key)) {
                $validated[$key] = $request->file($key);
            }
        }

        $settings->update($group, $validated);

        return redirect()->route('admin.settings.edit', $group)->with('success', __('admin.settings.saved'));
    }
}
