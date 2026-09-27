<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_default'])]
class TaxClass extends Model
{
    use Auditable, HasFactory, HasTranslations;

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class)->orderByDesc('effective_from');
    }

    /**
     * The rate in effect on the given date (Japan time), or null if none.
     */
    public function rateOn(?CarbonInterface $date = null): ?TaxRate
    {
        $day = ($date ?? now())->copy()->setTimezone(config('app.display_timezone'))->toDateString();

        $rates = $this->relationLoaded('rates') ? $this->rates : $this->rates()->get();

        return $rates->first(fn (TaxRate $rate) => $rate->effective_from->toDateString() <= $day
            && ($rate->effective_to === null || $rate->effective_to->toDateString() >= $day));
    }

    public function label(): string
    {
        $rate = $this->rateOn();

        return $this->translate('name').($rate ? ' ('.$rate->percentage().')' : '');
    }

    public static function default(): ?self
    {
        return self::query()->where('is_default', true)->first() ?? self::query()->orderBy('id')->first();
    }
}
