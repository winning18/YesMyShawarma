<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MenuItem;
use Illuminate\Http\Response;

/**
 * Not under the track.visit route group (routes/web.php) — a crawler
 * fetching this isn't a customer "visit" the Performance page's
 * conversion rate should ever count.
 */
class SitemapController extends Controller
{
    /**
     * Static pages, each active branch's own page, and every product page
     * a crawler can actually load — these are exactly the pages
     * MenuController's crawlability fix (resolveBranch defaulting instead
     * of redirecting) makes indexable at all, so a crawler has no way to
     * discover them without this.
     */
    public function index(): Response
    {
        $urls = collect([
            ['url' => route('home'), 'priority' => '1.0'],
            ['url' => route('menu.index'), 'priority' => '0.9'],
            ['url' => route('branches.index'), 'priority' => '0.8'],
            ['url' => route('about'), 'priority' => '0.5'],
            ['url' => route('contact'), 'priority' => '0.5'],
            ['url' => route('faq'), 'priority' => '0.4'],
            ['url' => route('reviews.index'), 'priority' => '0.4'],
            ['url' => route('policy.terms'), 'priority' => '0.2'],
            ['url' => route('policy.refunds'), 'priority' => '0.2'],
            ['url' => route('policy.privacy'), 'priority' => '0.2'],
            ['url' => route('policy.cookies'), 'priority' => '0.2'],
        ]);

        Branch::where('is_active', true)->orderBy('id')->get()->each(
            fn (Branch $branch) => $urls->push([
                'url' => route('branches.show', $branch),
                'priority' => '0.8',
                'lastmod' => $branch->updated_at?->toAtomString(),
            ])
        );

        // A crawler has no branch selected, so MenuController shows it the
        // default branch (the first active one) — only items on that
        // branch's menu have a page it can load. Sold-out items still do
        // (shown as sold out), so availability doesn't matter here.
        $defaultBranch = Branch::where('is_active', true)->orderBy('id')->first();

        if ($defaultBranch) {
            $defaultBranch->menuItems()->where('menu_items.is_active', true)->get()->each(
                fn (MenuItem $item) => $urls->push([
                    'url' => route('menu.show', $item),
                    'priority' => '0.7',
                    'lastmod' => $item->updated_at?->toAtomString(),
                ])
            );
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
