<?php

namespace App\View\Components;

use App\Services\Branches\BranchContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/**
 * An inline "which branch am I working on" dropdown for branch-scoped admin
 * pages (Menu, Item availability, Time table) — switches the session branch
 * and lands back on the same page. Only for owner/GM/manager accounts with
 * more than one branch to choose from; staff pick their branch when they
 * start a shift instead (see navigation-links.blade.php), so they never
 * see it.
 */
class BranchSwitcher extends Component
{
    /** @var Collection<int, \App\Models\Branch> */
    public Collection $branches;

    public ?int $currentBranchId;

    public function __construct(BranchContext $context)
    {
        $user = Auth::guard('web')->user();

        $this->branches = $user && ! $context->runsATill($user)
            ? $context->selectableBranchesFor($user)->sortBy('name')->values()
            : collect();
        $this->currentBranchId = $context->id();
    }

    public function shouldRender(): bool
    {
        return $this->branches->count() > 1;
    }

    public function render(): View
    {
        return view('components.branch-switcher');
    }
}
