<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Services\Branches\BranchContext;

/**
 * Turns the report controllers' branch arguments (WeeklySalesReportService's
 * $ignoreBranchScope/$branchId/$branchIds convention) into an explicit
 * branch-id list, and picks the branch whose address heads a report:
 * the one branch being reported on, or Ga Odumase (the main branch) for
 * a general all-branches report. Shared by every downloadable report so
 * they scope and head their documents identically.
 */
class ReportBranchResolver
{
    public const MAIN_BRANCH_SLUG = 'ga-odumase';

    public function __construct(private readonly BranchContext $context) {}

    /**
     * Null means every branch. The ambient case (no explicit filter and
     * not ignoring scope) pins to BranchContext's current branch — the
     * same branch BranchScope would have applied — resolved explicitly
     * because tables like shift_expenses have no BranchScope of their own.
     *
     * @param  ?list<int>  $branchIds
     * @return ?list<int>
     */
    public function branchIds(bool $ignoreBranchScope, ?int $branchId, ?array $branchIds): ?array
    {
        if ($branchId !== null) {
            return [$branchId];
        }

        if ($branchIds !== null) {
            return array_values($branchIds);
        }

        if ($ignoreBranchScope) {
            return null;
        }

        $current = $this->context->id();

        return $current !== null ? [$current] : null;
    }

    /**
     * The single branch a report is about, or null for a general report.
     * A general_manager's "all my branches" stays general even when they
     * oversee just one.
     *
     * @param  ?list<int>  $branchIds
     */
    public function singleBranchId(bool $ignoreBranchScope, ?int $branchId, ?array $branchIds): ?int
    {
        if ($branchId !== null) {
            return $branchId;
        }

        if ($ignoreBranchScope || $branchIds !== null) {
            return null;
        }

        return $this->context->id();
    }

    public function headerBranch(?int $singleBranchId): ?Branch
    {
        if ($singleBranchId !== null) {
            return Branch::find($singleBranchId);
        }

        return Branch::where('slug', self::MAIN_BRANCH_SLUG)->first() ?? Branch::orderBy('id')->first();
    }
}
