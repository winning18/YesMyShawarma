<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Printer;
use App\Models\Setting;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Settings\BusinessProfile;
use App\Services\Shifts\CounterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 8a: printers, receipts and kitchen tickets printed
 * through the browser.
 */
class PrintingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::create(['key' => BusinessProfile::NAME, 'value' => 'Mama Put Kitchen']);
        Setting::create(['key' => BusinessProfile::RECEIPT_MESSAGE, 'value' => 'Thank you, come again!']);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'Oxford Street',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $item = MenuItem::create([
            'category_id' => Category::create(['name' => 'Wraps', 'slug' => 'wraps'])->id,
            'name' => 'Chicken Wrap', 'slug' => 'chicken-wrap', 'base_price' => 3000,
        ]);

        $this->order = Order::create([
            'reference' => 'POS-AB12CD', 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => Customer::create(['phone' => '+233240000001', 'name' => 'Ama'])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => 6000, 'total' => 6000,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'channel' => 'pos', 'instructions' => 'No pepper',
        ]);
        $this->order->forceFill(['status' => 'accepted', 'placed_at' => now()])->save();
        $this->order->items()->create(['menu_item_id' => $item->id, 'name_snapshot' => 'Chicken Wrap', 'unit_price_snapshot' => 3000, 'quantity' => 2, 'line_total' => 6000]);
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

    public function test_a_branch_without_printers_shows_no_print_buttons(): void
    {
        $manager = $this->userWithRole('manager');

        $this->as($manager)->get(route('dashboard.orders.show', $this->order))->assertOk()->assertDontSee('Print receipt');
        $this->as($manager)->get(route('dashboard.printers.index'))->assertOk()->assertSee('No printers yet');
    }

    public function test_managers_add_printers_and_staff_cannot(): void
    {
        $this->as($this->userWithRole('staff'))->get(route('dashboard.printers.index'))->assertForbidden();

        $manager = $this->userWithRole('manager');
        $this->as($manager)->post(route('dashboard.printers.store'), [
            'name' => 'Front receipts', 'role' => 'receipt', 'paper_width' => 80, 'connection' => 'network', 'address' => '192.168.1.50', 'opens_drawer' => '1',
        ])->assertSessionHas('status');
        $this->as($manager)->post(route('dashboard.printers.store'), [
            'name' => 'Kitchen', 'role' => 'kitchen', 'paper_width' => 58, 'connection' => 'usb', 'address' => 'EPSON TM-T82III', 'opens_drawer' => '1',
        ])->assertSessionHas('status');

        $this->assertTrue(Printer::where('name', 'Front receipts')->sole()->opens_drawer);
        $this->assertFalse(Printer::where('name', 'Kitchen')->sole()->opens_drawer, 'only a receipt printer has a drawer');
        $this->as($manager)->get(route('dashboard.orders.show', $this->order))->assertSee('Print receipt')->assertSee('Print kitchen ticket');
    }

    public function test_the_receipt_and_kitchen_ticket_print_what_they_should(): void
    {
        Printer::create(['branch_id' => $this->branch->id, 'name' => 'Front', 'role' => 'receipt', 'paper_width' => 80]);
        Printer::create(['branch_id' => $this->branch->id, 'name' => 'Kitchen', 'role' => 'kitchen', 'paper_width' => 58]);
        $staff = $this->userWithRole('staff');

        $this->as($staff)->get(route('dashboard.orders.print', [$this->order, 'receipt']))->assertOk()
            ->assertSee('size: 80mm auto', false)
            ->assertSee('Mama Put Kitchen')
            ->assertSee('Oxford Street')
            ->assertSee('POS-AB12CD')
            ->assertSee('2× Chicken Wrap')
            ->assertSee('GH₵60.00')
            ->assertSee('Paid by Cash')
            ->assertSee('Thank you, come again!');

        $this->as($staff)->get(route('dashboard.orders.print', [$this->order, 'kitchen']))->assertOk()
            ->assertSee('size: 58mm auto', false)
            ->assertSee('POS-AB12CD')
            ->assertSee('2× Chicken Wrap')
            ->assertSee('No pepper')
            ->assertDontSee('60.00');
    }

    public function test_a_counter_prints_receipts_on_its_own_printer(): void
    {
        Printer::create(['branch_id' => $this->branch->id, 'name' => 'Wide', 'role' => 'receipt', 'paper_width' => 80]);
        $narrow = Printer::create(['branch_id' => $this->branch->id, 'name' => 'Narrow', 'role' => 'receipt', 'paper_width' => 58]);
        $counter = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 2', 'receipt_printer_id' => $narrow->id]);
        $staff = $this->userWithRole('staff');

        $this->as($staff)->get(route('dashboard.orders.print', [$this->order, 'receipt']))->assertSee('size: 80mm auto', false);

        $this->withCredentials()->withCookie(CounterService::COOKIE, app(CounterService::class)->pair($counter)->getValue());
        $this->as($staff)->get(route('dashboard.orders.print', [$this->order, 'receipt']))->assertSee('size: 58mm auto', false);
    }
}
