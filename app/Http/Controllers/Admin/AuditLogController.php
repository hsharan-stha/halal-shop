<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class AuditLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:audit_logs.view')];
    }

    public function index(Request $request): View
    {
        $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', $request->string('action').'%'))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', local_time($request->string('from'))->startOfDay()->utc()))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', local_time($request->string('to'))->endOfDay()->utc()))
            ->latest('id')
            ->cursorPaginate(config('shop.pagination.admin'))
            ->withQueryString();

        return view('admin.audit-logs.index', ['logs' => $logs]);
    }
}
