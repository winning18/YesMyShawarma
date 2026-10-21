<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Services\Sms\SmsBroadcastService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerImportTest extends TestCase
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

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function upload(string $csv, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->userWithRole('owner'))
            ->post(route('dashboard.customers.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
            ]);
    }

    public function test_imports_new_contacts_and_reports_what_was_skipped(): void
    {
        $this->upload("Name,Phone\nAma Mensah,0241234567\nKofi,+233 20 123 4567\nAma again,233241234567\nBad,12345\n,\n")
            ->assertRedirect(route('dashboard.customers.import.create'))
            ->assertSessionHas('importResult', fn (array $r) => $r['added'] === 2 && $r['duplicates'] === 1 && $r['invalid'] === 1
                && $r['invalid_rows'] === [['line' => 5, 'value' => '12345']]);

        $this->assertSame('Ama Mensah', Customer::where('phone', '+233241234567')->value('name'));
        $this->assertSame('Kofi', Customer::where('phone', '+233201234567')->value('name'));
    }

    public function test_existing_customers_are_never_overwritten_but_a_missing_name_is_filled(): void
    {
        $named = Customer::create(['phone' => '+233241111111', 'name' => 'Real Name']);
        $nameless = Customer::create(['phone' => '+233242222222']);
        $optedOut = Customer::create(['phone' => '+233243333333', 'marketing_opted_out_at' => now()]);

        $this->upload("Phone,Name\n0241111111,Lead Name\n0242222222,Esi\n0243333333,Yaw\n")
            ->assertSessionHas('importResult', fn (array $r) => $r['added'] === 0 && $r['existing'] === 3 && $r['names_filled'] === 2);

        $this->assertSame('Real Name', $named->fresh()->name);
        $this->assertSame('Esi', $nameless->fresh()->name);
        $this->assertNotNull($optedOut->fresh()->marketing_opted_out_at);
        $this->assertSame(3, Customer::count());
    }

    public function test_reads_numbers_that_lost_their_leading_zero_and_files_with_no_header(): void
    {
        $this->upload("241234567,Ama\n0201234567,Kofi\n")
            ->assertSessionHas('importResult', fn (array $r) => $r['added'] === 2);

        $this->assertSame('Ama', Customer::where('phone', '+233241234567')->value('name'));
    }

    public function test_reads_semicolon_files_with_a_bom_and_a_contact_name_header(): void
    {
        $this->upload("\xEF\xBB\xBFContact name;Mobile number\nAma;0241234567\n")
            ->assertSessionHas('importResult', fn (array $r) => $r['added'] === 1);

        $this->assertSame('Ama', Customer::where('phone', '+233241234567')->value('name'));
    }

    public function test_imported_leads_join_all_customer_broadcasts(): void
    {
        $this->upload("Phone\n0241234567\n");

        $this->assertSame(['+233241234567'], array_values(app(SmsBroadcastService::class)->audience(null)['recipients']));
    }

    public function test_only_owner_and_general_manager_can_import(): void
    {
        $this->actingAs($this->userWithRole('general_manager'))->get(route('dashboard.customers.import.create'))->assertOk();
        $this->actingAs($this->userWithRole('manager'))->get(route('dashboard.customers.import.create'))->assertForbidden();
        $this->upload("Phone\n0241234567\n", $this->userWithRole('manager'))->assertForbidden();
        $this->assertSame(0, Customer::count());
    }

    public function test_customer_list_shows_the_import_button_only_to_those_who_can_import(): void
    {
        $this->actingAs($this->userWithRole('owner'))->get(route('dashboard.customers.index'))->assertSee('Import contacts');
        $this->actingAs($this->userWithRole('manager'))->get(route('dashboard.customers.index'))->assertDontSee('Import contacts');
    }

    public function test_a_non_csv_file_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('owner'))
            ->post(route('dashboard.customers.import.store'), ['file' => UploadedFile::fake()->create('leads.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
    }
}
