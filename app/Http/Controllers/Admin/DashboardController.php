<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:dashboard.view')];
    }

    public function __invoke(Request $request, DashboardMetrics $metrics): View
    {
        return view('admin.dashboard', ['cards' => $metrics->cards($request->user()), 'alerts' => $metrics->alerts($request->user())]);
    }
}
