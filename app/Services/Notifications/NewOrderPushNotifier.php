<?php

namespace App\Services\Notifications;

use App\Contracts\PushNotifier;
use App\Models\Order;
use App\Models\PushSubscription;
use App\Services\Branches\BranchContext;
use Illuminate\Support\Collection;

/**
 * The push equivalent of orderAlertWidget()'s in-page audible alarm
 * (partials/order-alert-script.blade.php, realtime.md) — reaches the same
 * audience (staff, manager, general_manager at the order's branch; never
 * owner, who doesn't operate this board — see OrderDashboardController's
 * own docblock) via the OS notification tray instead, so a new order
 * still surfaces once the browser tab is backgrounded or the device is
 * locked and the in-page alarm's JS has been suspended.
 */
class NewOrderPushNotifier
{
    public function __construct(
        private readonly PushNotifier $push,
        private readonly BranchContext $branches,
    ) {}

    public function notify(Order $order): void
    {
        $staffIds = $this->branches->usersWithRole('staff', $order->branch_id)->pluck('id');
        $managerIds = collect(['manager', 'general_manager'])
            ->flatMap(fn (string $role) => $this->branches->usersWithRole($role, $order->branch_id))
            ->pluck('id');

        $title = __('New order :reference', ['reference' => $order->reference]);
        $body = __('Waiting for acceptance.');

        // Two sends, not one — staff and manager/general_manager land on
        // different routes for "the live board" (OrderDashboardController's
        // index() redirects the latter to dashboard.orders.live), so the
        // notification's click-through has to match whichever it actually
        // is for that recipient.
        $this->sendTo($staffIds, $title, $body, route('dashboard'), $order->id);
        $this->sendTo($managerIds, $title, $body, route('dashboard.orders.live'), $order->id);
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function sendTo(Collection $userIds, string $title, string $body, string $url, int $orderId): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        $subscriptions = PushSubscription::whereIn('user_id', $userIds->unique())->get();

        $this->push->notify($subscriptions, $title, $body, ['order_id' => $orderId, 'url' => $url]);
    }
}
