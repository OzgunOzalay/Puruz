<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Responsive WebP copies of uploaded images, stored next to the originals under variants/{width}/.
 * Instagram traffic is almost entirely mobile, so the 400/800 sizes do most of the work.
 */
class ProductImages
{
    public const WIDTHS = [400, 800, 1600];

    public static function variantPath(string $path, int $width): string
    {
        return 'variants/'.$width.'/'.preg_replace('/\.[^.\/]+$/', '', $path).'.webp';
    }

    public static function url(?string $path, ?int $width = null): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if ($width && $disk->exists($variant = self::variantPath($path, $width))) {
            return $disk->url($variant);
        }

        return $disk->url($path);
    }

    public static function srcset(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');
        $sources = [];

        foreach (self::WIDTHS as $width) {
            if ($disk->exists($variant = self::variantPath($path, $width))) {
                $sources[] = $disk->url($variant).' '.$width.'w';
            }
        }

        return $sources ? implode(', ', $sources) : null;
    }

    public static function generate(string $path): void
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return;
        }

        try {
            $manager = new ImageManager(Driver::class);
            $binary = $disk->get($path);

            foreach (self::WIDTHS as $width) {
                $target = self::variantPath($path, $width);
                if ($disk->exists($target)) {
                    continue;
                }

                $encoded = $manager->decodeBinary($binary)
                    ->orient()
                    ->scaleDown(width: $width)
                    ->encode(new WebpEncoder(quality: 82));

                $disk->put($target, (string) $encoded);
            }
        } catch (Throwable $e) {
            Log::warning('Görsel varyantı oluşturulamadı', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }

    public static function delete(string $path): void
    {
        $disk = Storage::disk('public');

        foreach (self::WIDTHS as $width) {
            $disk->delete(self::variantPath($path, $width));
        }
    }
}
