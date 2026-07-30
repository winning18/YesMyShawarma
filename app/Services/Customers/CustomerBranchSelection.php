<?php

namespace App\Services\Customers;

use App\Models\Branch;
use Illuminate\Http\Request;

/**
 * Which branch a customer is currently browsing/ordering from — session
 * based, deliberately separate from staff's App\Services\Branches\BranchContext
 * (that one drives permission-team scoping; this one is just "which menu
 * and pricing is this shopper looking at").
 */
class CustomerBranchSelection
{
    private const SESSION_KEY = 'customer_branch_id';

    public function __construct(private readonly Request $request) {}

    public function id(): ?int
    {
        return $this->request->session()->get(self::SESSION_KEY);
    }

    public function current(): ?Branch
    {
        $id = $this->id();

        return $id ? Branch::find($id) : null;
    }

    public function set(int $branchId): void
    {
        $this->request->session()->put(self::SESSION_KEY, $branchId);
    }
}
