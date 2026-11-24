<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWebsiteThemeRequest;
use App\Services\Content\WebsiteThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Settings → Website content → Website look (WebsiteThemeService): choose
 * and preview the customer website's theme. Owner and general manager
 * (content.manage).
 */
class WebsiteThemeController extends Controller
{
    public function edit(WebsiteThemeService $themes): View
    {
        Gate::authorize('content.manage');

        return view('dashboard.website-look.edit', [
            'themes' => collect(WebsiteThemeService::THEMES)->map(fn (array $theme, string $key) => $theme + [
                'key' => $key,
                'previewUrl' => $themes->previewUrl($key),
            ])->values(),
            'chosen' => $themes->chosen(),
            'photos' => $themes->photoCoverage(),
        ]);
    }

    public function update(UpdateWebsiteThemeRequest $request, WebsiteThemeService $themes): RedirectResponse
    {
        Gate::authorize('content.manage');

        $themes->set($request->validated('theme'));

        return back()->with('status', __('Website look saved: :theme.', ['theme' => __(WebsiteThemeService::THEMES[$request->validated('theme')]['label'])]));
    }
}
