<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\DeleteAccount;
use App\Actions\Account\ExportPersonalData;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PrivacyController extends Controller
{
    public function show(Request $request, ExportPersonalData $export): View
    {
        return view('account.privacy', ['data' => $export->handle($request->user())]);
    }

    public function export(Request $request, ExportPersonalData $export): JsonResponse
    {
        $filename = 'personal-data-'.now()->format('Ymd-His').'.json';

        return response()->json($export->handle($request->user()), 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function deactivate(Request $request, DeleteAccount $deleteAccount, AuditLogger $auditLogger): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        $user->forceFill(['status' => UserStatus::Deactivated])->save();
        $auditLogger->log('user.deactivated', $user);

        $this->logout($request);
        $deleteAccount->revokeAccess($user);

        return redirect()->route('home')->with('success', __('shop.account.deactivated'));
    }

    public function destroy(Request $request, DeleteAccount $deleteAccount): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', 'accepted'],
        ]);

        $user = $request->user();
        $this->logout($request);
        $deleteAccount->handle($user);

        return redirect()->route('home')->with('success', __('shop.account.deleted'));
    }

    private function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
