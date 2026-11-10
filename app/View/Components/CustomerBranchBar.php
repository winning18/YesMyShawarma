<?php

namespace App\View\Components;

use App\Models\Branch;
use App\Services\Customers\CustomerBranchSelection;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * "Ordering from X · Change · Find nearest" on the menu, item and cart
 * pages. Highlighted when the branch was picked for the customer rather
 * than by them (CustomerBranchSelection::isAutomatic), so nobody orders
 * from Ga Odumase without noticing.
 */
class CustomerBranchBar extends Component
{
    public bool $automatic;

    public function __construct(public Branch $branch, CustomerBranchSelection $selection)
    {
        $this->automatic = $selection->isAutomatic();
    }

    public function render(): View
    {
        return view('components.customer-branch-bar');
    }
}
