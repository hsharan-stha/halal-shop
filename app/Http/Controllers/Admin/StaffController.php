<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:staff.view', only: ['index']),
            new Middleware('can:staff.manage', except: ['index']),
        ];
    }

    public function index(Request $request): View
    {
        $staff = User::query()
            ->staff()
            ->with('roles')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->orderBy('name')
            ->paginate(config('shop.pagination.admin'))
            ->withQueryString();

        return view('admin.staff.index', ['staff' => $staff]);
    }

    public function create(): View
    {
        return view('admin.staff.form', ['member' => new User, 'roles' => $this->assignableRoles()]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $member = DB::transaction(function () use ($data): User {
            $member = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'locale' => config('app.locale'),
            ]);
            $member->forceFill(['email_verified_at' => now()])->save();
            $member->syncRoles($data['roles']);

            return $member;
        });

        $auditLogger->log('staff.created', $member, null, ['email' => $member->email, 'roles' => $data['roles']]);

        return redirect()->route('admin.staff.index')->with('success', __('admin.staff.created'));
    }

    public function edit(User $member): View
    {
        abort_unless($member->isStaff(), 404);

        return view('admin.staff.form', ['member' => $member->load('roles'), 'roles' => $this->assignableRoles()]);
    }

    public function update(Request $request, User $member, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($member->isStaff(), 404);
        $this->guardSuperAdmin($request->user(), $member);

        $data = $request->validate($this->rules($member));
        $oldRoles = $member->roles->pluck('slug')->all();

        $member->fill(['name' => $data['name'], 'email' => Str::lower($data['email'])]);

        if (! empty($data['password'])) {
            $member->password = $data['password'];
        }

        $member->save();

        if ($member->isNot($request->user())) {
            $member->syncRoles($data['roles']);
        }

        $auditLogger->log('staff.updated', $member, ['roles' => $oldRoles], ['roles' => $member->roles()->pluck('slug')->all()]);

        return redirect()->route('admin.staff.index')->with('success', __('admin.staff.updated'));
    }

    public function toggleStatus(Request $request, User $member, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($member->isStaff(), 404);
        abort_if($member->is($request->user()), 422, __('admin.staff.cannot_suspend_self'));
        $this->guardSuperAdmin($request->user(), $member);

        $suspend = $member->status === UserStatus::Active;
        $member->forceFill([
            'status' => $suspend ? UserStatus::Suspended : UserStatus::Active,
            'suspended_at' => $suspend ? now() : null,
        ])->save();

        if ($suspend) {
            $member->tokens()->delete();
            DB::table('sessions')->where('user_id', $member->id)->delete();
        }

        $auditLogger->log($suspend ? 'staff.suspended' : 'staff.reactivated', $member);

        return back()->with('success', __($suspend ? 'admin.staff.suspended' : 'admin.staff.reactivated'));
    }

    /**
     * Only super admins may assign or modify the super admin role.
     */
    private function guardSuperAdmin(User $actor, User $member): void
    {
        abort_if($member->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);
    }

    /**
     * @return Collection<int, Role>
     */
    private function assignableRoles()
    {
        return Role::query()
            ->where('slug', '!=', RoleSlug::Customer->value)
            ->when(! request()->user()->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', RoleSlug::SuperAdmin->value))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?User $member = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($member?->id)],
            'password' => [$member ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($this->assignableRoles()->pluck('slug')->all())],
        ];
    }
}
