<?php

namespace App\Models\Scopes;

use App\Services\Branches\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters to the currently resolved branch (see App\Services\Branches\BranchContext).
 * A no-op when no branch is resolved — true for console commands, queued jobs, the
 * owner's cross-branch view, and any customer-guard request. Those contexts must not
 * be silently scoped to whatever branch happens to be left in a stale session.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = app(BranchContext::class)->id();

        if ($branchId !== null) {
            $builder->where($model->getTable().'.branch_id', $branchId);
        }
    }
}
