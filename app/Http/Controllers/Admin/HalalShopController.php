<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HalalShopRequest;
use App\Models\Shop;
use App\Models\User;
use App\Rules\JapanesePhone;
use App\Services\AuditLogger;
use App\Support\Prefectures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HalalShopController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:shops.manage', except: ['editMine', 'updateMine']),
        ];
    }

    public function index(): View
    {
        $shops = Shop::query()->withCount(['products', 'orders'])->orderBy('name')->paginate(config('shop.pagination.admin'));

        return view('admin.halal-shops.index', ['shops' => $shops]);
    }

    public function create(): View
    {
        return view('admin.halal-shops.form', ['shop' => new Shop(['is_active' => true]), 'owns' => false]);
    }

    public function store(HalalShopRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $shop = DB::transaction(function () use ($request): Shop {
            $shop = new Shop($request->shopAttributes());
            $shop->slug = $this->uniqueSlug($shop->name);
            $shop->save();
            $this->createLogin($shop, $request->validated());

            return $shop;
        });

        $auditLogger->log('shop.created', $shop, null, ['name' => $shop->name]);

        return redirect()->route('admin.halal-shops.edit', $shop)->with('success', __('admin.halal_shops.created'));
    }

    public function edit(Shop $halalShop): View
    {
        return view('admin.halal-shops.form', ['shop' => $halalShop, 'owns' => false]);
    }

    public function update(HalalShopRequest $request, Shop $halalShop, AuditLogger $auditLogger): RedirectResponse
    {
        DB::transaction(function () use ($request, $halalShop): void {
            $halalShop->fill($request->shopAttributes());

            if ($halalShop->isDirty('name')) {
                $halalShop->slug = $this->uniqueSlug($halalShop->name, $halalShop->id);
            }

            $halalShop->save();

            if (filled($request->validated('user_email'))) {
                $this->createLogin($halalShop, $request->validated());
            }
        });

        $auditLogger->log('shop.updated', $halalShop);

        return redirect()->route('admin.halal-shops.edit', $halalShop)->with('success', __('admin.halal_shops.updated'));
    }

    public function destroy(Shop $halalShop): RedirectResponse
    {
        if ($halalShop->products()->exists() || $halalShop->orders()->exists()) {
            return back()->with('error', __('admin.halal_shops.has_records'));
        }

        $halalShop->users()->update(['shop_id' => null]);
        $halalShop->delete();

        return redirect()->route('admin.halal-shops.index')->with('success', __('admin.halal_shops.deleted'));
    }

    public function editMine(Request $request): View
    {
        return view('admin.halal-shops.form', ['shop' => $this->ownShop($request), 'owns' => true]);
    }

    public function updateMine(Request $request): RedirectResponse
    {
        $shop = $this->ownShop($request);
        $digits = preg_replace('/\D/', '', (string) $request->input('postal_code')) ?? '';

        if (strlen($digits) === 7) {
            $request->merge(['postal_code' => substr($digits, 0, 3).'-'.substr($digits, 3)]);
        }

        if (is_string($request->input('phone')) && $request->input('phone') !== '') {
            $request->merge(['phone' => JapanesePhone::normalize($request->input('phone'))]);
        }

        $data = $request->validate([
            'phone' => ['nullable', 'string', new JapanesePhone],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'postal_code' => ['required', 'string', 'regex:/^\d{3}-\d{4}$/'],
            'prefecture' => ['required', 'string', Rule::in(Prefectures::names())],
            'city' => ['required', 'string', 'max:80'],
            'town' => ['required', 'string', 'max:80'],
            'street' => ['required', 'string', 'max:80'],
            'building' => ['nullable', 'string', 'max:80'],
            'latitude' => ['required', 'numeric', 'between:20,46'],
            'longitude' => ['required', 'numeric', 'between:122,154'],
        ]);

        $shop->fill($data)->save();

        return back()->with('success', __('admin.halal_shops.updated'));
    }

    private function ownShop(Request $request): Shop
    {
        $user = $request->user();
        $user?->loadMissing('roles');
        abort_unless($user?->hasRole(RoleSlug::HalalShop) && $user->shop_id, 403);

        return Shop::query()->findOrFail($user->shop_id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createLogin(Shop $shop, array $data): void
    {
        $user = User::query()->create([
            'name' => $data['user_name'],
            'email' => $data['user_email'],
            'password' => $data['user_password'],
            'locale' => config('app.locale'),
            'timezone' => config('app.display_timezone'),
        ]);

        $user->syncRoles([RoleSlug::HalalShop->value]);
        $user->forceFill([
            'shop_id' => $shop->id,
            'email_verified_at' => now(),
        ])->save();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'shop';
        $slug = $base;
        $suffix = 2;

        while (Shop::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
