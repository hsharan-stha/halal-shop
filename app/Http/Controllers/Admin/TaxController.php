<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxClassRequest;
use App\Models\TaxClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaxController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:tax.view', only: ['index']),
            new Middleware('can:tax.manage', except: ['index']),
        ];
    }

    public function index(): View
    {
        return view('admin.tax.index', [
            'classes' => TaxClass::query()->with('rates')->withCount('products')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.tax.form', [
            'taxClass' => new TaxClass(['is_default' => false, 'name' => ['ja' => '', 'en' => '']]),
            'rates' => [['percent' => '', 'effective_from' => now()->toDateString(), 'effective_to' => '']],
        ]);
    }

    public function store(TaxClassRequest $request): RedirectResponse
    {
        $taxClass = DB::transaction(function () use ($request): TaxClass {
            if ($request->boolean('is_default') || ! TaxClass::query()->where('is_default', true)->exists()) {
                TaxClass::query()->update(['is_default' => false]);
                $request->merge(['is_default' => true]);
            }

            $taxClass = TaxClass::query()->create($request->classAttributes());
            $this->syncRates($request, $taxClass);

            return $taxClass;
        });

        return redirect()->route('admin.tax.edit', $taxClass)->with('success', __('admin.tax.created'));
    }

    public function edit(TaxClass $taxClass): View
    {
        $taxClass->load('rates');

        return view('admin.tax.form', [
            'taxClass' => $taxClass,
            'rates' => $taxClass->rates
                ->sortBy('effective_from')
                ->map(fn ($rate): array => [
                    'id' => $rate->id,
                    'percent' => $rate->rate_bps / 100,
                    'effective_from' => $rate->effective_from->toDateString(),
                    'effective_to' => $rate->effective_to?->toDateString() ?? '',
                ])
                ->push(['percent' => '', 'effective_from' => '', 'effective_to' => ''])
                ->values()
                ->all(),
        ]);
    }

    public function update(TaxClassRequest $request, TaxClass $taxClass): RedirectResponse
    {
        if (! $request->boolean('is_default') && $taxClass->is_default) {
            return back()->with('error', __('admin.tax.default_required'));
        }

        DB::transaction(function () use ($request, $taxClass): void {
            if ($request->boolean('is_default')) {
                TaxClass::query()->whereKeyNot($taxClass->id)->update(['is_default' => false]);
            }

            $taxClass->update($request->classAttributes());
            $this->syncRates($request, $taxClass);
        });

        return redirect()->route('admin.tax.edit', $taxClass)->with('success', __('admin.tax.updated'));
    }

    public function destroy(TaxClass $taxClass): RedirectResponse
    {
        if ($taxClass->is_default) {
            return back()->with('error', __('admin.tax.default_required'));
        }

        if ($taxClass->products()->exists()) {
            return back()->with('error', __('admin.tax.has_products'));
        }

        $taxClass->delete();

        return redirect()->route('admin.tax.index')->with('success', __('admin.tax.deleted'));
    }

    private function syncRates(TaxClassRequest $request, TaxClass $taxClass): void
    {
        $kept = [];

        foreach ($request->validated('rates') as $row) {
            $attributes = [
                'rate_bps' => (int) round(((float) $row['percent']) * 100),
                'effective_from' => $row['effective_from'],
                'effective_to' => $row['effective_to'] ?: null,
            ];

            if (! empty($row['id'])) {
                $rate = $taxClass->rates()->whereKey($row['id'])->firstOrFail();
                $rate->update($attributes);
                $kept[] = $rate->id;
            } else {
                $kept[] = $taxClass->rates()->create($attributes)->id;
            }
        }

        $taxClass->rates()->whereNotIn('id', $kept)->delete();
    }
}
