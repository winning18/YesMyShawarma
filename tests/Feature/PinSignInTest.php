<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Setting;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Settings\BusinessProfile;
use App\Services\Shifts\CounterService;
use App\Services\Users\PinService;
use App\Services\Users\StaffCodeService;
use App\Services\Users\UserManagementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 7b: till staff sign in at a counter with a Staff ID and
 * a PIN; only on a set-up counter, only at its branch.
 */
class PinSignInTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Terminal $counter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::create(['key' => BusinessProfile::NAME, 'value' => 'Mama Put Kitchen']);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $this->counter = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 1']);
    }

    /** Made the way the Users page makes them (so till staff get a Staff ID), password already changed. */
    private function makeUser(string $role, ?Branch $branch = null): User
    {
        $user = app(UserManagementService::class)->create(
            ['name' => ucfirst($role).' '.uniqid(), 'email' => uniqid().'@example.test'], $role, ($branch ?? $this->branch)->id,
        )['user'];
        $user->forceFill(['must_change_password' => false])->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId(($branch ?? $this->branch)->id);

        return $user;
    }

    private function onCounter(): static
    {
        return $this->withCredentials()->withCookie(CounterService::COOKIE, app(CounterService::class)->pair($this->counter)->getValue());
    }

    public function test_till_staff_get_a_staff_id_from_the_business_initials(): void
    {
        $this->assertSame('MPK', app(StaffCodeService::class)->prefix());

        $first = $this->makeUser('staff');
        $second = $this->makeUser('staff');
        $manager = $this->makeUser('manager');

        $this->assertSame(['MPK-0001', 'MPK-0002'], [$first->fresh()->staff_code, $second->fresh()->staff_code]);
        $this->assertNull($manager->fresh()->staff_code);
        $this->assertSame('MPK-0042', StaffCodeService::normalize(' mpk42 '));
    }

    public function test_a_manager_sets_a_pin_and_staff_sign_in_with_it_at_a_counter(): void
    {
        $staff = $this->makeUser('staff');
        $manager = $this->makeUser('manager');

        $this->actingAs($manager)->withSession(['current_branch_id' => $this->branch->id])
            ->put(route('dashboard.users.pin.set', $staff), ['pin' => '4821', 'pin_confirmation' => '4821'])
            ->assertSessionHas('status');
        $this->assertNotSame('4821', $staff->fresh()->pin, 'stored hashed');
        auth()->logout();

        $this->onCounter()->get(route('login'))->assertOk()->assertSee('Staff ID')->assertSee('Open till')->assertSee('Counter 1');

        $this->onCounter()->post(route('login.pin'), ['staff_code' => 'mpk-0001', 'pin' => '4821'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($staff->fresh());
        $this->assertSame($this->branch->id, session('current_branch_id'));
    }

    public function test_pin_sign_in_only_works_on_a_counter_of_the_staff_members_branch(): void
    {
        $staff = $this->makeUser('staff');
        app(PinService::class)->set($staff, '4821');

        // Not a counter: no PIN form, and the PIN route refuses.
        $this->get(route('login'))->assertDontSee('Staff ID');
        $this->post(route('login.pin'), ['staff_code' => $staff->fresh()->staff_code, 'pin' => '4821'])->assertNotFound();
        $this->assertGuest();

        // A counter at another branch.
        $elsewhere = Branch::create([
            'name' => 'Labone', 'slug' => 'labone', 'phone' => '+233241113333', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $this->counter = Terminal::create(['branch_id' => $elsewhere->id, 'name' => 'Counter 1']);
        $this->onCounter()->post(route('login.pin'), ['staff_code' => $staff->fresh()->staff_code, 'pin' => '4821'])->assertSessionHasErrors('staff_code');
        $this->assertGuest();
    }

    public function test_managers_cant_use_a_pin(): void
    {
        $manager = $this->makeUser('manager');
        app(StaffCodeService::class)->ensureFor($manager);
        $manager->forceFill(['pin' => bcrypt('4821')])->save();

        $this->onCounter()->post(route('login.pin'), ['staff_code' => $manager->staff_code, 'pin' => '4821'])->assertSessionHasErrors('staff_code');
        $this->assertGuest();
        $this->actingAs($this->makeUser('owner'))->withSession(['current_branch_id' => $this->branch->id])
            ->put(route('dashboard.users.pin.set', $manager), ['pin' => '1111', 'pin_confirmation' => '1111'])->assertStatus(422);
    }

    public function test_five_wrong_pins_lock_the_staff_id_for_fifteen_minutes(): void
    {
        $staff = $this->makeUser('staff');
        app(PinService::class)->set($staff, '4821');
        $code = $staff->fresh()->staff_code;

        foreach (range(1, 5) as $try) {
            $this->onCounter()->post(route('login.pin'), ['staff_code' => $code, 'pin' => '0000'])->assertSessionHasErrors('staff_code');
        }

        $this->onCounter()->post(route('login.pin'), ['staff_code' => $code, 'pin' => '4821'])
            ->assertSessionHasErrors(['staff_code' => __('Too many tries. Try again in :minutes minutes, or ask a manager.', ['minutes' => 15])]);
        $this->assertGuest();
    }

    public function test_a_pin_sign_in_skips_the_temporary_password_change_but_email_does_not(): void
    {
        $result = app(UserManagementService::class)->create(['name' => 'New Staff', 'email' => 'new@example.test'], 'staff', $this->branch->id);
        $staff = $result['user'];
        app(PinService::class)->set($staff, '4821');

        $this->onCounter()->post(route('login.pin'), ['staff_code' => $staff->fresh()->staff_code, 'pin' => '4821']);
        $this->get(route('dashboard'))->assertOk();

        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'new@example.test', 'password' => $result['temporary_password']]);
        $this->get(route('dashboard'))->assertRedirect(route('password.force-change'));
    }
}
