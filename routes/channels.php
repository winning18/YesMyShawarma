<?php

use App\Models\User;
use App\Services\Branches\BranchContext;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Must check actual branch membership, not merely that the user is
// authenticated — this is the single most likely place to accidentally let
// staff at one branch watch another branch's order flow. See realtime.md.
Broadcast::channel('branch.{branchId}.orders', function (User $user, int $branchId) {
    $context = app(BranchContext::class);

    return $context->hasRoleAtAnyBranch($user, 'owner')
        || $context->branchIdsFor($user)->contains($branchId);
});
