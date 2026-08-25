<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class CustomerLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public string $bodyClass = 'bg-brand-white',
        public string $mainClass = 'max-w-5xl',
        // Link-preview thumbnail (WhatsApp/Facebook/iMessage/etc) and
        // description — per-page, so a shared product link shows that
        // product's own photo rather than the site logo every crawler
        // fell back to finding as the first <img> on the page. Null
        // falls back to a sensible site-wide default in the layout
        // itself (see layouts/customer.blade.php).
        public ?string $ogImage = null,
        public ?string $ogDescription = null,
    ) {}

    public function render(): View
    {
        return view('layouts.customer');
    }
}
