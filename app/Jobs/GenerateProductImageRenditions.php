<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\Media\ImageStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class GenerateProductImageRenditions implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public ProductImage $image) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->image->id))->dontRelease()];
    }

    public function handle(ImageStorage $storage): void
    {
        $image = $this->image->fresh();

        if (! $image) {
            return;
        }

        $previous = array_values($image->renditions ?? []);
        $renditions = $storage->renditions($image->path, config('shop.image_sizes'));

        $image->forceFill(['renditions' => $renditions])->save();
        $storage->delete(array_diff($previous, array_values($renditions)));
    }
}
