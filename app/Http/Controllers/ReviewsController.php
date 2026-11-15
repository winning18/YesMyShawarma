<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\Content\SitePageService;
use Illuminate\View\View;

class ReviewsController extends Controller
{
    public function index(SitePageService $pages): View
    {
        $reviews = Review::with('branch')
            ->where('status', Review::STATUS_APPROVED)
            ->latest('moderated_at')
            ->paginate(12);

        return view('reviews.index', [
            'meta' => $pages->meta('reviews'),
            'reviews' => $reviews,
        ]);
    }
}
