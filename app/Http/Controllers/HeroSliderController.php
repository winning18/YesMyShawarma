<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Every active category is hero-slide-eligible — upload/remove still
 * posts to CategoryManagementController's image routes (that
 * authorization and storage logic is unchanged), this controller only
 * renders the page moved out from under the Categories index and out of
 * the Menu Editor dropdown.
 */
class HeroSliderController extends Controller
{
    public function index(): View
    {
        Gate::authorize('menu.edit_content');

        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return view('dashboard.hero-slider.index', ['categories' => $categories]);
    }
}
