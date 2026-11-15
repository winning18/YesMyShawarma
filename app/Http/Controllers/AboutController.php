<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\StaffMember;
use App\Services\Content\SitePageService;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function index(SitePageService $pages): View
    {
        abort_unless($pages->isPublished('about'), 404);

        $about = $pages->about();

        return view('about', [
            'meta' => $pages->meta('about'),
            'heading' => $pages->heading('about'),
            'mission' => $about['mission'],
            'vision' => $about['vision'],
            'story' => $pages->body('about'),
            'branchCount' => Branch::count(),
            'yearsOfOperation' => $about['founded_year'] ? now()->year - $about['founded_year'] : null,
            'customerCountLabel' => $about['customers_label'],
            'staffMembers' => StaffMember::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
