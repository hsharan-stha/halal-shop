<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Services\Media\ImageStorage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['parent_id', 'name', 'japanese_name', 'slug', 'description', 'icon', 'sort_order', 'is_active', 'meta_title', 'meta_description'])]
class Category extends Model
{
    use Auditable, HasFactory, HasTranslations;

    public const ICONS = ['squares', 'box', 'cube', 'snowflake', 'fire', 'sparkles', 'tag', 'star', 'heart', 'thermometer', 'store', 'layers', 'sun'];

    protected function casts(): array
    {
        return [
            'description' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function imageUrl(): ?string
    {
        return app(ImageStorage::class)->url($this->image_path);
    }

    /**
     * IDs of this category and every category below it.
     *
     * @return list<int>
     */
    public function descendantAndSelfIds(): array
    {
        $byParent = self::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [];
        $queue = [$this->id];

        while ($queue !== []) {
            $id = array_shift($queue);

            if (in_array($id, $ids, true)) {
                continue;
            }

            $ids[] = $id;
            array_push($queue, ...($byParent->get($id)?->pluck('id')->all() ?? []));
        }

        return $ids;
    }

    /**
     * Flattened tree for select inputs: [id => "— Child name"].
     *
     * @param  list<int>  $exclude
     * @return array<int, string>
     */
    public static function treeOptions(array $exclude = []): array
    {
        $all = self::query()->ordered()->get(['id', 'parent_id', 'name', 'japanese_name']);
        $byParent = $all->groupBy(fn (self $category) => $category->parent_id ?? 0);
        $options = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$options, $byParent, $exclude): void {
            /** @var Collection<int, self> $children */
            $children = $byParent->get($parentId, collect());

            foreach ($children as $child) {
                if (in_array($child->id, $exclude, true)) {
                    continue;
                }

                $options[$child->id] = str_repeat('— ', $depth).$child->localizedName();
                $walk($child->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $options;
    }
}
