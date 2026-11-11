<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\MarketList;
use App\Models\MarketListItem;
use App\Models\User;
use App\Services\MarketLists\ExpenseKindMatcher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpenseKindTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function kind(string $name): ExpenseCategory
    {
        return ExpenseCategory::where('name', $name)->firstOrFail();
    }

    private function item(string $description, string $kind, string $status = MarketList::STATUS_APPROVED): MarketListItem
    {
        $list = MarketList::create([
            'branch_id' => $this->branch->id, 'purchased_on' => '2026-11-05', 'recorded_by' => User::factory()->create()->id,
            'recorder_role' => 'staff', 'status' => $status, 'total' => 1000,
        ]);

        return $list->items()->create(['description' => $description, 'amount' => 1000, 'expense_category_id' => $this->kind($kind)->id]);
    }

    public function test_a_manager_adds_a_kind_that_is_suggested_straight_away(): void
    {
        $this->as($this->userWithRole('manager'))
            ->post(route('dashboard.market-lists.kinds.store'), ['name' => 'Cheese', 'keywords' => 'Cheese, MOZZARELLA ,cheddar,,'])
            ->assertRedirect(route('dashboard.market-lists.kinds.index'));

        $cheese = $this->kind('Cheese');
        $this->assertSame(['cheese', 'mozzarella', 'cheddar'], $cheese->keywords);
        $this->assertSame(['Cheese', 'Others'], ExpenseCategory::orderBy('sort_order')->get()->pluck('name')->take(-2)->values()->all());
        $this->assertSame('Cheese', (new ExpenseKindMatcher)->match('Cheddar 1kg')->name);
    }

    public function test_past_others_items_can_be_moved_into_the_new_kind(): void
    {
        $cheddar = $this->item('Cheddar 1kg', 'Others');
        $mozzarella = $this->item('Mozzarella', 'Others', MarketList::STATUS_PENDING);
        $sauce = $this->item('Cheese sauce', 'Sauces & spices');
        $rice = $this->item('Rice', 'Others');
        $manager = $this->userWithRole('manager');

        $this->as($manager)->post(route('dashboard.market-lists.kinds.store'), ['name' => 'Cheese', 'keywords' => 'cheese, mozzarella, cheddar']);

        $page = $this->as($manager)->get(route('dashboard.market-lists.kinds.index'))->assertOk()->assertSee('2 past items under Others match Cheese');
        $this->assertEqualsCanonicalizing([$cheddar->id, $mozzarella->id], $page->viewData('matches')->pluck('id')->all());

        $cheese = $this->kind('Cheese');
        $this->as($manager)->post(route('dashboard.market-lists.kinds.move-items', $cheese), ['items' => [$cheddar->id, $mozzarella->id, $sauce->id, $rice->id]])
            ->assertSessionHas('status', '2 items moved to Cheese.');

        $this->assertSame($cheese->id, $cheddar->fresh()->expense_category_id);
        $this->assertSame($cheese->id, $mozzarella->fresh()->expense_category_id);
        // Only ever from Others, and only what matches.
        $this->assertSame($this->kind('Sauces & spices')->id, $sauce->fresh()->expense_category_id);
        $this->assertSame($this->kind('Others')->id, $rice->fresh()->expense_category_id);

        $event = $cheddar->marketList->events()->where('action', 'kind_changed')->sole();
        $this->assertSame($manager->id, $event->actor_id);
        $this->assertSame('1 item moved from Others to Cheese (Cheddar 1kg).', $event->note);
    }

    public function test_a_hidden_kind_is_not_offered_but_lists_using_it_keep_it(): void
    {
        $manager = $this->userWithRole('manager');
        $gas = $this->kind('Gas');
        $this->item('Gas refill', 'Gas', MarketList::STATUS_PENDING);

        $this->as($manager)->post(route('dashboard.market-lists.kinds.toggle', $gas))->assertSessionHas('status');
        $this->assertFalse($gas->fresh()->is_active);
        $this->assertSame('Others', (new ExpenseKindMatcher)->match('Gas refill')->name);

        // The pending list still shows Gas for its item.
        $kinds = $this->as($manager)->get(route('dashboard.market-lists.index'))->viewData('kinds');
        $this->assertContains('Gas', $kinds->pluck('name')->all());

        $this->as($manager)->post(route('dashboard.market-lists.kinds.toggle', $gas));
        $this->assertTrue($gas->fresh()->is_active);
    }

    public function test_rename_and_reorder_but_others_stays_put(): void
    {
        $manager = $this->userWithRole('manager');
        $chicken = $this->kind('Chicken');
        $beef = $this->kind('Beef');
        $others = $this->kind('Others');

        $this->as($manager)->put(route('dashboard.market-lists.kinds.update', $chicken), ['name' => 'Chicken & poultry', 'keywords' => 'chicken, turkey']);
        $this->assertSame('Chicken & poultry', $chicken->fresh()->name);

        $this->as($manager)->post(route('dashboard.market-lists.kinds.move', $chicken), ['direction' => 'down']);
        $this->assertGreaterThan($beef->fresh()->sort_order, $chicken->fresh()->sort_order);

        $this->as($manager)->put(route('dashboard.market-lists.kinds.update', $others), ['name' => 'Misc', 'keywords' => 'stuff']);
        $this->as($manager)->post(route('dashboard.market-lists.kinds.toggle', $others));
        $this->assertSame('Others', $others->fresh()->name);
        $this->assertSame([], $others->fresh()->keywords);
        $this->assertTrue($others->fresh()->is_active);

        $this->as($manager)->post(route('dashboard.market-lists.kinds.store'), ['name' => 'Beef'])->assertSessionHasErrors('name');
    }

    public function test_staff_cannot_manage_kinds(): void
    {
        $staff = $this->userWithRole('staff');

        $this->as($staff)->get(route('dashboard.market-lists.kinds.index'))->assertForbidden();
        $this->as($staff)->post(route('dashboard.market-lists.kinds.store'), ['name' => 'Cheese'])->assertForbidden();
        $this->as($staff)->get(route('dashboard.market-lists.index'))->assertDontSee('Manage kinds');
    }
}
