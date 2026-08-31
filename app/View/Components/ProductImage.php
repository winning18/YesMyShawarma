<?php

namespace App\View\Components;

use App\Models\MenuItem;
use App\Services\Images\WebpConverter;
use Illuminate\View\Component;
use Illuminate\View\View;

class ProductImage extends Component
{
    public ?string $webpUrl;

    public function __construct(public MenuItem $item, WebpConverter $webp)
    {
        $this->webpUrl = $webp->urlFor($item->image_path);
    }

    public function render(): View
    {
        return view('components.product-image');
    }
}
