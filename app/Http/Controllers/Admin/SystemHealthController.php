<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SystemHealthController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:system.health')];
    }

    public function __invoke(SystemHealthService $health): View
    {
        return view('admin.system.health', ['checks' => $health->checks()]);
    }
}
