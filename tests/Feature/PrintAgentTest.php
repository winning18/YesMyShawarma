<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PrintAgent;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\Printing\PrintAgentService;
use App\Services\Settings\BusinessProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 8b: the print agent connects with a one-time code,
 * collects receipts, kitchen tickets and cash drawer kicks as ESC/POS,
 * and reports what printed.
 */
class PrintAgentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $item;

    private Printer $receipt;

    private Printer $kitchen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::create(['key' => BusinessProfile::NAME, 'value' => 'Mama Put Kitchen']);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'Oxford Street',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $this->item = MenuItem::create([
            'category_id' => Category::create(['name' => 'Wraps', 'slug' => 'wraps'])->id,
            'name' => 'Chicken Wrap', 'slug' => 'chicken-wrap', 'base_price' => 3000,
        ]);
        $this->branch->menuItems()->attach($this->item->id, ['is_available' => true]);
        $this->receipt = Printer::create(['branch_id' => $this->branch->id, 'name' => 'Front', 'role' => 'receipt', 'paper_width' => 80, 'connection' => 'network', 'address' => '192.168.1.50', 'opens_drawer' => true]);
        $this->kitchen = Printer::create(['branch_id' => $this->branch->id, 'name' => 'Kitchen', 'role' => 'kitchen', 'paper_width' => 58, 'connection' => 'usb', 'address' => 'EPSON TM-T82III']);
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

    /** A connected, online agent; returns its secret. */
    private function onlineAgent(): string
    {
        [, $code] = app(PrintAgentService::class)->startConnecting($this->branch, 'Counter PC');

        return app(PrintAgentService::class)->connect($code)['token'];
    }

    private function sell(User $staff, string $payment = 'cash')
    {
        $this->as($staff)->postJson(route('dashboard.pos.cart.add'), ['menu_item_id' => $this->item->id, 'quantity' => 2, 'option_ids' => []])->assertOk();

        return $this->as($staff)->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => $payment]);
    }

    public function test_an_agent_connects_once_with_a_code_from_the_printers_page(): void
    {
        $this->as($this->userWithRole('manager'))->post(route('dashboard.printers.agents.connect'), ['name' => 'Counter PC'])
            ->assertSessionHas('agentCode');
        $code = session('agentCode')['code'];
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);

        $this->postJson(route('api.print-agent.connect'), ['code' => strtolower($code)])->assertOk()
            ->assertJsonPath('agent', 'Counter PC')->assertJsonPath('branch', 'Osu')->assertJsonStructure(['token']);
        $this->postJson(route('api.print-agent.connect'), ['code' => $code])->assertUnprocessable();

        $this->getJson(route('api.print-agent.jobs'))->assertUnauthorized();
        $this->getJson(route('api.print-agent.jobs'), ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    }

    public function test_an_expired_code_doesnt_connect(): void
    {
        [, $code] = app(PrintAgentService::class)->startConnecting($this->branch, 'Counter PC');
        $this->travel(16)->minutes();

        $this->postJson(route('api.print-agent.connect'), ['code' => $code])->assertUnprocessable();
    }

    public function test_a_cash_sale_queues_its_receipt_drawer_and_kitchen_ticket_for_the_agent(): void
    {
        $token = $this->onlineAgent();
        $this->sell($this->userWithRole('staff'))->assertOk()->assertJsonPath('printed_by_agent', true);

        $this->assertEqualsCanonicalizing(['receipt', 'drawer', 'kitchen'], PrintJob::pluck('kind')->all());

        $jobs = $this->getJson(route('api.print-agent.jobs'), ['Authorization' => 'Bearer '.$token])->assertOk()->json('jobs');
        $this->assertCount(3, $jobs);
        $this->assertSame(PrintJob::SENT, PrintJob::first()->status);

        $byPrinter = collect($jobs)->groupBy('printer.name');
        $receipt = base64_decode($byPrinter['Front']->firstWhere(fn ($job) => strlen(base64_decode($job['data'])) > 20)['data']);
        $this->assertStringStartsWith("\x1B@", $receipt);
        $this->assertStringContainsString('Mama Put Kitchen', $receipt);
        $this->assertStringContainsString('2x Chicken Wrap', $receipt);
        $this->assertStringContainsString('GHS 60.00', $receipt);
        $this->assertStringContainsString("\x1DV", $receipt, 'paper cut');
        $this->assertSame(['network', '192.168.1.50'], [$byPrinter['Front'][0]['printer']['connection'], $byPrinter['Front'][0]['printer']['address']]);
        $this->assertStringContainsString("\x1Bp", base64_decode(collect($jobs)->firstWhere(fn ($job) => strlen(base64_decode($job['data'])) <= 20)['data']), 'drawer kick');
        $this->assertStringNotContainsString('60.00', base64_decode($byPrinter['Kitchen'][0]['data']));

        foreach ($jobs as $job) {
            $this->postJson(route('api.print-agent.report', $job['id']), ['printed' => true], ['Authorization' => 'Bearer '.$token])->assertOk();
        }
        $this->assertSame(3, PrintJob::where('status', PrintJob::PRINTED)->count());
        $this->getJson(route('api.print-agent.jobs'), ['Authorization' => 'Bearer '.$token])->assertJsonCount(0, 'jobs');
    }

    public function test_a_momo_sale_doesnt_open_the_drawer(): void
    {
        $this->onlineAgent();
        $this->sell($this->userWithRole('staff'), 'momo')->assertOk();

        $this->assertEqualsCanonicalizing(['receipt', 'kitchen'], PrintJob::pluck('kind')->all());
    }

    public function test_with_no_agent_online_nothing_is_queued_and_the_browser_prints(): void
    {
        $this->onlineAgent();
        $this->travel(2)->minutes();

        $this->sell($this->userWithRole('staff'))->assertOk()->assertJsonPath('printed_by_agent', false);
        $this->assertSame(0, PrintJob::count());

        $order = Order::sole();
        $this->as($this->userWithRole('staff'))->postJson(route('dashboard.orders.print.queue', [$order, 'receipt']))->assertJsonPath('queued', 0);
    }

    public function test_accepting_a_web_order_queues_its_kitchen_ticket(): void
    {
        $this->onlineAgent();
        $staff = $this->userWithRole('staff');
        $this->as($staff)->postJson(route('shift.start'))->assertOk();
        $order = Order::create([
            'reference' => 'WEB-AB12CD', 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => Customer::create(['phone' => '+233240000001'])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => 3000, 'total' => 3000,
            'payment_method' => 'cash', 'payment_status' => 'pending', 'channel' => 'web',
        ]);
        $order->forceFill(['status' => 'paid', 'placed_at' => now()])->save();

        $this->as($staff)->postJson(route('orders.accept', $order))->assertOk()->assertJsonPath('printed_by_agent', true);

        $this->assertSame(['kitchen'], PrintJob::where('order_id', $order->id)->pluck('kind')->all());
    }

    public function test_a_failed_print_shows_on_the_board_until_it_is_reprinted(): void
    {
        $token = $this->onlineAgent();
        $staff = $this->userWithRole('staff');
        $this->sell($staff, 'momo')->assertOk();
        $receipt = PrintJob::where('kind', 'receipt')->sole();

        $this->getJson(route('api.print-agent.jobs'), ['Authorization' => 'Bearer '.$token]);
        $this->postJson(route('api.print-agent.report', $receipt), ['printed' => false, 'error' => 'Printer out of paper'], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->as($staff)->getJson(route('dashboard.orders.data'))->assertJsonPath('print_failures.0.kind', 'receipt')->assertJsonPath('print_failures.0.error', 'Printer out of paper');

        $this->as($staff)->postJson(route('dashboard.orders.print.queue', [Order::sole(), 'receipt']))->assertJsonPath('queued', 1);
        $again = PrintJob::where('kind', 'receipt')->latest('id')->first();
        $this->getJson(route('api.print-agent.jobs'), ['Authorization' => 'Bearer '.$token]);
        $this->postJson(route('api.print-agent.report', $again), ['printed' => true], ['Authorization' => 'Bearer '.$token]);

        $this->as($staff)->getJson(route('dashboard.orders.data'))->assertJsonCount(0, 'print_failures');
    }

    public function test_a_job_the_agent_never_confirms_is_sent_again_then_given_up_on(): void
    {
        $token = $this->onlineAgent();
        $this->sell($this->userWithRole('staff'), 'momo')->assertOk();
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson(route('api.print-agent.jobs'), $headers)->assertJsonCount(2, 'jobs');
        $this->getJson(route('api.print-agent.jobs'), $headers)->assertJsonCount(0, 'jobs');

        foreach ([1, 2] as $retry) {
            $this->travel(3)->minutes();
            $this->getJson(route('api.print-agent.jobs'), $headers)->assertJsonCount(2, 'jobs');
        }

        $this->travel(3)->minutes();
        $this->getJson(route('api.print-agent.jobs'), $headers)->assertJsonCount(0, 'jobs');
        $this->assertSame(2, PrintJob::where('status', PrintJob::FAILED)->count());
    }

    public function test_the_printers_page_shows_whether_the_agent_is_online(): void
    {
        $this->onlineAgent();
        $manager = $this->userWithRole('manager');

        $this->as($manager)->get(route('dashboard.printers.index'))->assertSee('Counter PC')->assertSee('online');

        $this->travel(2)->minutes();
        $this->as($manager)->get(route('dashboard.printers.index'))->assertSee('offline');

        $this->as($manager)->delete(route('dashboard.printers.agents.disconnect', PrintAgent::sole()))->assertSessionHas('status');
        $this->assertSame(0, PrintAgent::count());
    }
}
