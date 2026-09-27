<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\Shop;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Infrastructure
|--------------------------------------------------------------------------
*/

Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::post('/locale/{locale}', LocaleController::class)->name('locale.switch')->where('locale', '[a-z]{2}');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [Auth\LoginController::class, 'create'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [Auth\RegisterController::class, 'create'])->name('register');
    Route::post('/register', [Auth\RegisterController::class, 'store'])->middleware('throttle:forms');
    Route::get('/password/forgot', [Auth\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/password/forgot', [Auth\PasswordResetController::class, 'email'])->middleware('throttle:forms')->name('password.email');
    Route::get('/password/reset/{token}', [Auth\PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/password/reset', [Auth\PasswordResetController::class, 'update'])->middleware('throttle:forms')->name('password.update');

    Route::get('/admin/login', [Admin\Auth\AdminLoginController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [Admin\Auth\AdminLoginController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [Auth\LoginController::class, 'destroy'])->name('logout');
    Route::get('/email/verify', [Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [Auth\EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/

Route::middleware('store')->group(function (): void {
    Route::get('/', Shop\HomeController::class)->name('home');
    Route::get('/shop', [Shop\ProductController::class, 'index'])->name('shop.index');
    Route::get('/search', Shop\SearchController::class)->name('search');
    Route::get('/products/{product:slug}', [Shop\ProductController::class, 'show'])->name('products.show');
    Route::get('/categories', [Shop\CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category:slug}', [Shop\CategoryController::class, 'show'])->name('categories.show');
    Route::get('/brands/{brand:slug}', [Shop\BrandController::class, 'show'])->name('brands.show');

    Route::get('/cart', [Shop\CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [Shop\CartController::class, 'store'])->middleware('throttle:60,1')->name('cart.store');
    Route::patch('/cart/{variant}', [Shop\CartController::class, 'update'])->middleware('throttle:60,1')->name('cart.update');
    Route::delete('/cart/{variant}', [Shop\CartController::class, 'destroy'])->middleware('throttle:60,1')->name('cart.destroy');

    Route::middleware('auth')->group(function (): void {
        Route::get('/checkout', [Shop\CheckoutController::class, 'create'])->name('checkout.create');
        Route::post('/checkout', [Shop\CheckoutController::class, 'store'])->middleware('throttle:forms')->name('checkout.store');
    });

    Route::get('/wishlist', [Account\WishlistController::class, 'index'])->name('account.wishlist');
    Route::post('/wishlist/{product:slug}', [Account\WishlistController::class, 'store'])->middleware('throttle:60,1')->name('wishlist.store');
    Route::post('/wishlist/{product:slug}/cart', [Account\WishlistController::class, 'moveToCart'])->middleware('throttle:60,1')->name('wishlist.cart');
    Route::delete('/wishlist/{product:slug}', [Account\WishlistController::class, 'destroy'])->middleware('throttle:60,1')->name('wishlist.destroy');

    Route::middleware(['auth'])->prefix('account')->name('account.')->group(function (): void {
        Route::get('/', Account\DashboardController::class)->name('dashboard');
        Route::get('/orders', [Account\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [Account\OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/cancel', [Account\OrderController::class, 'cancel'])->name('orders.cancel');
        Route::get('/profile', [Account\ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [Account\ProfileController::class, 'update'])->middleware('throttle:uploads')->name('profile.update');
        Route::get('/security', [Account\SecurityController::class, 'edit'])->name('security');
        Route::put('/security/password', [Account\SecurityController::class, 'updatePassword'])->name('password.update');
        Route::delete('/security/sessions', [Account\SecurityController::class, 'destroyOtherSessions'])->name('sessions.destroy');
        Route::delete('/security/tokens/{tokenId}', [Account\SecurityController::class, 'destroyToken'])->whereNumber('tokenId')->name('tokens.destroy');
        Route::get('/settings', [Account\PreferencesController::class, 'edit'])->name('settings');
        Route::put('/settings', [Account\PreferencesController::class, 'update'])->name('settings.update');
        Route::get('/privacy', [Account\PrivacyController::class, 'show'])->name('privacy');
        Route::get('/privacy/export', [Account\PrivacyController::class, 'export'])->middleware('throttle:6,1')->name('privacy.export');
        Route::post('/privacy/deactivate', [Account\PrivacyController::class, 'deactivate'])->name('privacy.deactivate');
        Route::delete('/privacy', [Account\PrivacyController::class, 'destroy'])->name('privacy.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::post('/products/bulk', [Admin\ProductController::class, 'bulk'])->name('products.bulk');
    Route::post('/products/{product}/duplicate', [Admin\ProductController::class, 'duplicate'])->name('products.duplicate');
    Route::post('/products/{product}/restore', [Admin\ProductController::class, 'restore'])->whereNumber('product')->name('products.restore');
    Route::resource('products', Admin\ProductController::class)->except('show');
    Route::resource('categories', Admin\CategoryController::class)->except('show');
    Route::resource('brands', Admin\BrandController::class)->except('show');

    Route::get('/halal-certifications/{certification}/file', [Admin\HalalCertificationController::class, 'file'])->middleware('signed')->name('halal-certifications.file');
    Route::post('/halal-certifications/{certification}/verify', [Admin\HalalCertificationController::class, 'verify'])->name('halal-certifications.verify');
    Route::post('/halal-certifications/{certification}/reject', [Admin\HalalCertificationController::class, 'reject'])->name('halal-certifications.reject');
    Route::resource('halal-certifications', Admin\HalalCertificationController::class)->parameters(['halal-certifications' => 'certification']);

    Route::get('/inventory', [Admin\InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/{item}', [Admin\InventoryController::class, 'show'])->name('inventory.show');
    Route::put('/inventory/{item}', [Admin\InventoryController::class, 'update'])->name('inventory.update');
    Route::get('/inventory/{item}/receive', [Admin\InventoryController::class, 'receiveForm'])->name('inventory.receive');
    Route::post('/inventory/{item}/receive', [Admin\InventoryController::class, 'receive'])->name('inventory.receive.store');
    Route::post('/inventory/{item}/adjust', [Admin\InventoryController::class, 'adjust'])->name('inventory.adjust');

    Route::get('/batches', [Admin\InventoryBatchController::class, 'index'])->name('batches.index');
    Route::get('/batches/{batch}', [Admin\InventoryBatchController::class, 'show'])->name('batches.show');
    Route::post('/batches/{batch}/adjust', [Admin\InventoryBatchController::class, 'adjust'])->name('batches.adjust');
    Route::post('/batches/{batch}/dispose', [Admin\InventoryBatchController::class, 'dispose'])->name('batches.dispose');
    Route::post('/batches/{batch}/transfer', [Admin\InventoryBatchController::class, 'transfer'])->name('batches.transfer');
    Route::post('/batches/{batch}/status', [Admin\InventoryBatchController::class, 'status'])->name('batches.status');

    Route::resource('suppliers', Admin\SupplierController::class);
    Route::post('/suppliers/{supplier}/products', [Admin\SupplierProductController::class, 'store'])->name('suppliers.products.store');
    Route::delete('/suppliers/{supplier}/products/{supplierProduct}', [Admin\SupplierProductController::class, 'destroy'])->scopeBindings()->name('suppliers.products.destroy');

    Route::post('/purchase-orders/{purchase_order}/order', [Admin\PurchaseOrderController::class, 'order'])->name('purchase-orders.order');
    Route::post('/purchase-orders/{purchase_order}/cancel', [Admin\PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::get('/purchase-orders/{purchase_order}/receive', [Admin\PurchaseOrderController::class, 'receiveForm'])->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchase_order}/receive', [Admin\PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive.store');
    Route::resource('purchase-orders', Admin\PurchaseOrderController::class);

    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/pay', [Admin\OrderController::class, 'pay'])->name('orders.pay');
    Route::post('/orders/{order}/ship', [Admin\OrderController::class, 'ship'])->name('orders.ship');
    Route::post('/orders/{order}/complete', [Admin\OrderController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{order}/cancel', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/tax', [Admin\TaxController::class, 'index'])->name('tax.index');
    Route::get('/tax/create', [Admin\TaxController::class, 'create'])->name('tax.create');
    Route::post('/tax', [Admin\TaxController::class, 'store'])->name('tax.store');
    Route::get('/tax/{taxClass}/edit', [Admin\TaxController::class, 'edit'])->name('tax.edit');
    Route::put('/tax/{taxClass}', [Admin\TaxController::class, 'update'])->name('tax.update');
    Route::delete('/tax/{taxClass}', [Admin\TaxController::class, 'destroy'])->name('tax.destroy');

    Route::get('/settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/{group}', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings/{group}', [Admin\SettingsController::class, 'update'])->name('settings.update');

    Route::get('/roles', [Admin\RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/{role}/edit', [Admin\RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');

    Route::get('/staff', [Admin\StaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [Admin\StaffController::class, 'create'])->name('staff.create');
    Route::post('/staff', [Admin\StaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{member}/edit', [Admin\StaffController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{member}', [Admin\StaffController::class, 'update'])->name('staff.update');
    Route::post('/staff/{member}/toggle-status', [Admin\StaffController::class, 'toggleStatus'])->name('staff.toggle-status');

    Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/system/health', Admin\SystemHealthController::class)->name('system.health');
});
