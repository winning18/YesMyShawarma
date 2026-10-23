<?php

namespace App\Services\Notifications;

use App\Jobs\SendSms;
use App\Models\StockItem;
use App\Services\Branches\BranchContext;

/**
 * The owner-facing low-stock SMS — one place for the message copy, same
 * reasoning as CustomerOrderNotifier. Owner is looked up globally
 * (BranchContext::usersWithRole('owner', null)), same lookup
 * EscalateUnacknowledgedOrders uses, since owner's role assignment is
 * anchored at one branch but they should be notified regardless of which
 * branch the low-stock item belongs to.
 */
class StockAlertNotifier
{
    public function __construct(
        private readonly BranchContext $branches,
    ) {}

    public function lowStock(StockItem $item): void
    {
        $message = "Low stock at {$item->branch->name}: {$item->name} is down to {$item->quantity} {$item->unit}.";

        // A missing phone number must never take the request down with it —
        // same reasoning as ArkeselNotifier swallowing delivery failures.
        foreach ($this->branches->usersWithRole('owner', null) as $owner) {
            if ($owner->phone) {
                // Queued: this runs inside the staff member's "accept order" request.
                SendSms::dispatch($owner->phone, $message, ['stock_item_id' => $item->id]);
            }
        }
    }
}
