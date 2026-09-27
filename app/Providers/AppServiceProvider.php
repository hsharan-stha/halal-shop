<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\HalalCertification;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BrandingService;
use App\Services\LocaleService;
use App\Services\SettingsService;
use App\Support\PermissionCatalog;
use App\Support\ShopAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(BrandingService::class);
        $this->app->singleton(LocaleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->configureShopTenancy();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configurePasswords();

        View::composer('*', function ($view): void {
            $view->with('branding', app(BrandingService::class));
        });

        Blade::if('feature', fn (string $flag) => feature($flag));

        Paginator::defaultView('pagination.default');
        Paginator::defaultSimpleView('pagination.simple');
    }

    private function configureShopTenancy(): void
    {
        $ownOnly = function (Builder $query): void {
            if (ShopAccess::$tenantId !== null) {
                $query->where($query->getModel()->getTable().'.shop_id', ShopAccess::$tenantId);
            }
        };

        $ownOrPlatform = function (Builder $query): void {
            if (ShopAccess::$tenantId !== null) {
                $column = $query->getModel()->getTable().'.shop_id';

                $query->where(fn (Builder $inner) => $inner->whereNull($column)->orWhere($column, ShopAccess::$tenantId));
            }
        };

        $throughRelation = fn (string $relation) => function (Builder $query) use ($relation): void {
            if (ShopAccess::$tenantId !== null) {
                $query->whereHas($relation);
            }
        };

        Product::addGlobalScope('shop-tenant', $ownOnly);
        Order::addGlobalScope('shop-tenant', $ownOnly);
        PurchaseOrder::addGlobalScope('shop-tenant', $ownOnly);

        Category::addGlobalScope('shop-tenant', $ownOrPlatform);
        Brand::addGlobalScope('shop-tenant', $ownOrPlatform);
        HalalCertification::addGlobalScope('shop-tenant', $ownOrPlatform);
        Supplier::addGlobalScope('shop-tenant', $ownOrPlatform);

        InventoryItem::addGlobalScope('shop-tenant', $throughRelation('variant.product'));
        InventoryBatch::addGlobalScope('shop-tenant', $throughRelation('item.variant.product'));
        SupplierProduct::addGlobalScope('shop-tenant', $throughRelation('variant.product'));
    }

    private function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->isActive()) {
                return false;
            }

            if (! PermissionCatalog::exists($ability)) {
                return null;
            }

            return $user->isStaff() && $user->hasPermission($ability);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $attempts = (int) settings('security.login_max_attempts', 5);

            return Limit::perMinute($attempts)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('forms', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }

    private function configurePasswords(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(max(8, (int) settings('security.password_min_length', 8)))->letters()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
