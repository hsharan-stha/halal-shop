<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use App\Services\Media\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class BrandController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:brands.view', only: ['index']),
            new Middleware('can:brands.manage', except: ['index']),
        ];
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        $brands = Brand::query()
            ->withCount('products')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('japanese_name', 'like', '%'.$search.'%')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(config('shop.pagination.admin'))
            ->withQueryString();

        return view('admin.brands.index', ['brands' => $brands, 'search' => $search]);
    }

    public function create(): View
    {
        return view('admin.brands.form', ['brand' => new Brand(['is_active' => true])]);
    }

    public function store(BrandRequest $request, ImageStorage $images): RedirectResponse
    {
        $brand = new Brand($request->attributesForModel());

        if ($request->hasFile('logo')) {
            $brand->logo_path = $images->store($request->file('logo'), 'brands', 800)['path'];
        }

        $brand->save();

        return redirect()->route('admin.brands.index')->with('success', __('admin.brands.created'));
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.form', ['brand' => $brand]);
    }

    public function update(BrandRequest $request, Brand $brand, ImageStorage $images): RedirectResponse
    {
        $brand->fill($request->attributesForModel());
        $previous = $brand->logo_path;

        if ($request->hasFile('logo')) {
            $brand->logo_path = $images->store($request->file('logo'), 'brands', 800)['path'];
        } elseif ($request->boolean('logo_remove')) {
            $brand->logo_path = null;
        }

        $brand->save();

        if ($previous && $previous !== $brand->logo_path) {
            $images->delete($previous);
        }

        return redirect()->route('admin.brands.index')->with('success', __('admin.brands.updated'));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->with('error', __('admin.brands.has_products'));
        }

        $brand->delete();

        return redirect()->route('admin.brands.index')->with('success', __('admin.brands.deleted'));
    }
}
