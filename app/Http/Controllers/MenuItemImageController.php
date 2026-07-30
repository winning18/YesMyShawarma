<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MenuItemImageController extends Controller
{
    public function index(): View
    {
        Gate::authorize('menu.edit_content');

        return view('dashboard.menu-items.index', [
            'categories' => Category::with(['menuItems' => fn ($query) => $query->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function update(Request $request, MenuItem $menuItem, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $menuItem->update([
            'image_path' => $images->store('menu-items', $menuItem->id, $request->file('image'), $menuItem->image_path),
        ]);

        return back()->with('status', __(':item image updated.', ['item' => $menuItem->name]));
    }

    public function destroy(MenuItem $menuItem, ImageUploadService $images): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $images->delete($menuItem->image_path);
        $menuItem->update(['image_path' => null]);

        return back()->with('status', __(':item image removed.', ['item' => $menuItem->name]));
    }
}
