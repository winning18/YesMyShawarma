<?php

namespace Tests\Feature;

use App\Contracts\Notifier;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SmsBroadcast;
use App\Models\User;
use App\Services\Sms\SmsBroadcastService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SmsBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    /** @var list<array{phone: string, message: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

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

    private function fakeSms(bool $accept = true): void
    {
        $this->mock(Notifier::class, function ($mock) use ($accept) {
            $mock->shouldReceive('send')->andReturnUsing(function (string $phone, string $message) use ($accept) {
                $this->sent[] = ['phone' => $phone, 'message' => $message];

                return $accept;
            });
        });
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function orderFor(Customer $customer, Branch $branch): void
    {
        Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 1000,
            'total' => 1000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
    }

    private function broadcasts(): SmsBroadcastService
    {
        return app(SmsBroadcastService::class);
    }

    public function test_general_manager_and_owner_can_open_broadcasts_but_manager_and_staff_cannot(): void
    {
        $this->actingAs($this->userWithRole('general_manager'))->get(route('dashboard.customers.broadcasts.index'))->assertOk();
        $this->actingAs($this->userWithRole('owner'))->get(route('dashboard.customers.broadcasts.index'))->assertOk();
        $this->actingAs($this->userWithRole('manager'))->get(route('dashboard.customers.broadcasts.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('dashboard.customers.broadcasts.index'))->assertForbidden();
    }

    public function test_customers_list_shows_the_broadcasts_tab_only_to_those_who_can_send(): void
    {
        $this->actingAs($this->userWithRole('general_manager'))->get(route('dashboard.customers.index'))->assertSee('SMS broadcasts');
        $this->actingAs($this->userWithRole('manager'))->get(route('dashboard.customers.index'))->assertDontSee('SMS broadcasts');
    }

    public function test_audience_skips_opted_out_phoneless_and_invalid_numbers_and_removes_duplicates(): void
    {
        Customer::create(['phone' => '+233241111111']);
        Customer::create(['phone' => '0241111111']); // legacy, un-normalised copy of the same number
        Customer::create(['phone' => '+233242222222', 'marketing_opted_out_at' => now()]);
        Customer::create(['phone' => null]);
        Customer::create(['phone' => '12345']);

        $audience = $this->broadcasts()->audience(null);

        $this->assertSame(['+233241111111'], array_values($audience['recipients']));
        $this->assertSame(1, $audience['duplicates']);
        $this->assertSame(1, $audience['invalid']);
        $this->assertSame(1, $audience['opted_out']);
    }

    public function test_audience_can_be_narrowed_to_customers_of_one_branch(): void
    {
        $other = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $osuCustomer = Customer::create(['phone' => '+233241111111']);
        $pokuaseCustomer = Customer::create(['phone' => '+233242222222']);
        $this->orderFor($osuCustomer, $this->branch);
        $this->orderFor($pokuaseCustomer, $other);

        $this->assertSame([$osuCustomer->id => '+233241111111'], $this->broadcasts()->audience($this->branch->id)['recipients']);
        $this->assertCount(2, $this->broadcasts()->audience(null)['recipients']);
    }

    public function test_send_now_texts_every_recipient_with_their_own_opt_out_link_and_marks_it_sent(): void
    {
        $this->fakeSms();
        Customer::create(['phone' => '+233241111111']);
        Customer::create(['phone' => '+233242222222']);

        $this->actingAs($this->userWithRole('general_manager'))
            ->post(route('dashboard.customers.broadcasts.store'), ['message' => 'Shawarma Wednesday!', 'send_mode' => 'now'])
            ->assertRedirect(route('dashboard.customers.broadcasts.index'));

        $this->artisan('sms:send-due-broadcasts')->assertSuccessful();

        $broadcast = SmsBroadcast::sole();
        $this->assertSame('sent', $broadcast->status);
        $this->assertSame(2, $broadcast->recipient_count);
        $this->assertSame(2, $broadcast->sent_count);
        $this->assertCount(2, $this->sent);

        $codes = $broadcast->recipients()->pluck('opt_out_code');
        $this->assertCount(2, $codes->unique());
        $this->assertStringStartsWith("Shawarma Wednesday!\nStop: ", $this->sent[0]['message']);
        $this->assertStringContainsString('/stop/'.$codes[0], $this->sent[0]['message']);
        $this->assertStringNotContainsString('https://', $this->sent[0]['message']);
    }

    public function test_a_rejected_send_is_recorded_as_failed(): void
    {
        $this->fakeSms(accept: false);
        Customer::create(['phone' => '+233241111111']);
        $this->broadcasts()->schedule('Hello', null, null, $this->userWithRole('owner'));

        $this->artisan('sms:send-due-broadcasts');

        $broadcast = SmsBroadcast::sole();
        $this->assertSame('sent', $broadcast->status);
        $this->assertSame(1, $broadcast->failed_count);
        $this->assertSame('failed', $broadcast->recipients()->sole()->status);
    }

    public function test_a_scheduled_broadcast_waits_for_its_time_and_converts_accra_time_to_utc(): void
    {
        $this->fakeSms();
        Carbon::setTestNow('2026-10-20 09:00:00');
        Customer::create(['phone' => '+233241111111']);

        $this->actingAs($this->userWithRole('owner'))->post(route('dashboard.customers.broadcasts.store'), [
            'message' => 'Lunch deal', 'send_mode' => 'later', 'scheduled_at' => '2026-10-21T12:30',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-10-21 12:30:00', SmsBroadcast::sole()->scheduled_at->utc()->format('Y-m-d H:i:s'));

        $this->artisan('sms:send-due-broadcasts');
        $this->assertSame('scheduled', SmsBroadcast::sole()->status);
        $this->assertSame([], $this->sent);

        Carbon::setTestNow('2026-10-21 12:30:00');
        $this->artisan('sms:send-due-broadcasts');
        $this->assertSame('sent', SmsBroadcast::sole()->status);
        $this->assertCount(1, $this->sent);
    }

    public function test_scheduling_in_the_past_is_rejected(): void
    {
        Customer::create(['phone' => '+233241111111']);

        $this->actingAs($this->userWithRole('owner'))->post(route('dashboard.customers.broadcasts.store'), [
            'message' => 'Late', 'send_mode' => 'later', 'scheduled_at' => now('Africa/Accra')->subHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('scheduled_at');

        $this->assertSame(0, SmsBroadcast::count());
    }

    public function test_only_one_broadcast_is_allowed_per_seven_days(): void
    {
        Customer::create(['phone' => '+233241111111']);
        $owner = $this->userWithRole('owner');
        $this->broadcasts()->schedule('First', null, now()->addDays(3), $owner);

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.store'), ['message' => 'Second', 'send_mode' => 'now'])
            ->assertSessionHasErrors('message');

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.store'), [
            'message' => 'Next week', 'send_mode' => 'later', 'scheduled_at' => now('Africa/Accra')->addDays(11)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, SmsBroadcast::count());
    }

    public function test_a_cancelled_broadcast_never_sends_and_frees_its_week(): void
    {
        $this->fakeSms();
        Customer::create(['phone' => '+233241111111']);
        $owner = $this->userWithRole('owner');
        $broadcast = $this->broadcasts()->schedule('Oops', null, now()->addHour(), $owner);

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.cancel', $broadcast))->assertSessionHasNoErrors();

        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('sms:send-due-broadcasts');

        $this->assertSame('cancelled', $broadcast->fresh()->status);
        $this->assertSame([], $this->sent);
        $this->broadcasts()->schedule('Fixed', null, null, $owner);
        $this->assertSame(2, SmsBroadcast::count());
    }

    public function test_a_broadcast_with_nobody_to_send_to_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('owner'))
            ->post(route('dashboard.customers.broadcasts.store'), ['message' => 'Hi', 'send_mode' => 'now'])
            ->assertSessionHasErrors('message');
    }

    public function test_a_stopped_send_resumes_from_pending_recipients_without_texting_anyone_twice(): void
    {
        $this->fakeSms();
        foreach (range(1, 3) as $i) {
            Customer::create(['phone' => '+23324000000'.$i]);
        }
        $broadcast = $this->broadcasts()->schedule('Hi', null, null, $this->userWithRole('owner'));
        SmsBroadcast::whereKey($broadcast->id)->update(['status' => 'sending']);
        (fn () => $this->buildRecipients($broadcast))->call($this->broadcasts());
        $broadcast->recipients()->orderBy('id')->first()->update(['status' => 'sent']);

        $this->artisan('sms:send-due-broadcasts');

        $this->assertCount(2, $this->sent);
        $this->assertSame('sent', $broadcast->fresh()->status);
        $this->assertSame(3, $broadcast->fresh()->sent_count);
    }

    public function test_opt_out_link_asks_first_then_opts_the_customer_out_of_later_broadcasts(): void
    {
        $this->fakeSms();
        $customer = Customer::create(['phone' => '+233241111111']);
        $this->broadcasts()->schedule('Hi', null, null, $this->userWithRole('owner'));
        $this->artisan('sms:send-due-broadcasts');
        $code = SmsBroadcast::sole()->recipients()->sole()->opt_out_code;

        $this->get(route('sms.opt-out.show', $code))->assertOk()->assertSee('Yes, stop these messages')
            ->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertNull($customer->fresh()->marketing_opted_out_at);

        $this->post(route('sms.opt-out.store', $code))->assertOk()->assertSee('any more promotional SMS');
        $this->assertNotNull($customer->fresh()->marketing_opted_out_at);
        $this->assertSame([], $this->broadcasts()->audience(null)['recipients']);
    }

    public function test_unknown_opt_out_code_is_not_found(): void
    {
        $this->get('/stop/AAAAAAAA')->assertNotFound();
        $this->post('/stop/AAAAAAAA')->assertNotFound();
    }

    public function test_sms_parts_follow_gsm_and_unicode_limits(): void
    {
        $service = $this->broadcasts();

        $this->assertSame(1, $service->smsParts(str_repeat('a', 160)));
        $this->assertSame(2, $service->smsParts(str_repeat('a', 161)));
        $this->assertSame(2, $service->smsParts(str_repeat('a', 159).'€'));
        $this->assertSame(1, $service->smsParts(str_repeat('a', 68).'🌯'));
        $this->assertSame(2, $service->smsParts(str_repeat('a', 69).'🌯'));
    }
}
