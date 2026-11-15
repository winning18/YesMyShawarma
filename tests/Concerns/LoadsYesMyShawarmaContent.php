<?php

namespace Tests\Concerns;

use App\Models\FaqItem;
use App\Models\Setting;
use App\Models\SitePage;

/**
 * For tests that check Yes My Shawarma's own wording (titles, policies,
 * FAQ): loads the website content its install holds — the same data the
 * 2026_11_15_120000 migration copied in. A fresh test database is a new
 * install, which has none of it.
 */
trait LoadsYesMyShawarmaContent
{
    protected function loadYesMyShawarmaContent(): void
    {
        $content = require database_path('seeders/data/site_content.php');

        foreach ($content['pages'] as $slug => $page) {
            SitePage::create(['slug' => $slug, ...$page]);
        }

        foreach ($content['faq'] as $i => $item) {
            FaqItem::create([...$item, 'sort_order' => $i + 1]);
        }

        foreach ($content['settings'] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
