<?php

namespace App\Services\Customers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Which branch a customer is currently browsing/ordering from — session
 * based, deliberately separate from staff's App\Services\Branches\BranchContext
 * (that one drives permission-team scoping; this one is just "which menu
 * and pricing is this shopper looking at").
 *
 * With nothing in the session yet, current() picks up where the customer
 * left off (owner decision 2026-11-10): the branch this device last chose
 * (a year-long cookie), else a logged-in customer's last order's branch.
 * "Find my nearest branch" (NearestBranchFinder) overrides both whenever
 * the customer taps it. Anything else falls to MenuController's default
 * branch, recorded as SOURCE_DEFAULT so the page can say it was picked for
 * them.
 */
class CustomerBranchSelection
{
    public const SOURCE_CHOSEN = 'chosen';

    public const SOURCE_NEAREST = 'nearest';

    public const SOURCE_REMEMBERED = 'remembered';

    public const SOURCE_LAST_ORDER = 'last_order';

    public const SOURCE_DEFAULT = 'default';

    public const COOKIE = 'customer_branch';

    private const SESSION_KEY = 'customer_branch_id';

    private const SOURCE_KEY = 'customer_branch_source';

    public function __construct(private readonly Request $request) {}

    public function id(): ?int
    {
        return $this->current()?->id;
    }

    public function current(): ?Branch
    {
        $id = $this->request->session()->get(self::SESSION_KEY);

        if ($id) {
            return Branch::find($id);
        }

        $restored = $this->restore();

        if ($restored) {
            [$branch, $source] = $restored;
            $this->request->session()->put([self::SESSION_KEY => $branch->id, self::SOURCE_KEY => $source]);

            return $branch;
        }

        return null;
    }

    /**
     * Only a choice the customer actually made (picked a branch, tapped
     * "nearest", placed an order) is remembered on the device — never the
     * default fallback, which would otherwise stick for a year without
     * anyone choosing it. Not remembered for a visitor who declined
     * non-essential cookies (the Cookie Policy's "functional" cookies).
     */
    public function set(int $branchId, string $source = self::SOURCE_CHOSEN): void
    {
        $this->request->session()->put([self::SESSION_KEY => $branchId, self::SOURCE_KEY => $source]);

        if ($source !== self::SOURCE_DEFAULT && $this->request->cookie('cookie_consent') !== 'decline') {
            Cookie::queue(Cookie::make(self::COOKIE, (string) $branchId, 60 * 24 * 365));
        }
    }

    /**
     * True when nobody chose this branch — the page should say so and
     * offer to find the nearest one.
     */
    public function isAutomatic(): bool
    {
        return $this->request->session()->get(self::SOURCE_KEY) === self::SOURCE_DEFAULT;
    }

    /**
     * @return array{0: Branch, 1: string}|null
     */
    private function restore(): ?array
    {
        $remembered = (int) $this->request->cookie(self::COOKIE);
        $branch = $remembered ? Branch::where('is_active', true)->find($remembered) : null;

        if ($branch) {
            return [$branch, self::SOURCE_REMEMBERED];
        }

        $lastBranchId = Auth::guard('customer')->user()?->orders()->latest('placed_at')->value('branch_id');
        $branch = $lastBranchId ? Branch::where('is_active', true)->find($lastBranchId) : null;

        return $branch ? [$branch, self::SOURCE_LAST_ORDER] : null;
    }
}
