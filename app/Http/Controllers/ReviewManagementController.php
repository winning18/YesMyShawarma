<?php

namespace App\Http\Controllers;

use App\Exceptions\ReviewException;
use App\Models\Review;
use App\Services\Branches\BranchContext;
use App\Services\Orders\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The "Reviews" sidebar page — owner/manager/general_manager only
 * (reviews.moderate, permissions.md). Same weight class as
 * CustomerManagementController, not the heavier multi-state Refunds
 * workflow: a review only ever moves pending -> approved or
 * pending -> rejected, never further.
 */
class ReviewManagementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Review::class);

        $status = $request->string('status')->toString();
        $status = in_array($status, Review::STATUSES, true) ? $status : Review::STATUS_PENDING;

        $reviews = Review::with(['order', 'branch', 'customer'])
            ->where('status', $status)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.reviews.index', [
            'reviews' => $reviews,
            'status' => $status,
        ]);
    }

    public function approve(Request $request, Review $review, ReviewService $reviews, BranchContext $context): RedirectResponse
    {
        Gate::authorize('approve', $review);

        $user = $request->user();
        $actorType = $context->primaryRoleFor($user, $review->branch_id);

        try {
            $reviews->approve($review, $user, $actorType);
        } catch (ReviewException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', __('Review approved — now visible publicly.'));
    }

    public function reject(Request $request, Review $review, ReviewService $reviews, BranchContext $context): RedirectResponse
    {
        Gate::authorize('reject', $review);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $user = $request->user();
        $actorType = $context->primaryRoleFor($user, $review->branch_id);

        try {
            $reviews->reject($review, $user, $actorType, $validated['note'] ?? null);
        } catch (ReviewException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', __('Review rejected.'));
    }
}
