<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageUploadService
{
    /**
     * Stores a new image on the public disk under $directory, replacing
     * whatever was previously there, and returns the path to save on the
     * owning model. Shared by every entity with a photo (branches, menu
     * items) so the disk and naming convention can change in one place —
     * this is the seam a future Cloudinary/S3 swap hangs off of.
     */
    public function store(string $directory, int $id, UploadedFile $file, ?string $previousPath): string
    {
        $this->delete($previousPath);

        return $file->storeAs($directory, $id.'.'.$file->extension(), 'public');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
