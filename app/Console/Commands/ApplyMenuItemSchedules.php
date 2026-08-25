<?php

namespace App\Console\Commands;

use App\Models\MenuItemSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Only menu items with at least one MenuItemSchedule row are touched —
 * everything else is left entirely to the manual Available/Sold out
 * toggle (Item availability page). day_of_week/starts_at/ends_at are
 * plain Africa/Accra local time (see the migration's docblock), so
 * "now" here is resolved in that timezone, not UTC.
 */
#[Signature('menu:apply-schedules')]
#[Description("Sync branch_menu_item.is_available with each scheduled item's day/time timetable")]
class ApplyMenuItemSchedules extends Command
{
    public function handle(): int
    {
        $now = now('Africa/Accra');
        $dayOfWeek = (int) $now->dayOfWeek;
        $time = $now->format('H:i:s');

        $scheduledPairs = MenuItemSchedule::select('menu_item_id', 'branch_id')->distinct()->get();

        foreach ($scheduledPairs as $pair) {
            $withinWindow = MenuItemSchedule::where('menu_item_id', $pair->menu_item_id)
                ->where('branch_id', $pair->branch_id)
                ->where('day_of_week', $dayOfWeek)
                ->where('starts_at', '<=', $time)
                ->where('ends_at', '>=', $time)
                ->exists();

            DB::table('branch_menu_item')
                ->where('menu_item_id', $pair->menu_item_id)
                ->where('branch_id', $pair->branch_id)
                ->update(['is_available' => $withinWindow]);
        }

        return self::SUCCESS;
    }
}
