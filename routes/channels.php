<?php

use App\Models\User;
use App\Services\Branches\BranchContext;
use Illuminate\Support\Facades\Broadcast;

// ['guards' => ['web', 'rider']] on both — /broadcasting/auth is its own,
// separate request (Echo's background call), not covered by whatever
// 'auth:rider' middleware ran on the page that opened the connection, so
// it has to be told explicitly which guards a legitimate subscriber might
// be authenticated under. Without this, a rider (config/auth.php's own
// guard) is invisible here and every channel subscription silently fails
// auth. Broadcaster::retrieveUser() tries each guard in order and uses
// whichever one actually has someone logged in.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['web', 'rider']]);

// Must check actual branch membership, not merely that the user is
// authenticated — this is the single most likely place to accidentally let
// staff at one branch watch another branch's order flow. See realtime.md.
Broadcast::channel('branch.{branchId}.orders', function (User $user, int $branchId) {
    $context = app(BranchContext::class);

    return $context->hasRoleAtAnyBranch($user, 'owner')
        || $context->branchIdsFor($user)->contains($branchId);
}, ['guards' => ['web', 'rider']]);
