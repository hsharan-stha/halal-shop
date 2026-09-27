<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificationStatus;
use App\Enums\HalalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HalalCertificationRequest;
use App\Models\Brand;
use App\Models\HalalCertification;
use App\Models\Product;
use App\Services\Catalog\HalalCertificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HalalCertificationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:halal_certificates.view', only: ['index', 'show', 'file']),
            new Middleware('can:halal_certificates.manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
            new Middleware('can:halal_certificates.verify', only: ['verify', 'reject']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(CertificationStatus::class)],
            'expiry' => ['nullable', 'in:expiring,expired'],
        ]);

        $warningDays = (int) (collect(settings('halal.certificate_expiry_warning_days'))->max() ?? 30);
        $today = HalalCertification::today();

        $certifications = HalalCertification::query()
            ->with(['brand:id,name,japanese_name'])
            ->withCount('products')
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(fn ($inner) => $inner
                ->where('certifying_body', 'like', '%'.$q.'%')
                ->orWhere('certificate_number', 'like', '%'.$q.'%')))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($filters['expiry'] ?? null) === 'expiring', fn ($query) => $query->expiringWithin($warningDays))
            ->when(($filters['expiry'] ?? null) === 'expired', fn ($query) => $query->whereDate('expires_at', '<', $today))
            ->orderBy('expires_at')
            ->paginate(config('shop.pagination.admin'))
            ->withQueryString();

        $summary = [
            'verified' => HalalCertification::query()->valid()->count(),
            'pending' => HalalCertification::query()->where('status', CertificationStatus::Pending)->count(),
            'expiring' => HalalCertification::query()->expiringWithin($warningDays)->count(),
            'expired' => HalalCertification::query()->where('status', CertificationStatus::Verified)->whereDate('expires_at', '<', $today)->count(),
            'unsupported_products' => Product::query()->where('halal_status', HalalStatus::Certified)
                ->whereDoesntHave('halalCertifications', fn ($query) => $query->valid())->count(),
        ];

        return view('admin.halal.index', compact('certifications', 'filters', 'summary', 'warningDays'));
    }

    public function create(Request $request): View
    {
        $certification = new HalalCertification(['brand_id' => $request->integer('brand') ?: null]);

        return view('admin.halal.form', $this->formData($certification, $request->integer('product') ? [$request->integer('product')] : []));
    }

    public function store(HalalCertificationRequest $request, HalalCertificationService $service): RedirectResponse
    {
        $certification = $service->save(new HalalCertification, $request->attributesForModel(), $request->productIds(), $request->file('file'));

        return redirect()->route('admin.halal-certifications.show', $certification)->with('success', __('admin.halal.created'));
    }

    public function show(HalalCertification $certification, HalalCertificationService $service): View
    {
        $certification->load(['brand', 'verifier', 'products' => fn ($query) => $query->with('category:id,name,japanese_name')]);

        return view('admin.halal.show', [
            'certification' => $certification,
            'fileUrl' => $service->fileUrl($certification),
        ]);
    }

    public function edit(HalalCertification $certification): View
    {
        return view('admin.halal.form', $this->formData($certification, $certification->products()->pluck('products.id')->all()));
    }

    public function update(HalalCertificationRequest $request, HalalCertification $certification, HalalCertificationService $service): RedirectResponse
    {
        $wasVerified = $certification->status === CertificationStatus::Verified;
        $service->save($certification, $request->attributesForModel(), $request->productIds(), $request->file('file'));

        $message = $wasVerified && $certification->status === CertificationStatus::Pending ? 'admin.halal.updated_needs_verification' : 'admin.halal.updated';

        return redirect()->route('admin.halal-certifications.show', $certification)->with('success', __($message));
    }

    public function destroy(HalalCertification $certification, HalalCertificationService $service): RedirectResponse
    {
        $service->delete($certification);

        return redirect()->route('admin.halal-certifications.index')->with('success', __('admin.halal.deleted'));
    }

    public function verify(Request $request, HalalCertification $certification, HalalCertificationService $service): RedirectResponse
    {
        if (! $certification->hasFile()) {
            return back()->with('error', __('admin.halal.verify_requires_file'));
        }

        if ($certification->isExpired()) {
            return back()->with('error', __('admin.halal.verify_expired'));
        }

        $service->verify($certification, $request->user());

        return back()->with('success', __('admin.halal.verified'));
    }

    public function reject(Request $request, HalalCertification $certification, HalalCertificationService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $service->reject($certification, $request->user(), $data['reason']);

        return back()->with('success', __('admin.halal.rejected'));
    }

    /**
     * Served only through short-lived signed URLs to authorised staff.
     */
    public function file(HalalCertification $certification, HalalCertificationService $service): StreamedResponse
    {
        abort_unless($certification->hasFile() && $service->disk()->exists($certification->file_path), 404);

        $extension = pathinfo($certification->file_path, PATHINFO_EXTENSION);

        return $service->disk()->response($certification->file_path, 'certificate-'.$certification->id.'.'.$extension, [
            'Content-Type' => $certification->file_mime ?: 'application/octet-stream',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    /**
     * @param  list<int>  $selectedProductIds
     * @return array<string, mixed>
     */
    private function formData(HalalCertification $certification, array $selectedProductIds): array
    {
        return [
            'certification' => $certification,
            'brands' => Brand::query()->orderBy('name')->get()->mapWithKeys(fn (Brand $brand) => [$brand->id => $brand->localizedName()])->all(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'japanese_name', 'sku', 'brand_id']),
            'selectedProductIds' => $selectedProductIds,
        ];
    }
}
