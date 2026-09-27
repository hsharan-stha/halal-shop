<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesTranslations;
use App\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TaxClassRequest extends FormRequest
{
    use HandlesTranslations;

    public function authorize(): bool
    {
        return $this->user()->can('tax.manage');
    }

    protected function prepareForValidation(): void
    {
        $rates = collect($this->input('rates', []))
            ->filter(function (mixed $rate): bool {
                if (! is_array($rate)) {
                    return false;
                }

                if (filter_var($rate['remove'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    return false;
                }

                return ! (blank($rate['id'] ?? null)
                    && blank($rate['percent'] ?? null)
                    && blank($rate['effective_from'] ?? null)
                    && blank($rate['effective_to'] ?? null));
            })
            ->map(function (array $rate): array {
                if (blank($rate['id'] ?? null)) {
                    unset($rate['id']);
                }

                $rate['effective_to'] = blank($rate['effective_to'] ?? null) ? null : $rate['effective_to'];

                return $rate;
            })
            ->values()
            ->all();

        $this->merge([
            'code' => strtolower(trim((string) $this->input('code'))),
            'rates' => $rates,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TaxClass|null $taxClass */
        $taxClass = $this->route('taxClass');

        $rules = [
            'is_default' => ['boolean'],
            'rates' => ['required', 'array', 'min:1'],
            ...$this->translationRules('name', 80, true),
        ];

        if ($taxClass === null) {
            $rules['code'] = ['required', 'string', 'max:40', 'alpha_dash:ascii', Rule::unique('tax_classes', 'code')];
        }

        foreach ($this->input('rates', []) as $index => $rate) {
            $rules['rates.'.$index.'.id'] = ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where('tax_class_id', $taxClass?->id ?? 0)];
            $rules['rates.'.$index.'.percent'] = ['required', 'numeric', 'min:0', 'max:100'];
            $rules['rates.'.$index.'.effective_from'] = ['required', 'date'];
            $rules['rates.'.$index.'.effective_to'] = ['nullable', 'date', 'after_or_equal:rates.'.$index.'.effective_from'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->rangesOverlap($this->input('rates', []))) {
                return;
            }

            $validator->errors()->add('rates', __('admin.tax.overlap'));
        });
    }

    /**
     * @return array{code?: string, name: array<string, string>, is_default: bool}
     */
    public function classAttributes(): array
    {
        $attributes = [
            'name' => $this->translation('name') ?? [],
            'is_default' => $this->boolean('is_default'),
        ];

        if ($this->route('taxClass') === null) {
            $attributes['code'] = $this->validated('code');
        }

        return $attributes;
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     */
    private function rangesOverlap(array $rates): bool
    {
        $ranges = collect($rates)
            ->map(fn (array $rate): array => [
                'from' => (string) $rate['effective_from'],
                'to' => $rate['effective_to'] ?: '9999-12-31',
            ])
            ->sortBy('from')
            ->values();

        for ($index = 1; $index < $ranges->count(); $index++) {
            if ($ranges[$index]['from'] <= $ranges[$index - 1]['to']) {
                return true;
            }
        }

        return false;
    }
}
