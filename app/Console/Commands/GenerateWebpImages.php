<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\Images\WebpConverter;
use Illuminate\Console\Command;

/**
 * One-off backfill for images uploaded before WebpConverter existed.
 * Every upload going forward gets its .webp sibling generated immediately
 * by ImageUploadService::store() — this command only ever needs running
 * once per environment (production, staging) right after this feature
 * first deploys, and again if new environments are stood up before any
 * uploads happen through the app itself.
 */
class GenerateWebpImages extends Command
{
    protected $signature = 'images:generate-webp';

    protected $description = 'Generate .webp siblings for every already-uploaded menu item, branch, and category hero image';

    public function handle(WebpConverter $webp): int
    {
        $paths = MenuItem::whereNotNull('image_path')->pluck('image_path')
            ->merge(Branch::whereNotNull('image_path')->pluck('image_path'))
            ->merge(Category::whereNotNull('hero_image_path')->pluck('hero_image_path'));

        $this->withProgressBar($paths, fn (string $path) => $webp->generate($path));
        $this->newLine(2);
        $this->info("Done — checked {$paths->count()} image(s).");

        return self::SUCCESS;
    }
}
