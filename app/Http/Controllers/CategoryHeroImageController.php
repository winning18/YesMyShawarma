<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryHeroImageController extends Controller
{
    public function index(): View
    {
        Gate::authorize('menu.edit_content');

        $slugs = array_keys(Category::HERO_SLIDE_TAGLINES);

        $categories = Category::whereIn('slug', $slugs)->get()
            ->sortBy(fn (Category $category) => array_search($category->slug, $slugs, true))
            ->values();

        return view('dashboard.hero-images.index', ['categories' => $categories]);
    }

    public function update(Request $request, Category $category, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $category->update([
            'hero_image_path' => $images->store('category-hero', $category->id, $request->file('image'), $category->hero_image_path),
        ]);

        return back()->with('status', __(':category hero image updated.', ['category' => $category->name]));
    }

    public function destroy(Category $category, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $images->delete($category->hero_image_path);
        $category->update(['hero_image_path' => null]);

        return back()->with('status', __(':category hero image removed.', ['category' => $category->name]));
    }
}
