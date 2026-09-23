<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PushSubscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function makeStaff(): User
    {
        $staff = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $staff->assignRole('staff');

        return $staff;
    }

    private function subscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'p256dh-value', 'auth' => 'auth-value'],
        ];
    }

    public function test_staff_can_register_a_push_subscription(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->postJson(route('push.subscribe'), $this->subscriptionPayload())
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $staff->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'public_key' => 'p256dh-value',
            'auth_token' => 'auth-value',
        ]);
    }

    public function test_resubscribing_the_same_endpoint_updates_the_row_instead_of_duplicating(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->postJson(route('push.subscribe'), $this->subscriptionPayload())->assertOk();
        $this->actingAs($staff)->postJson(route('push.subscribe'), $this->subscriptionPayload())->assertOk();

        $this->assertSame(1, PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/abc123')->count());
    }

    public function test_a_second_device_for_the_same_staff_member_gets_its_own_row(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->postJson(route('push.subscribe'), $this->subscriptionPayload('https://fcm.googleapis.com/fcm/send/device-1'))->assertOk();
        $this->actingAs($staff)->postJson(route('push.subscribe'), $this->subscriptionPayload('https://fcm.googleapis.com/fcm/send/device-2'))->assertOk();

        $this->assertSame(2, PushSubscription::where('user_id', $staff->id)->count());
    }

    public function test_staff_can_unsubscribe(): void
    {
        $staff = $this->makeStaff();
        $subscription = PushSubscription::create([
            'user_id' => $staff->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'public_key' => 'p256dh-value',
            'auth_token' => 'auth-value',
        ]);

        $this->actingAs($staff)->deleteJson(route('push.unsubscribe'), ['endpoint' => $subscription->endpoint])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_unsubscribing_never_removes_another_users_subscription(): void
    {
        $staff = $this->makeStaff();
        $otherStaff = $this->makeStaff();
        $othersSubscription = PushSubscription::create([
            'user_id' => $otherStaff->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/someone-elses',
            'public_key' => 'p256dh-value',
            'auth_token' => 'auth-value',
        ]);

        $this->actingAs($staff)->deleteJson(route('push.unsubscribe'), ['endpoint' => $othersSubscription->endpoint])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $othersSubscription->id]);
    }

    public function test_a_guest_cannot_subscribe(): void
    {
        $this->postJson(route('push.subscribe'), $this->subscriptionPayload())->assertUnauthorized();
    }
}
