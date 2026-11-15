<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveFaqItemRequest;
use App\Models\FaqItem;
use App\Services\Content\FaqService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Settings → Website content → FAQ questions (FaqService). Owner and
 * general manager (content.manage).
 */
class FaqItemManagementController extends Controller
{
    public function index(FaqService $faq): View
    {
        Gate::authorize('content.manage');

        return view('dashboard.faq-items.index', ['items' => $faq->items()]);
    }

    public function store(SaveFaqItemRequest $request, FaqService $faq): RedirectResponse
    {
        Gate::authorize('content.manage');

        $faq->create($request->validated());

        return back()->with('status', __('Question added.'));
    }

    public function update(SaveFaqItemRequest $request, FaqItem $faqItem, FaqService $faq): RedirectResponse
    {
        Gate::authorize('content.manage');

        $faq->update($faqItem, $request->validated());

        return back()->with('status', __('Question saved.'));
    }

    public function destroy(FaqItem $faqItem, FaqService $faq): RedirectResponse
    {
        Gate::authorize('content.manage');

        $faq->delete($faqItem);

        return back()->with('status', __('Question removed.'));
    }
}
