<?php

namespace App\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

/**
 * Stores uploaded images on the public media disk. Every upload is decoded and
 * re-encoded as WebP under a random name, which strips metadata and anything
 * that is not image data; the client's filename and extension are never used.
 */
class ImageStorage
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = ImageManager::gd();
    }

    /**
     * @return array{path: string, width: int, height: int}
     */
    public function store(UploadedFile $file, string $directory, int $maxDimension = 2400): array
    {
        $this->guardDimensions((string) $file->getRealPath());

        $image = $this->manager->read($file->getRealPath());
        $image->scaleDown(width: $maxDimension, height: $maxDimension);

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        $this->disk()->put($path, (string) $image->toWebp(quality: 85));

        return ['path' => $path, 'width' => $image->width(), 'height' => $image->height()];
    }

    /**
     * Generate width-bounded renditions next to an already stored image.
     *
     * @param  array<string, int>  $sizes
     * @return array<string, string>
     */
    public function renditions(string $path, array $sizes): array
    {
        $source = $this->manager->read($this->disk()->get($path));
        $base = Str::beforeLast($path, '.');
        $result = [];

        foreach ($sizes as $name => $width) {
            $rendition = clone $source;
            $rendition->scaleDown(width: $width, height: $width);
            $target = $base.'-'.$name.'.webp';
            $this->disk()->put($target, (string) $rendition->toWebp(quality: 80));
            $result[$name] = $target;
        }

        return $result;
    }

    public function copy(string $path, string $directory): string
    {
        $target = trim($directory, '/').'/'.Str::random(40).'.webp';
        $this->disk()->copy($path, $target);

        return $target;
    }

    /**
     * @param  iterable<string|null>|string|null  $paths
     */
    public function delete(iterable|string|null $paths): void
    {
        $paths = array_values(array_filter(is_iterable($paths) ? [...$paths] : [$paths]));

        if ($paths !== []) {
            $this->disk()->delete($paths);
        }
    }

    public function url(?string $path): ?string
    {
        return $path ? $this->disk()->url($path) : null;
    }

    private function guardDimensions(string $realPath): void
    {
        $size = @getimagesize($realPath);
        $max = (int) config('shop.uploads.image_max_dimension');

        if ($size === false || $size[0] > $max || $size[1] > $max) {
            throw new InvalidArgumentException('Unsupported image.');
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('shop.media_disk'));
    }
}
