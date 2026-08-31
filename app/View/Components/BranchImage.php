<?php

namespace App\View\Components;

use App\Models\Branch;
use App\Services\Images\WebpConverter;
use Illuminate\View\Component;
use Illuminate\View\View;

class BranchImage extends Component
{
    public ?string $webpUrl;

    public function __construct(public Branch $branch, WebpConverter $webp)
    {
        $this->webpUrl = $webp->urlFor($branch->image_path);
    }

    public function render(): View
    {
        return view('components.branch-image');
    }
}
