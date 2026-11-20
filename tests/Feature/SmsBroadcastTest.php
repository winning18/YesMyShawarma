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
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SmsBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    /** @var list<array{phone: string, message: string}> */
    private array $sent = [];

    /** @var list<string> numbers fakeFlakySms() rejects until cleared */
    private array $rejecting = [];

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

    /**
     * Like fakeSms(), but rejects whatever is in $this->rejecting at the
     * moment of sending — clear it to make the "provider" recover.
     */
    private function fakeFlakySms(): void
    {
        $this->mock(Notifier::class, function ($mock) {
            $mock->shouldReceive('send')->andReturnUsing(function (string $phone, string $message) {
                $this->sent[] = ['phone' => $phone, 'message' => $message];

                return ! in_array($phone, $this->rejecting, true);
            });
        });
    }

    /**
     * A sent broadcast to three customers where the provider rejected the
     * second and third numbers.
     */
    private function sentBroadcastWithTwoFailures(User $owner): SmsBroadcast
    {
        $this->fakeFlakySms();
        foreach (['+233241111111', '+233242222222', '+233243333333'] as $phone) {
            $this->customer($phone);
        }
        $this->rejecting = ['+233242222222', '+233243333333'];

        $this->broadcasts()->schedule('Shawarma Wednesday!', ['all' => true], null, $owner);
        $this->artisan('sms:send-due-broadcasts');

        $broadcast = SmsBroadcast::sole();
        $this->assertSame('sent', $broadcast->status);
        $this->assertSame(2, $broadcast->failed_count);
        $this->sent = [];

        return $broadcast;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    /**
     * A real customer — they have an account — so they're on the "All
     * customers" list. A bare phone-only row with no orders is what an
     * old upload left behind, and isn't (SmsBroadcastService::audience()).
     */
    private function customer(?string $phone, array $attributes = []): Customer
    {
        return Customer::create(['phone' => $phone, 'password' => 'secret-pass', ...$attributes]);
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
        $this->customer('+233241111111');
        $this->customer('0241111111'); // legacy, un-normalised copy of the same number
        $this->customer('+233242222222', ['marketing_opted_out_at' => now()]);
        $this->customer(null);
        $this->customer('12345');

        $audience = $this->broadcasts()->audience(['all' => true]);

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
        $osuCustomer = $this->customer('+233241111111');
        $pokuaseCustomer = $this->customer('+233242222222');
        $this->orderFor($osuCustomer, $this->branch);
        $this->orderFor($pokuaseCustomer, $other);

        $this->assertSame([$osuCustomer->id => '+233241111111'], $this->broadcasts()->audience(['branches' => [$this->branch->id]])['recipients']);
        $this->assertCount(2, $this->broadcasts()->audience(['all' => true])['recipients']);
    }

    public function test_send_now_texts_every_recipient_with_their_own_opt_out_link_and_marks_it_sent(): void
    {
        $this->fakeSms();
        $this->customer('+233241111111');
        $this->customer('+233242222222');

        $this->actingAs($this->userWithRole('general_manager'))
            ->post(route('dashboard.customers.broadcasts.store'), ['message' => 'Shawarma Wednesday!', 'all' => '1', 'send_mode' => 'now'])
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
        $this->customer('+233241111111');
        $this->broadcasts()->schedule('Hello', ['all' => true], null, $this->userWithRole('owner'));

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
        $this->customer('+233241111111');

        $this->actingAs($this->userWithRole('owner'))->post(route('dashboard.customers.broadcasts.store'), [
            'message' => 'Lunch deal', 'all' => '1', 'send_mode' => 'later', 'scheduled_at' => '2026-10-21T12:30',
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
        $this->customer('+233241111111');

        $this->actingAs($this->userWithRole('owner'))->post(route('dashboard.customers.broadcasts.store'), [
            'message' => 'Late', 'all' => '1', 'send_mode' => 'later', 'scheduled_at' => now('Africa/Accra')->subHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('scheduled_at');

        $this->assertSame(0, SmsBroadcast::count());
    }

    public function test_broadcasts_can_be_sent_back_to_back_with_no_frequency_limit(): void
    {
        $this->customer('+233241111111');
        $owner = $this->userWithRole('owner');
        $this->broadcasts()->schedule('First', ['all' => true], null, $owner);

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.store'), ['message' => 'Second', 'all' => '1', 'send_mode' => 'now'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, SmsBroadcast::count());
    }

    public function test_a_cancelled_broadcast_never_sends(): void
    {
        $this->fakeSms();
        $this->customer('+233241111111');
        $owner = $this->userWithRole('owner');
        $broadcast = $this->broadcasts()->schedule('Oops', ['all' => true], now()->addHour(), $owner);

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.cancel', $broadcast))->assertSessionHasNoErrors();

        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('sms:send-due-broadcasts');

        $this->assertSame('cancelled', $broadcast->fresh()->status);
        $this->assertSame([], $this->sent);
    }

    public function test_a_broadcast_with_nobody_to_send_to_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('owner'))
            ->post(route('dashboard.customers.broadcasts.store'), ['message' => 'Hi', 'all' => '1', 'send_mode' => 'now'])
            ->assertSessionHasErrors('message');
    }

    public function test_a_stopped_send_resumes_from_pending_recipients_without_texting_anyone_twice(): void
    {
        $this->fakeSms();
        foreach (range(1, 3) as $i) {
            $this->customer('+23324000000'.$i);
        }
        $broadcast = $this->broadcasts()->schedule('Hi', ['all' => true], null, $this->userWithRole('owner'));
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
        $customer = $this->customer('+233241111111');
        $this->broadcasts()->schedule('Hi', ['all' => true], null, $this->userWithRole('owner'));
        $this->artisan('sms:send-due-broadcasts');
        $code = SmsBroadcast::sole()->recipients()->sole()->opt_out_code;

        $this->get(route('sms.opt-out.show', $code))->assertOk()->assertSee('Yes, stop these messages')
            ->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertNull($customer->fresh()->marketing_opted_out_at);

        $this->post(route('sms.opt-out.store', $code))->assertOk()->assertSee('any more promotional SMS');
        $this->assertNotNull($customer->fresh()->marketing_opted_out_at);
        $this->assertSame([], $this->broadcasts()->audience(['all' => true])['recipients']);
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

    public function test_retry_failed_texts_only_the_failed_numbers_again_with_their_own_opt_out_links(): void
    {
        $owner = $this->userWithRole('owner');
        $broadcast = $this->sentBroadcastWithTwoFailures($owner);
        $codes = $broadcast->recipients()->where('status', 'failed')->pluck('opt_out_code', 'phone');
        $this->rejecting = [];

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.retry', $broadcast))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Retrying 2 failed numbers. Sending starts within a minute.');
        $this->assertSame('sending', $broadcast->fresh()->status);
        $this->assertSame(0, $broadcast->fresh()->failed_count);

        $this->artisan('sms:send-due-broadcasts');

        $this->assertEqualsCanonicalizing(['+233242222222', '+233243333333'], array_column($this->sent, 'phone'));
        foreach ($this->sent as $sms) {
            $this->assertStringContainsString('/stop/'.$codes[$sms['phone']], $sms['message']);
        }
        $broadcast->refresh();
        $this->assertSame('sent', $broadcast->status);
        $this->assertSame(3, $broadcast->sent_count);
        $this->assertSame(0, $broadcast->failed_count);
        $this->assertSame(1, SmsBroadcast::count());
    }

    public function test_a_number_still_rejected_on_retry_stays_failed_and_can_be_retried_again(): void
    {
        $owner = $this->userWithRole('owner');
        $broadcast = $this->sentBroadcastWithTwoFailures($owner);
        $this->rejecting = ['+233243333333'];

        $this->broadcasts()->retryFailed($broadcast);
        $this->artisan('sms:send-due-broadcasts');

        $broadcast->refresh();
        $this->assertSame('sent', $broadcast->status);
        $this->assertSame(2, $broadcast->sent_count);
        $this->assertSame(1, $broadcast->failed_count);
        $this->assertSame(1, $this->broadcasts()->retryFailed($broadcast));
    }

    public function test_retry_leaves_out_anyone_who_has_opted_out_since(): void
    {
        $owner = $this->userWithRole('owner');
        $broadcast = $this->sentBroadcastWithTwoFailures($owner);
        Customer::where('phone', '+233243333333')->update(['marketing_opted_out_at' => now()]);
        $this->rejecting = [];

        $this->assertSame(1, $this->broadcasts()->retryFailed($broadcast));
        $this->artisan('sms:send-due-broadcasts');

        $this->assertSame(['+233242222222'], array_column($this->sent, 'phone'));
        $this->assertSame(1, $broadcast->fresh()->failed_count);
    }

    public function test_retry_is_refused_when_everyone_who_failed_has_opted_out(): void
    {
        $owner = $this->userWithRole('owner');
        $broadcast = $this->sentBroadcastWithTwoFailures($owner);
        Customer::whereIn('phone', ['+233242222222', '+233243333333'])->update(['marketing_opted_out_at' => now()]);

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.retry', $broadcast))
            ->assertSessionHasErrors('broadcast');

        // Rolled back: still a finished broadcast with its two failures.
        $this->assertSame('sent', $broadcast->fresh()->status);
        $this->assertSame(2, $broadcast->fresh()->failed_count);
    }

    public function test_only_a_sent_broadcast_with_failures_can_be_retried(): void
    {
        $this->fakeSms();
        $this->customer('+233241111111');
        $owner = $this->userWithRole('owner');
        $scheduled = $this->broadcasts()->schedule('Later', ['all' => true], now()->addHour(), $owner);
        $clean = $this->broadcasts()->schedule('Now', ['all' => true], null, $owner);
        $this->artisan('sms:send-due-broadcasts');
        $this->assertSame('sent', $clean->fresh()->status);

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.retry', $scheduled))->assertSessionHasErrors('broadcast');
        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.retry', $clean))->assertSessionHasErrors('broadcast');
        $this->assertSame('scheduled', $scheduled->fresh()->status);
        $this->assertSame('sent', $clean->fresh()->status);
    }

    public function test_resend_sends_the_same_message_again_to_todays_audience_with_new_opt_out_links(): void
    {
        $this->fakeSms();
        $this->customer('+233241111111');
        $owner = $this->userWithRole('owner');
        $first = $this->broadcasts()->schedule('Shawarma Wednesday!', ['all' => true], null, $owner);
        $this->artisan('sms:send-due-broadcasts');
        $this->customer('+233242222222');
        $this->sent = [];

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.resend', $first))
            ->assertRedirect(route('dashboard.customers.broadcasts.index'))
            ->assertSessionHasNoErrors();
        $this->artisan('sms:send-due-broadcasts');

        $again = SmsBroadcast::whereKeyNot($first->id)->sole();
        $this->assertSame('Shawarma Wednesday!', $again->message);
        $this->assertSame(['all' => true, 'branches' => [], 'uploaded' => false], $again->selection());
        $this->assertSame('sent', $again->status);
        $this->assertSame(2, $again->sent_count);
        $this->assertCount(2, $this->sent);
        $this->assertEmpty(array_intersect(
            $first->recipients()->pluck('opt_out_code')->all(),
            $again->recipients()->pluck('opt_out_code')->all(),
        ));
    }

    public function test_resend_keeps_the_branch_the_broadcast_went_to(): void
    {
        $this->fakeSms();
        $other = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->orderFor($this->customer('+233241111111'), $this->branch);
        $this->orderFor($this->customer('+233242222222'), $other);
        $owner = $this->userWithRole('owner');
        $first = $this->broadcasts()->schedule('Osu only', ['branches' => [$this->branch->id]], null, $owner);
        $this->artisan('sms:send-due-broadcasts');
        $this->sent = [];

        $again = $this->broadcasts()->resend($first->fresh(), $owner);
        $this->artisan('sms:send-due-broadcasts');

        $this->assertSame([$this->branch->id], $again->selection()['branches']);
        $this->assertSame(['+233241111111'], array_column($this->sent, 'phone'));
    }

    public function test_only_a_sent_broadcast_can_be_resent(): void
    {
        $this->customer('+233241111111');
        $owner = $this->userWithRole('owner');
        $scheduled = $this->broadcasts()->schedule('Later', ['all' => true], now()->addHour(), $owner);
        $cancelled = $this->broadcasts()->schedule('Oops', ['all' => true], now()->addHour(), $owner);
        $this->broadcasts()->cancel($cancelled);

        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.resend', $scheduled))->assertSessionHasErrors('broadcast');
        $this->actingAs($owner)->post(route('dashboard.customers.broadcasts.resend', $cancelled))->assertSessionHasErrors('broadcast');
        $this->assertSame(2, SmsBroadcast::count());
    }

    public function test_managers_cannot_retry_or_resend(): void
    {
        $owner = $this->userWithRole('owner');
        $broadcast = $this->sentBroadcastWithTwoFailures($owner);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->post(route('dashboard.customers.broadcasts.retry', $broadcast))->assertForbidden();
        $this->actingAs($manager)->post(route('dashboard.customers.broadcasts.resend', $broadcast))->assertForbidden();
        $this->assertSame('sent', $broadcast->fresh()->status);
        $this->assertSame(1, SmsBroadcast::count());
    }

    public function test_past_broadcasts_offer_copy_resend_and_retry_where_they_apply(): void
    {
        $owner = $this->userWithRole('owner');
        $failed = $this->sentBroadcastWithTwoFailures($owner);
        $this->broadcasts()->schedule('Later on', ['all' => true], now()->addHour(), $owner);

        $response = $this->actingAs($owner)->get(route('dashboard.customers.broadcasts.index'))->assertOk();

        $response->assertSee(route('dashboard.customers.broadcasts.retry', $failed), false);
        $response->assertSee(route('dashboard.customers.broadcasts.resend', $failed), false);
        $response->assertSee('Send this SMS again to 3 people?');
        $response->assertSee('Retry the 2 numbers this broadcast could not reach?');
        $this->assertSame(2, substr_count($response->getContent(), "\$dispatch('copy-broadcast'"));
        $this->assertSame(1, substr_count($response->getContent(), 'broadcasts/'.$failed->id.'/resend'));
    }

    private function uploadedContact(string $phone): Customer
    {
        return tap(Customer::create(['phone' => $phone]), fn (Customer $customer) => $customer->forceFill(['imported_at' => now()])->save());
    }

    public function test_all_customers_is_real_customers_and_leaves_out_uploaded_contacts(): void
    {
        $ordered = Customer::create(['phone' => '+233241111111']);
        $this->orderFor($ordered, $this->branch);
        $this->customer('+233242222222');
        $this->uploadedContact('+233243333333');

        $this->assertEqualsCanonicalizing(['+233241111111', '+233242222222'], array_values($this->broadcasts()->audience(['all' => true])['recipients']));
        $this->assertSame(['+233243333333'], array_values($this->broadcasts()->audience(['uploaded' => true])['recipients']));
        $this->assertSame([], $this->broadcasts()->audience([])['recipients']);
    }

    public function test_an_uploaded_contact_who_later_orders_is_on_both_lists(): void
    {
        $contact = $this->uploadedContact('+233243333333');
        $this->orderFor($contact, $this->branch);

        $this->assertSame([$contact->id => '+233243333333'], $this->broadcasts()->audience(['all' => true])['recipients']);
        $this->assertSame([$contact->id => '+233243333333'], $this->broadcasts()->audience(['uploaded' => true])['recipients']);
        $this->assertSame([$contact->id => '+233243333333'], $this->broadcasts()->audience(['branches' => [$this->branch->id]])['recipients']);
    }

    public function test_several_ticked_lists_send_one_sms_per_number(): void
    {
        $this->fakeSms();
        $other = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $osu = Customer::create(['phone' => '+233241111111']);
        $this->orderFor($osu, $this->branch);
        $both = $this->uploadedContact('+233242222222');
        $this->orderFor($both, $this->branch);
        $this->uploadedContact('+233243333333');
        $this->orderFor(Customer::create(['phone' => '+233244444444']), $other);

        $this->actingAs($this->userWithRole('owner'))->post(route('dashboard.customers.broadcasts.store'), [
            'message' => 'Osu and uploads', 'branches' => [$this->branch->id], 'uploaded' => '1', 'send_mode' => 'now',
        ])->assertSessionHasNoErrors();
        $this->artisan('sms:send-due-broadcasts');

        $broadcast = SmsBroadcast::sole();
        $this->assertSame(['all' => false, 'branches' => [$this->branch->id], 'uploaded' => true], $broadcast->selection());
        $this->assertNull($broadcast->branch_id);
        $this->assertEqualsCanonicalizing(['+233241111111', '+233242222222', '+233243333333'], array_column($this->sent, 'phone'));
    }

    public function test_a_broadcast_needs_at_least_one_list_ticked(): void
    {
        $this->customer('+233241111111');

        $this->actingAs($this->userWithRole('owner'))
            ->post(route('dashboard.customers.broadcasts.store'), ['message' => 'Hi', 'send_mode' => 'now'])
            ->assertSessionHasErrors('send_to');

        $this->assertSame(0, SmsBroadcast::count());
    }

    public function test_the_form_counts_ticked_lists_together_with_duplicates_removed(): void
    {
        $this->customer('+233241111111');
        $this->customer('+233242222222');
        $this->uploadedContact('+233243333333');
        $this->uploadedContact('0241111111'); // the same number as the first customer

        $this->actingAs($this->userWithRole('owner'))
            ->getJson(route('dashboard.customers.broadcasts.audience', ['all' => 1, 'uploaded' => 1]))
            ->assertOk()
            ->assertJson(['recipients' => 3, 'duplicates' => 1]);

        $this->actingAs($this->userWithRole('manager'))
            ->getJson(route('dashboard.customers.broadcasts.audience', ['all' => 1]))
            ->assertForbidden();
    }

    public function test_a_broadcast_scheduled_before_the_lists_still_reaches_uploaded_contacts(): void
    {
        $this->fakeSms();
        $this->customer('+233241111111');
        $this->uploadedContact('+233243333333');
        $owner = $this->userWithRole('owner');
        SmsBroadcast::create(['message' => 'Old', 'branch_id' => null, 'status' => SmsBroadcast::STATUS_SCHEDULED, 'scheduled_at' => now(), 'created_by' => $owner->id]);

        $this->artisan('sms:send-due-broadcasts');

        $this->assertEqualsCanonicalizing(['+233241111111', '+233243333333'], array_column($this->sent, 'phone'));
    }

    public function test_resend_and_copy_keep_the_ticked_lists(): void
    {
        $this->fakeSms();
        $this->customer('+233241111111');
        $this->uploadedContact('+233243333333');
        $owner = $this->userWithRole('owner');
        $first = $this->broadcasts()->schedule('Both', ['all' => true, 'uploaded' => true], null, $owner);
        $this->artisan('sms:send-due-broadcasts');

        $page = $this->actingAs($owner)->get(route('dashboard.customers.broadcasts.index'))->assertOk();
        $page->assertSee('All customers + Uploaded contacts');
        $page->assertSee('Send this SMS again to 2 people?');

        $again = $this->broadcasts()->resend($first->fresh(), $owner);
        $this->assertSame(['all' => true, 'branches' => [], 'uploaded' => true], $again->selection());
    }

    public function test_the_migration_files_earlier_uploads_as_uploaded_contacts(): void
    {
        $lead = Customer::create(['phone' => '+233243333333']);
        $ordered = Customer::create(['phone' => '+233241111111']);
        $this->orderFor($ordered, $this->branch);
        $registered = $this->customer('+233242222222');
        $walkIn = Customer::create(['phone' => null]);

        Schema::table('sms_broadcasts', fn ($table) => $table->dropColumn('audience'));
        Schema::table('customers', fn ($table) => $table->dropColumn('imported_at'));
        (require database_path('migrations/2026_11_20_150000_add_sms_send_to_lists.php'))->up();

        $this->assertNotNull($lead->fresh()->imported_at);
        $this->assertNull($ordered->fresh()->imported_at);
        $this->assertNull($registered->fresh()->imported_at);
        $this->assertNull($walkIn->fresh()->imported_at);
    }
}
