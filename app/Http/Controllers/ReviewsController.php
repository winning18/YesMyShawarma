<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\View\View;

class ReviewsController extends Controller
{
    public function index(): View
    {
        $reviews = Review::with('branch')
            ->where('status', Review::STATUS_APPROVED)
            ->latest('moderated_at')
            ->paginate(12);

        return view('reviews.index', [
            'reviews' => $reviews,
        ]);
    }
}
