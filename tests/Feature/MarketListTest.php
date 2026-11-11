<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\MarketList;
use App\Models\Shift;
use App\Models\User;
use App\Services\MarketLists\ExpenseKindMatcher;
use App\Services\MarketLists\MarketListService;
use App\Services\Shifts\ShiftService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MarketListTest extends TestCase
{
    use RefreshDatabase;

    private Branch $main;

    private Branch $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->main = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->other = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function userWithRole(string $role, ?Branch $branch = null, string $name = 'Someone'): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(($branch ?? $this->main)->id);
        $user->assignRole($role);

        return $user;
    }

    private function as(User $user, ?Branch $branch = null): static
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => ($branch ?? $this->main)->id]);
    }

    private function kind(string $name): int
    {
        return ExpenseCategory::where('name', $name)->value('id');
    }

    /**
     * @param  list<array<string, mixed>>|null  $items
     */
    private function submit(User $user, ?array $items = null, ?Branch $branch = null): MarketList
    {
        $this->as($user, $branch)->post(route('dashboard.market-lists.store'), [
            'purchased_on' => now('Africa/Accra')->toDateString(),
            'items' => $items ?? [
                ['description' => '10kg chicken', 'quantity' => 10, 'unit' => 'kg', 'amount' => '450.00'],
                ['description' => 'Trotro to market', 'amount' => '12.50'],
                ['description' => '', 'amount' => ''],
            ],
        ])->assertRedirect(route('dashboard.market-lists.index'));

        return MarketList::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_kinds_are_suggested_from_the_description(): void
    {
        $kinds = app(ExpenseKindMatcher::class);

        $this->assertSame('Chicken', $kinds->match('10kg CHICKEN')->name);
        $this->assertSame('Cooking oil', $kinds->match('Frytol vegetable oil 5L')->name);
        $this->assertSame('Vegetables', $kinds->match('onions')->name);
        $this->assertSame('Cleaning', $kinds->match('toilet roll')->name);
        $this->assertSame('Transport', $kinds->match('Trotro to market')->name);
        $this->assertSame('Others', $kinds->match('Rice')->name);
    }

    public function test_staff_record_a_list_that_waits_for_approval(): void
    {
        $staff = $this->userWithRole('staff');
        $list = $this->submit($staff);

        $this->assertSame(MarketList::STATUS_PENDING, $list->status);
        $this->assertSame('staff', $list->recorder_role);
        $this->assertSame(46250, $list->total);
        $this->assertSame(['Chicken', 'Transport'], $list->items()->with('category')->get()->pluck('category.name')->all());
        $this->assertSame(45000, $list->items()->first()->amount);
        $this->assertSame(['submitted'], $list->events()->pluck('action')->all());

        $manager = $this->userWithRole('manager');
        $this->assertSame(1, app(MarketListService::class)->awaitingApprovalCount($manager));
    }

    public function test_a_kind_chosen_by_the_recorder_is_kept(): void
    {
        $list = $this->submit($this->userWithRole('staff'), [
            ['description' => 'Cheese', 'amount' => '80', 'expense_category_id' => $this->kind('Sauces & spices')],
        ]);

        $this->assertSame($this->kind('Sauces & spices'), $list->items()->first()->expense_category_id);
    }

    public function test_a_list_with_no_real_items_is_refused(): void
    {
        $this->as($this->userWithRole('staff'))->post(route('dashboard.market-lists.store'), [
            'purchased_on' => now('Africa/Accra')->toDateString(),
            'items' => [['description' => 'Chicken', 'amount' => '0']],
        ])->assertSessionHasErrors('market_list');

        $this->assertSame(0, MarketList::withoutGlobalScopes()->count());
    }

    public function test_the_owners_own_list_is_approved_automatically(): void
    {
        $list = $this->submit($this->userWithRole('owner'));

        $this->assertSame(MarketList::STATUS_APPROVED, $list->status);
        $this->assertSame(['submitted', 'auto_approved'], $list->events()->pluck('action')->all());
    }

    public function test_a_stock_manager_can_record(): void
    {
        $list = $this->submit($this->userWithRole('stock_manager'));

        $this->assertSame(MarketList::STATUS_PENDING, $list->status);
    }

    public function test_a_manager_approves_staff_lists_and_can_correct_a_kind(): void
    {
        $list = $this->submit($this->userWithRole('staff'));
        $transport = $list->items()->get()->last();
        $manager = $this->userWithRole('manager');

        $this->as($manager)->post(route('dashboard.market-lists.approve', $list), [
            'kinds' => [$transport->id => $this->kind('Others')],
        ])->assertSessionHas('status', 'Market list approved.');

        $list->refresh();
        $this->assertSame(MarketList::STATUS_APPROVED, $list->status);
        $this->assertSame($manager->id, $list->reviewed_by);
        $this->assertSame($this->kind('Others'), $transport->fresh()->expense_category_id);
        $this->assertSame('1 kind(s) corrected.', $list->events()->where('action', 'approved')->value('note'));

        // Locked once approved.
        $this->as($manager)->get(route('dashboard.market-lists.edit', $list))->assertForbidden();
    }

    public function test_nobody_approves_their_own_list(): void
    {
        $manager = $this->userWithRole('manager');
        $list = $this->submit($manager);

        $this->as($manager)->post(route('dashboard.market-lists.approve', $list))->assertForbidden();
        $this->assertSame(0, app(MarketListService::class)->awaitingApprovalCount($manager));
    }

    public function test_only_the_owner_approves_a_managers_or_gms_list(): void
    {
        $managerList = $this->submit($this->userWithRole('manager'));
        $gmList = $this->submit($this->userWithRole('general_manager'));

        $otherManager = $this->userWithRole('manager');
        $gm = $this->userWithRole('general_manager');

        $this->as($otherManager)->post(route('dashboard.market-lists.approve', $managerList))->assertForbidden();
        $this->as($gm)->post(route('dashboard.market-lists.approve', $managerList))->assertForbidden();
        $this->as($otherManager)->post(route('dashboard.market-lists.approve', $gmList))->assertForbidden();
        $this->assertSame(0, app(MarketListService::class)->awaitingApprovalCount($otherManager));

        $owner = $this->userWithRole('owner');
        $this->assertSame(2, app(MarketListService::class)->awaitingApprovalCount($owner));
        $this->as($owner)->post(route('dashboard.market-lists.approve', $managerList))->assertSessionHas('status');
        $this->assertSame(MarketList::STATUS_APPROVED, $managerList->fresh()->status);
    }

    public function test_a_manager_cannot_approve_another_branchs_list(): void
    {
        $list = $this->submit($this->userWithRole('staff', $this->other), branch: $this->other);
        $manager = $this->userWithRole('manager');

        $this->as($manager)->post(route('dashboard.market-lists.approve', $list))->assertForbidden();
    }

    public function test_a_general_manager_approves_at_every_branch_they_run(): void
    {
        $list = $this->submit($this->userWithRole('staff', $this->other), branch: $this->other);
        $gm = $this->userWithRole('general_manager');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->other->id);
        $gm->assignRole('general_manager');

        $this->as($gm, $this->main)->get(route('dashboard.market-lists.index'))->assertOk()->assertSee('Pokuase');
        $this->as($gm, $this->main)->post(route('dashboard.market-lists.approve', $list))->assertSessionHas('status');

        $this->assertSame(MarketList::STATUS_APPROVED, $list->fresh()->status);
    }

    public function test_send_back_then_resubmit(): void
    {
        $staff = $this->userWithRole('staff');
        $list = $this->submit($staff);
        $manager = $this->userWithRole('manager');

        $this->as($manager)->post(route('dashboard.market-lists.send-back', $list), [])->assertSessionHasErrors('reason');
        $this->as($manager)->post(route('dashboard.market-lists.send-back', $list), ['reason' => 'Chicken price looks wrong'])
            ->assertSessionHas('status');
        $this->assertSame(MarketList::STATUS_NEEDS_CHANGES, $list->fresh()->status);

        // Can't be approved while it's with the recorder.
        $this->as($manager)->post(route('dashboard.market-lists.approve', $list))->assertForbidden();

        $this->as($staff)->get(route('dashboard.market-lists.edit', $list))->assertOk()->assertSee('Chicken price looks wrong');
        $this->as($staff)->put(route('dashboard.market-lists.update', $list), [
            'purchased_on' => now('Africa/Accra')->toDateString(),
            'items' => [['description' => '10kg chicken', 'quantity' => 10, 'unit' => 'kg', 'amount' => '420']],
        ])->assertRedirect(route('dashboard.market-lists.index'));

        $list->refresh();
        $this->assertSame(MarketList::STATUS_PENDING, $list->status);
        $this->assertSame(42000, $list->total);
        $this->assertSame(['submitted', 'sent_back', 'resubmitted'], $list->events()->orderBy('id')->pluck('action')->all());
    }

    public function test_staff_only_see_their_own_lists(): void
    {
        $ama = $this->userWithRole('staff', name: 'Ama');
        $kofi = $this->userWithRole('staff', name: 'Kofi');
        $this->submit($ama, [['description' => 'Ama gas', 'amount' => '100']]);
        $this->submit($kofi, [['description' => 'Kofi onions', 'amount' => '50']]);

        $this->as($ama)->get(route('dashboard.market-lists.index'))->assertSee('Ama gas')->assertDontSee('Kofi onions');
        $this->as($this->userWithRole('manager'))->get(route('dashboard.market-lists.index'))->assertSee('Ama gas')->assertSee('Kofi onions');
        $this->as($kofi)->get(route('dashboard.market-lists.edit', MarketList::withoutGlobalScopes()->where('recorded_by', $ama->id)->first()))->assertForbidden();
    }

    public function test_riders_have_no_market_list(): void
    {
        $this->as($this->userWithRole('rider'))->get(route('dashboard.market-lists.index'))->assertForbidden();
    }

    public function test_the_receipt_photo_is_private(): void
    {
        Storage::fake('local');
        $staff = $this->userWithRole('staff');

        $this->as($staff)->post(route('dashboard.market-lists.store'), [
            'purchased_on' => now('Africa/Accra')->toDateString(),
            'items' => [['description' => 'Gas refill', 'amount' => '150']],
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
        $list = MarketList::withoutGlobalScopes()->firstOrFail();

        Storage::disk('local')->assertExists($list->receipt_path);
        $this->as($staff)->get(route('dashboard.market-lists.receipt', $list))->assertOk();
        $this->as($this->userWithRole('manager'))->get(route('dashboard.market-lists.receipt', $list))->assertOk();
        $this->as($this->userWithRole('staff'))->get(route('dashboard.market-lists.receipt', $list))->assertForbidden();
    }

    public function test_expenses_entered_at_shift_close_land_on_the_market_list(): void
    {
        $staff = $this->userWithRole('staff');
        $shift = Shift::create(['user_id' => $staff->id, 'branch_id' => $this->main->id, 'started_at' => now()->subHours(8)]);

        app(ShiftService::class)->end($shift, $staff, 100000, 100000, null, [
            ['description' => 'Gas refill', 'amount' => 15000],
            ['description' => 'Okada', 'amount' => 2000],
        ]);

        $this->assertSame(2, $shift->expenses()->count());
        $list = MarketList::withoutGlobalScopes()->where('shift_id', $shift->id)->firstOrFail();
        $this->assertSame(MarketList::STATUS_PENDING, $list->status);
        $this->assertSame(17000, $list->total);
        $this->assertSame(['Gas', 'Transport'], $list->items()->with('category')->get()->pluck('category.name')->all());
    }

    public function test_existing_shift_expenses_are_converted_once(): void
    {
        $staff = $this->userWithRole('staff');
        $shift = Shift::create(['user_id' => $staff->id, 'branch_id' => $this->main->id, 'started_at' => '2026-10-05 08:00:00', 'ended_at' => '2026-10-05 20:00:00', 'ended_by_user_id' => $staff->id]);
        DB::table('shift_expenses')->insert([
            ['shift_id' => $shift->id, 'description' => 'Chicken', 'amount' => 30000, 'created_at' => '2026-10-05 20:00:00'],
            ['shift_id' => $shift->id, 'description' => 'Taxi', 'amount' => 1500, 'created_at' => '2026-10-05 20:00:00'],
        ]);

        $migration = require database_path('migrations/2026_11_11_100300_convert_shift_expenses_to_market_lists.php');
        $migration->up();
        $migration->up();

        $list = MarketList::withoutGlobalScopes()->sole();
        $this->assertSame(MarketList::STATUS_APPROVED, $list->status);
        $this->assertSame('2026-10-05', $list->purchased_on->toDateString());
        $this->assertSame(31500, $list->total);
        $this->assertSame(['Chicken', 'Transport'], $list->items()->with('category')->get()->pluck('category.name')->all());
    }

    public function test_the_sidebar_shows_how_many_wait_for_approval(): void
    {
        $this->submit($this->userWithRole('staff'));

        $this->as($this->userWithRole('manager'))->get(route('dashboard.market-lists.index'))
            ->assertSee('Market list')
            ->assertSee('title="Waiting for your approval">1</span>', false);
    }
}
