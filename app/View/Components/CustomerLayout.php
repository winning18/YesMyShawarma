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
    ) {}

    public function render(): View
    {
        return view('layouts.customer');
    }
}
