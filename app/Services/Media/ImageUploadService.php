<?php

namespace App\Services\Media;

use App\Services\Images\WebpConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageUploadService
{
    public function __construct(private readonly WebpConverter $webp) {}

    /**
     * Stores a new image on the public disk under $directory, replacing
     * whatever was previously there, and returns the path to save on the
     * owning model. Shared by every entity with a photo (branches, menu
     * items) so the disk and naming convention can change in one place —
     * this is the seam a future Cloudinary/S3 swap hangs off of.
     *
     * Generates the .webp sibling right here, once, at upload time —
     * never lazily on a customer-facing page render (see WebpConverter's
     * own docblock for why that would be a real problem).
     */
    public function store(string $directory, int $id, UploadedFile $file, ?string $previousPath): string
    {
        $this->delete($previousPath);

        $path = $file->storeAs($directory, $id.'.'.$file->extension(), 'public');

        $this->webp->generate($path);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
            $this->webp->delete($path);
        }
    }
}
