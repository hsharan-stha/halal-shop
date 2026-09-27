<?php

use App\Http\Middleware\ApplyShopTenancy;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureStoreIsAvailable;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            SetLocale::class,
            EnsureAccountIsActive::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            SetLocale::class,
        ], append: [
            SecurityHeaders::class,
        ]);

        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ApplyShopTenancy::class,
        );

        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'shop.tenant' => ApplyShopTenancy::class,
            'store' => EnsureStoreIsAvailable::class,
            'active' => EnsureAccountIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isStaff() ? route('admin.dashboard') : route('account.dashboard'));

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $message, $errors] = match (true) {
                $e instanceof ValidationException => [422, __('api.validation_failed'), $e->errors()],
                $e instanceof AuthenticationException => [401, __('auth.unauthenticated'), []],
                $e instanceof AuthorizationException => [403, __('api.forbidden'), []],
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, __('api.not_found'), []],
                $e instanceof ThrottleRequestsException => [429, __('api.too_many_requests'), []],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage() ?: __('api.error'), []],
                default => [500, __('api.server_error'), []],
            };

            if ($status === 500 && config('app.debug')) {
                return null;
            }

            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json(array_filter([
                'success' => false,
                'message' => $message,
                'errors' => $errors ?: null,
            ], fn ($value) => $value !== null), $status, $headers);
        });
    })->create();
