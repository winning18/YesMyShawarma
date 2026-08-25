<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuItemSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApplyMenuItemSchedulesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeItem(bool $initiallyAvailable): MenuItem
    {
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $item->branches()->attach($this->branch->id, ['is_available' => $initiallyAvailable]);

        return $item;
    }

    public function test_a_scheduled_item_becomes_available_within_its_window(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::FRIDAY)->setTime(19, 0));

        $item = $this->makeItem(initiallyAvailable: false);
        MenuItemSchedule::create([
            'menu_item_id' => $item->id, 'branch_id' => $this->branch->id,
            'day_of_week' => Carbon::FRIDAY, 'starts_at' => '18:00', 'ends_at' => '22:00',
        ]);

        $this->artisan('menu:apply-schedules')->assertSuccessful();

        $this->assertTrue((bool) $item->branches()->where('branches.id', $this->branch->id)->first()->pivot->is_available);
    }

    public function test_a_scheduled_item_becomes_unavailable_outside_its_window(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::FRIDAY)->setTime(10, 0));

        $item = $this->makeItem(initiallyAvailable: true);
        MenuItemSchedule::create([
            'menu_item_id' => $item->id, 'branch_id' => $this->branch->id,
            'day_of_week' => Carbon::FRIDAY, 'starts_at' => '18:00', 'ends_at' => '22:00',
        ]);

        $this->artisan('menu:apply-schedules')->assertSuccessful();

        $this->assertFalse((bool) $item->branches()->where('branches.id', $this->branch->id)->first()->pivot->is_available);
    }

    public function test_a_scheduled_item_is_unavailable_on_a_different_day(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(19, 0));

        $item = $this->makeItem(initiallyAvailable: true);
        MenuItemSchedule::create([
            'menu_item_id' => $item->id, 'branch_id' => $this->branch->id,
            'day_of_week' => Carbon::FRIDAY, 'starts_at' => '18:00', 'ends_at' => '22:00',
        ]);

        $this->artisan('menu:apply-schedules')->assertSuccessful();

        $this->assertFalse((bool) $item->branches()->where('branches.id', $this->branch->id)->first()->pivot->is_available);
    }

    public function test_an_item_with_no_schedule_is_left_untouched(): void
    {
        $item = $this->makeItem(initiallyAvailable: false);

        $this->artisan('menu:apply-schedules')->assertSuccessful();

        // Still false — the command never touched it since it has no
        // MenuItemSchedule rows at all.
        $this->assertFalse((bool) $item->branches()->where('branches.id', $this->branch->id)->first()->pivot->is_available);
    }
}
