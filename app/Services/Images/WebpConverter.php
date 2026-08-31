<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * WebP generation for uploaded JPG/PNG images, using GD (a PHP extension
 * already present everywhere this app runs — no composer package
 * needed). AVIF is deliberately not attempted: GD on production lacks
 * imageavif() (needs libavif at compile time, a system-level install
 * this service has no business making silently).
 *
 * generate() is called at the moment an image is uploaded
 * (ImageUploadService::store()) and by the one-off
 * images:generate-webp backfill command — never lazily from a
 * customer-facing page render, which would mean the first visitor after
 * a deploy pays the CPU cost of converting every uncached image in one
 * request. urlFor() is a cheap existence check only; ProductImage/
 * BranchImage fall back to the original file whenever no .webp sibling
 * exists yet, so a slow/failed conversion never breaks an image.
 */
class WebpConverter
{
    /**
     * $storagePath is relative to the 'public' disk, e.g. "menu-items/1.jpg"
     * — the same value MenuItem::image_path / Branch::image_path store.
     * Returns the public URL of its .webp sibling if one already exists,
     * or null otherwise (including for a non-convertible format).
     */
    public function urlFor(?string $storagePath): ?string
    {
        $webpPath = $this->webpPath($storagePath);

        if (! $webpPath) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($webpPath) ? $disk->url($webpPath) : null;
    }

    /**
     * Generates the .webp sibling for $storagePath if it doesn't already
     * exist. Safe to call unconditionally after every upload and from the
     * backfill command — a no-op if the file's already there.
     */
    public function generate(?string $storagePath): void
    {
        $webpPath = $this->webpPath($storagePath);

        if (! $webpPath || ! function_exists('imagewebp')) {
            return;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($webpPath) || ! $disk->exists($storagePath)) {
            return;
        }

        $this->convert($disk, $storagePath, $webpPath);
    }

    /**
     * Removes the .webp sibling alongside its original — called from
     * ImageUploadService::delete() so a removed/replaced photo never
     * leaves an orphaned .webp file behind on disk.
     */
    public function delete(?string $storagePath): void
    {
        $webpPath = $this->webpPath($storagePath);

        if ($webpPath) {
            Storage::disk('public')->delete($webpPath);
        }
    }

    private function webpPath(?string $storagePath): ?string
    {
        if (! $storagePath) {
            return null;
        }

        $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $storagePath);

        // Not a .jpg/.jpeg/.png — nothing this service knows how to
        // re-encode (e.g. already a .gif or an unexpected format).
        return ($webpPath === null || $webpPath === $storagePath) ? null : $webpPath;
    }

    private function convert($disk, string $storagePath, string $webpPath): bool
    {
        try {
            $source = $this->load($disk->path($storagePath));

            if (! $source) {
                return false;
            }

            ob_start();
            imagewebp($source, null, 82);
            $data = ob_get_clean();
            imagedestroy($source);

            if ($data === false || $data === '') {
                return false;
            }

            $disk->put($webpPath, $data);

            return true;
        } catch (\Throwable $e) {
            Log::warning('WebP conversion failed', ['path' => $storagePath, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return \GdImage|false
     */
    private function load(string $absolutePath)
    {
        $info = @getimagesize($absolutePath);

        return match ($info[2] ?? null) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            default => false,
        };
    }
}
