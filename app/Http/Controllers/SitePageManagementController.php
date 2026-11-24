<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSitePageRequest;
use App\Services\Content\SitePageService;
use App\Services\Content\WebsiteThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Settings → Website content: each customer page's heading, search title,
 * search description and text (SitePageService). Owner and general
 * manager (content.manage).
 */
class SitePageManagementController extends Controller
{
    public function index(SitePageService $pages, WebsiteThemeService $themes): View
    {
        Gate::authorize('content.manage');

        return view('dashboard.site-pages.index', [
            'theme' => $themes->chosen(),
            'pages' => collect(SitePageService::PAGES)->map(fn (array $config, string $slug) => [
                'slug' => $slug,
                'label' => $config['label'],
                'written' => $pages->page($slug)->exists,
                'optional' => in_array($slug, SitePageService::OPTIONAL_PAGES, true),
                'published' => ! in_array($slug, SitePageService::OPTIONAL_PAGES, true) || $pages->isPublished($slug),
            ]),
        ]);
    }

    public function edit(string $page, SitePageService $pages): View
    {
        Gate::authorize('content.manage');
        abort_unless(isset(SitePageService::PAGES[$page]), 404);

        return view('dashboard.site-pages.edit', [
            'slug' => $page,
            'config' => SitePageService::PAGES[$page],
            'page' => $pages->page($page),
            'about' => $page === 'about' ? $pages->about() : null,
            'publicUrl' => $this->publicUrl($page),
        ]);
    }

    public function update(UpdateSitePageRequest $request, string $page, SitePageService $pages): RedirectResponse
    {
        Gate::authorize('content.manage');
        abort_unless(isset(SitePageService::PAGES[$page]), 404);

        $pages->update($page, $request->validated());

        if ($page === 'about') {
            $pages->updateAbout($request->validated());
        }

        return back()->with('status', __(':page saved.', ['page' => SitePageService::PAGES[$page]['label']]));
    }

    private function publicUrl(string $page): ?string
    {
        return match ($page) {
            'home' => route('home'),
            'menu' => route('menu.index'),
            'branches' => route('branches.index'),
            'contact' => route('contact'),
            'reviews' => route('reviews.index'),
            'faq' => route('faq'),
            'about' => route('about'),
            'terms' => route('policy.terms'),
            'refunds' => route('policy.refunds'),
            'privacy' => route('policy.privacy'),
            'cookies' => route('policy.cookies'),
            'llms' => route('llms'),
            default => null,
        };
    }
}
