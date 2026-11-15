<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveHomeStripsRequest;
use App\Models\Category;
use App\Services\Content\HomeStripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Settings → Website content → Home page strips (HomeStripService). Owner
 * and general manager (content.manage).
 */
class HomeStripController extends Controller
{
    public function edit(HomeStripService $strips): View
    {
        Gate::authorize('content.manage');

        return view('dashboard.home-strips.edit', [
            'strips' => $strips->strips(),
            'isCustom' => $strips->configured() !== null,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']),
        ]);
    }

    public function update(SaveHomeStripsRequest $request, HomeStripService $strips): RedirectResponse
    {
        Gate::authorize('content.manage');

        if ($request->boolean('reset')) {
            $strips->reset();

            return back()->with('status', __('Home page strips set back to one per category.'));
        }

        $strips->save($request->validated('strips'));

        return back()->with('status', __('Home page strips saved.'));
    }
}
