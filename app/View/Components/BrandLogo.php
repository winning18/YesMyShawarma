<?php

namespace App\View\Components;

use App\Services\Settings\BusinessProfile;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The business's logo, or its name in bold when no logo has been uploaded
 * yet (a fresh Express POS install).
 */
class BrandLogo extends Component
{
    public ?string $url;

    public string $name;

    public function __construct(BusinessProfile $profile)
    {
        $this->url = $profile->logoUrl();
        $this->name = $profile->name();
    }

    public function render(): View
    {
        return view('components.brand-logo');
    }
}
