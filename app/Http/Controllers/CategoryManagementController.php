<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Category info and the home-page hero image are one feature — every
 * category is hero-slide-eligible (image + tagline), not just a fixed
 * whitelist, so a newly created category never needs a code change
 * before staff can give it a hero photo.
 */
class CategoryManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('menu.edit_content');

        return view('dashboard.categories.index', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('menu.edit_content');

        return view('dashboard.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        $category = Category::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('dashboard.categories.edit', $category)
            ->with('status', __(':name has been created.', ['name' => $category->name]));
    }

    public function edit(Category $category): View
    {
        Gate::authorize('menu.edit_content');

        return view('dashboard.categories.edit', ['category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        $category->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('dashboard.categories.edit', $category)
            ->with('status', __('Category updated.'));
    }

    public function updateImage(Request $request, Category $category, ImageUploadService $images): RedirectResponse
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

    public function destroyImage(Category $category, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $images->delete($category->hero_image_path);
        $category->update(['hero_image_path' => null]);

        return back()->with('status', __(':category hero image removed.', ['category' => $category->name]));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
