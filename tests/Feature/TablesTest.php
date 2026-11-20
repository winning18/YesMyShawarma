<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Shift;
use App\Models\TableTab;
use App\Models\User;
use App\Services\Modules\ModuleService;
use App\Services\Settings\SettingsService;
use App\Services\Tables\TableTabService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tables (owner, 2026-11-20): eat-in rounds go to the kitchen straight
 * away, one bill is settled at the end at a till, a bill can move to a
 * free table, and a till can't close while its tables still owe.
 */
class TablesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $item;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $this->item = MenuItem::create(['category_id' => $category->id, 'name' => 'Wrap', 'slug' => 'wrap', 'base_price' => 5000]);
        $this->branch->menuItems()->attach($this->item->id, ['is_available' => true]);

        $this->staff = User::factory()->create(['name' => 'Ama']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->staff->assignRole('staff');
    }

    private function as(?User $user = null): static
    {
        return $this->actingAs($user ?? $this->staff)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function till(): Shift
    {
        return Shift::create(['user_id' => $this->staff->id, 'branch_id' => $this->branch->id, 'started_at' => now()->subHour()]);
    }

    private function table(string $name = '5'): DiningTable
    {
        $this->as()->postJson(route('dashboard.tables.store'), ['name' => $name])->assertCreated();

        return DiningTable::where('name', $name)->sole();
    }

    private function round(DiningTable $table, int $quantity = 1)
    {
        // Each table has its own cart.
        $this->as()->postJson(route('dashboard.pos.cart.add').'?table_id='.$table->id, ['menu_item_id' => $this->item->id, 'quantity' => $quantity, 'option_ids' => []]);

        return $this->as()->postJson(route('dashboard.pos.orders.store'), [
            'fulfilment_type' => 'pickup', 'payment_method' => 'cash', 'table_id' => $table->id,
        ]);
    }

    public function test_rounds_go_on_one_open_bill_unpaid(): void
    {
        $table = $this->table();

        $this->round($table)->assertOk()->assertJsonPath('order.table', '5');
        $this->round($table, 2)->assertOk();

        $tab = TableTab::sole();
        $this->assertTrue($tab->isOpen());
        $this->assertSame(2, $tab->orders()->count());
        $this->assertSame(['pending', 'pending'], $tab->orders()->pluck('payment_status')->all());
        $this->assertSame(['paid', 'paid'], $tab->orders()->pluck('status')->all(), 'with the kitchen straight away');

        $this->as()->getJson(route('dashboard.tables.index'))->assertJsonPath('tables.0.tab.total', 15000)->assertJsonPath('tables.0.tab.rounds', 2);
    }

    public function test_settling_pays_every_round_at_this_till_and_frees_the_table(): void
    {
        $table = $this->table();
        $this->round($table);
        $this->round($table);
        $tab = TableTab::sole();
        $till = $this->till();

        $this->as()->postJson(route('dashboard.tables.settle', $tab), ['payment_method' => 'cash'])
            ->assertOk()->assertJsonPath('total', 10000)->assertJsonPath('tables.0.tab', null);

        $tab->refresh();
        $this->assertFalse($tab->isOpen());
        $this->assertSame([10000, 'cash', $till->id], [$tab->total, $tab->payment_method, $tab->shift_id]);
        foreach ($tab->orders as $order) {
            $this->assertSame(['paid', $till->id], [$order->payment_status, $order->shift_id]);
            $this->assertSame('paid', $order->payments()->sole()->status);
            $this->assertTrue($order->events()->where('meta->action', 'table_tab_settled')->exists());
        }

        // The table takes a new bill now.
        $this->round($table)->assertOk();
        $this->assertSame(2, TableTab::count());
    }

    public function test_momo_needs_a_transaction_id_used_only_once(): void
    {
        $table = $this->table();
        $this->round($table);
        $tab = TableTab::sole();

        $this->as()->postJson(route('dashboard.tables.settle', $tab), ['payment_method' => 'momo'])->assertStatus(422);
        $this->as()->postJson(route('dashboard.tables.settle', $tab), ['payment_method' => 'momo', 'payment_reference' => 'TX123'])->assertOk();
        $this->assertSame('momo', Order::withoutGlobalScopes()->sole()->payment_method);

        $other = $this->table('6');
        $this->round($other);
        $this->as()->postJson(route('dashboard.tables.settle', $other->openTab), ['payment_method' => 'momo', 'payment_reference' => 'TX123'])->assertStatus(422);
    }

    public function test_a_bill_moves_only_to_a_free_table(): void
    {
        $five = $this->table('5');
        $six = $this->table('6');
        $seven = $this->table('7');
        $this->round($five);
        $this->round($seven);
        $tab = $five->openTab;

        $this->as()->postJson(route('dashboard.tables.move', $tab), ['table_id' => $seven->id])->assertStatus(422);
        $this->as()->postJson(route('dashboard.tables.move', $tab), ['table_id' => $six->id])->assertOk();

        $this->assertSame($six->id, $tab->fresh()->dining_table_id);
        $this->assertNull($five->fresh()->openTab);
    }

    public function test_a_till_cannot_close_while_its_tables_owe(): void
    {
        $this->till();
        $this->round($this->table());

        $this->as()->postJson(route('shift.end'), ['total_sales' => 50, 'no_expenses' => true])
            ->assertStatus(422)->assertJsonPath('message', 'A table still has an unpaid bill. Settle it before closing the till.');

        app(TableTabService::class)->settle(TableTab::sole(), 'cash', null, $this->staff, Shift::sole());
        $this->as()->postJson(route('shift.end'), ['total_sales' => 50, 'no_expenses' => true])->assertOk();
    }

    public function test_the_bill_prints_with_the_table_and_every_round(): void
    {
        $table = $this->table('Terrace 2');
        $this->round($table);
        $this->round($table, 2);

        $this->as()->get(route('dashboard.tables.bill', $table->openTab))
            ->assertOk()->assertSee('Table Terrace 2')->assertSee('150.00')->assertSee('Not paid yet');
    }

    public function test_tables_disappear_with_the_module(): void
    {
        $table = $this->table();
        app(ModuleService::class)->set(ModuleService::TABLES, false);

        $this->as()->getJson(route('dashboard.tables.index'))->assertNotFound();
        $this->round($table)->assertStatus(422)->assertJsonValidationErrors('table_id');
        $this->as()->get(route('dashboard.pos.index'))->assertDontSee('Add table');
    }

    public function test_a_branch_starts_with_three_tables_and_the_pos_opens_on_them(): void
    {
        $this->as()->getJson(route('dashboard.tables.index'))->assertOk()
            ->assertJsonPath('tables.*.name', ['1', '2', '3']);

        $this->as()->get(route('dashboard.pos.index'))->assertOk()->assertViewHas('posMode', 'both');
    }

    public function test_each_table_keeps_its_own_items_and_customer(): void
    {
        $one = $this->table('1');
        $two = $this->table('2');

        $this->as()->postJson(route('dashboard.pos.cart.add').'?table_id='.$one->id, ['menu_item_id' => $this->item->id, 'quantity' => 2, 'option_ids' => []]);
        $this->as()->getJson(route('dashboard.pos.cart.show', ['table_id' => $two->id]))->assertJsonPath('lines', []);
        $this->as()->getJson(route('dashboard.pos.cart.show'))->assertJsonPath('lines', []);
        $this->as()->getJson(route('dashboard.pos.cart.show', ['table_id' => $one->id]))->assertJsonPath('subtotal', 10000);

        $this->as()->postJson(route('dashboard.pos.orders.store'), [
            'fulfilment_type' => 'pickup', 'payment_method' => 'cash', 'table_id' => $one->id, 'name' => 'Kwesi', 'phone' => '0241112222',
        ])->assertOk();

        $tab = $one->openTab;
        $this->assertSame(['Kwesi', '+233241112222'], [$tab->customer_name, $tab->customer_phone]);

        // The next round takes the table's customer without retyping it.
        $this->round($one)->assertOk();
        $this->assertSame(['Kwesi', 'Kwesi'], $tab->orders()->with('customer')->get()->pluck('customer.name')->all());
    }

    public function test_tables_can_be_renamed_and_free_ones_deleted(): void
    {
        $five = $this->table('5');
        $six = $this->table('6');
        $this->round($six);

        $this->as()->putJson(route('dashboard.tables.update', $five), ['name' => '6'])->assertStatus(422);
        $this->as()->putJson(route('dashboard.tables.update', $five), ['name' => 'Terrace'])->assertOk();
        $this->assertSame('Terrace', $five->fresh()->name);

        $this->as()->deleteJson(route('dashboard.tables.destroy', $six))->assertStatus(422);
        $this->as()->deleteJson(route('dashboard.tables.destroy', $five))->assertOk()->assertJsonMissing(['name' => 'Terrace']);
        $this->assertFalse($five->fresh()->is_active, 'kept for its past bills');

        // Adding a table by that name again brings the same table back.
        $this->table('Terrace');
        $this->assertTrue($five->fresh()->is_active);
        $this->assertSame(1, DiningTable::where('name', 'like', 'Terrace%')->count());
    }

    public function test_tables_only_means_every_cash_or_momo_order_has_a_table_and_only_the_owner_chooses(): void
    {
        $owner = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $owner->assignRole('owner');
        $this->as($owner)->get(route('dashboard.settings.index'))->assertSee('How the POS works');
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $this->as($manager)->get(route('dashboard.settings.index'))->assertDontSee('How the POS works');

        app(SettingsService::class)->set('pos_mode', 'tables');
        $this->as()->postJson(route('dashboard.pos.cart.add'), ['menu_item_id' => $this->item->id, 'quantity' => 1, 'option_ids' => []]);
        $this->as()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash'])
            ->assertStatus(422)->assertJsonValidationErrors('table_id');

        app(SettingsService::class)->set('pos_mode', 'quick');
        $this->as()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash'])->assertOk();
    }

    public function test_a_table_with_unsent_items_is_in_use_and_quick_sale_never_sees_them(): void
    {
        $one = $this->table('1');
        $this->as()->postJson(route('dashboard.pos.cart.add').'?table_id='.$one->id, ['menu_item_id' => $this->item->id, 'quantity' => 1, 'option_ids' => []]);

        $this->as()->getJson(route('dashboard.tables.index'))
            ->assertJsonPath('tables.0.unsent', 1)
            ->assertJsonPath('tables.0.tab', null);
        $this->as()->getJson(route('dashboard.pos.cart.show'))->assertJsonPath('lines', []);

        // A quick sale can't send the table's items.
        $this->as()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash'])
            ->assertStatus(422)->assertJsonPath('message', 'The cart is empty.');
        $this->assertSame(1, $this->as()->getJson(route('dashboard.tables.index'))->json('tables.0.unsent'));
    }
}
