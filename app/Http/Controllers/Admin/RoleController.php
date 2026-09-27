<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:staff.view', only: ['index']),
            new Middleware('can:roles.manage', only: ['edit', 'update']),
        ];
    }

    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('id')->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        abort_if($role->slug === RoleSlug::SuperAdmin->value, 403);

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'groups' => PermissionCatalog::grouped(),
        ]);
    }

    public function update(Request $request, Role $role, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if(in_array($role->slug, [RoleSlug::SuperAdmin->value, RoleSlug::Customer->value], true), 403);

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::all())],
        ]);

        $old = $role->permissions()->pluck('slug')->sort()->values()->all();
        $ids = Permission::query()->whereIn('slug', $data['permissions'] ?? [])->pluck('id');
        $role->permissions()->sync($ids);
        $new = $role->permissions()->pluck('slug')->sort()->values()->all();

        $auditLogger->log('role.permissions_updated', $role, ['permissions' => $old], ['permissions' => $new]);

        return redirect()->route('admin.roles.index')->with('success', __('admin.roles.updated'));
    }
}
