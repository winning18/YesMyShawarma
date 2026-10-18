<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\ShiftExpense;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpensesReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $main;

    private Branch $pokuase;

    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->main = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233243635265',
            'address' => 'Adjacent Star Oil, Odumase', 'ghanapost_code' => 'GW-0286-6918',
            'lat' => 5.67, 'lng' => -0.30, 'opens_at' => '14:00', 'closes_at' => '23:59',
        ]);
        $this->pokuase = Branch::create([
            'name' => 'Pokuase Y-Junction', 'slug' => 'pokuase-y-junction', 'phone' => '+233531907747',
            'address' => 'Footbridge, Nsawam Side, Nsawam Road',
            'lat' => 5.69, 'lng' => -0.29, 'opens_at' => '19:00', 'closes_at' => '23:59',
        ]);

        $this->monday = Carbon::parse('2026-10-05', 'Africa/Accra')->startOfDay();
    }

    private function userWithRole(string $role, Branch $branch, string $name = 'Someone'): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function expense(Branch $branch, Carbon $at, int $amount, string $description, ?User $closedBy = null): ShiftExpense
    {
        $closedBy ??= User::factory()->create(['name' => 'Ama']);
        $shift = Shift::create([
            'user_id' => $closedBy->id, 'branch_id' => $branch->id, 'started_at' => $at->clone()->subHours(2),
            'ended_at' => $at, 'ended_by_user_id' => $closedBy->id,
        ]);
        $expense = ShiftExpense::create(['shift_id' => $shift->id, 'description' => $description, 'amount' => $amount]);
        $expense->created_at = $at;
        $expense->save();

        return $expense;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function page(User $user, array $params = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->get(route('dashboard.reports.expenses.index', [
            'from' => '2026-10-05', 'to' => '2026-10-11', ...$params,
        ]))->assertOk();
    }

    public function test_the_expenses_tab_appears_for_managers_and_lists_expenses_newest_first(): void
    {
        $manager = $this->userWithRole('manager', $this->main);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 12000, 'Gas refill');
        $this->expense($this->main, $this->monday->clone()->addDays(2)->setTime(15, 0), 4500, 'Charcoal');

        $response = $this->page($manager)
            ->assertSee('Expenses')
            ->assertSee('Gas refill')
            ->assertSee('GH₵165.00');

        $rows = $response->viewData('expenses')->items();
        $this->assertSame(['Charcoal', 'Gas refill'], array_column($rows, 'description'));
        $this->assertSame(['total' => 16500, 'count' => 2, 'shifts_count' => 2], $response->viewData('summary'));
    }

    public function test_staff_cannot_see_expenses(): void
    {
        $staff = $this->userWithRole('staff', $this->main);

        $this->actingAs($staff)->get(route('dashboard.reports.expenses.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('dashboard.reports.expenses.download', ['format' => 'csv']))->assertForbidden();
    }

    public function test_only_expenses_inside_the_date_range_are_listed(): void
    {
        $owner = $this->userWithRole('owner', $this->main);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 1000, 'Inside');
        $this->expense($this->main, $this->monday->clone()->subDay()->setTime(15, 0), 2000, 'Before');
        $this->expense($this->main, $this->monday->clone()->addWeek()->setTime(15, 0), 3000, 'After');

        $summary = $this->page($owner)->viewData('summary');

        $this->assertSame(1000, $summary['total']);
        $this->assertSame(1, $summary['count']);
    }

    public function test_search_matches_the_description_case_insensitively(): void
    {
        $owner = $this->userWithRole('owner', $this->main);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 12000, 'Gas refill');
        $this->expense($this->main, $this->monday->clone()->setTime(16, 0), 3000, 'LPG gas top-up');
        $this->expense($this->main, $this->monday->clone()->setTime(17, 0), 4500, 'Charcoal');

        $summary = $this->page($owner, ['search' => 'GAS'])->viewData('summary');

        $this->assertSame(15000, $summary['total']);
        $this->assertSame(2, $summary['count']);
    }

    public function test_recorded_by_filters_to_who_closed_the_shift(): void
    {
        $owner = $this->userWithRole('owner', $this->main);
        $ama = User::factory()->create(['name' => 'Ama']);
        $kofi = User::factory()->create(['name' => 'Kofi']);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 1000, 'Bread', $ama);
        $this->expense($this->main, $this->monday->clone()->setTime(16, 0), 2000, 'Onions', $kofi);

        $response = $this->page($owner, ['recorded_by' => $kofi->id]);

        $this->assertSame(['Onions'], array_column($response->viewData('expenses')->items(), 'description'));
        $this->assertSame('Kofi', $response->viewData('expenses')->items()[0]['recorded_by']);
        $this->assertSame(['Ama', 'Kofi'], $response->viewData('recordedByOptions')->pluck('name')->all());
    }

    public function test_amount_range_is_in_cedis_and_a_reversed_range_is_swapped(): void
    {
        $owner = $this->userWithRole('owner', $this->main);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 1500, 'Small');
        $this->expense($this->main, $this->monday->clone()->setTime(16, 0), 5000, 'Medium');
        $this->expense($this->main, $this->monday->clone()->setTime(17, 0), 20000, 'Large');

        $range = $this->page($owner, ['min_amount' => '20', 'max_amount' => '100'])->viewData('summary');
        $reversed = $this->page($owner, ['min_amount' => '100', 'max_amount' => '20'])->viewData('summary');

        $this->assertSame(5000, $range['total']);
        $this->assertSame(5000, $reversed['total']);
    }

    public function test_owner_sees_every_branch_by_default_and_can_pick_one(): void
    {
        $owner = $this->userWithRole('owner', $this->main);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 1000, 'Main');
        $this->expense($this->pokuase, $this->monday->clone()->setTime(15, 0), 2000, 'Pokuase');

        $this->assertSame(3000, $this->page($owner)->viewData('summary')['total']);
        $this->assertSame(2000, $this->page($owner, ['branch' => $this->pokuase->id])->viewData('summary')['total']);
    }

    public function test_a_manager_only_ever_sees_their_own_branch(): void
    {
        $manager = $this->userWithRole('manager', $this->main);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 1000, 'Main');
        $this->expense($this->pokuase, $this->monday->clone()->setTime(15, 0), 2000, 'Pokuase');

        $response = $this->page($manager, ['branch' => $this->pokuase->id]);

        $this->assertSame(1000, $response->viewData('summary')['total']);
        $response->assertDontSee('Pokuase');
    }

    public function test_totals_cover_every_page_not_just_the_one_shown(): void
    {
        $owner = $this->userWithRole('owner', $this->main);
        for ($i = 0; $i < 30; $i++) {
            $this->expense($this->main, $this->monday->clone()->setTime(12, $i), 100, 'Item '.$i);
        }

        $response = $this->page($owner);

        $this->assertCount(25, $response->viewData('expenses')->items());
        $this->assertSame(3000, $response->viewData('summary')['total']);
        $this->assertSame(30, $response->viewData('summary')['count']);
    }

    public function test_csv_groups_by_day_with_totals_and_notes_the_filters(): void
    {
        $owner = $this->userWithRole('owner', $this->main);
        $kofi = User::factory()->create(['name' => 'Kofi']);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 12000, 'Gas refill', $kofi);
        $this->expense($this->main, $this->monday->clone()->setTime(18, 30), 3000, 'Gas top-up', $kofi);
        $this->expense($this->main, $this->monday->clone()->addDay()->setTime(15, 0), 4500, 'Gas again', $kofi);
        $this->expense($this->main, $this->monday->clone()->addDay()->setTime(16, 0), 999, 'Charcoal', $kofi);

        $response = $this->actingAs($owner)->get(route('dashboard.reports.expenses.download', [
            'format' => 'csv', 'branch' => $this->main->id, 'from' => '2026-10-05', 'to' => '2026-10-11', 'search' => 'gas',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            '2026-10-05 -- 2026-10-11 '.config('app.name').' Expenses Report - Ga Odumase.csv',
            $response->headers->get('content-disposition'),
        );

        $rows = array_map('str_getcsv', explode("\n", trim($response->getContent())));
        $this->assertSame(['Date', 'Time', 'Recorded by', 'Description', 'Amount (ghs)'], $rows[0]);
        $this->assertSame(['2026-10-05', '15:00', 'Kofi', 'Gas refill', '120.00'], $rows[1]);
        $this->assertSame(['2026-10-05', '', '', 'TOTAL', '150.00'], $rows[3]);

        $flat = collect($rows)->map(fn ($row) => implode('|', array_filter($row, fn ($cell) => $cell !== '')))->all();
        $this->assertContains('2026-10-06|TOTAL|45.00', $flat);
        $this->assertContains('Total expenses|195.00', $flat);
        $this->assertContains('Entries|3', $flat);
        $this->assertContains('Filtered by|Description contains "gas"', $flat);
        $this->assertNotContains('Charcoal', collect($rows)->flatten()->all());
    }

    public function test_xlsx_has_the_report_header_and_numeric_amounts(): void
    {
        $owner = $this->userWithRole('owner', $this->main);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 12000, 'Gas refill');

        $response = $this->actingAs($owner)->get(route('dashboard.reports.expenses.download', [
            'format' => 'xlsx', 'from' => '2026-10-05', 'to' => '2026-10-11',
        ]));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame('Expenses report', $sheet->getCell('A1')->getValue());
        $this->assertStringContainsString('Adjacent Star Oil, Odumase · GW-0286-6918', $sheet->getCell('A4')->getValue());
        // General report: Date, Time, Branch, Recorded by, Description, Amount — headings row 8.
        $this->assertSame('Amount (ghs)', $sheet->getCell('F8')->getValue());
        $this->assertEquals(120, $sheet->getCell('F9')->getValue());
    }

    public function test_pdf_uses_the_shared_report_design(): void
    {
        $owner = $this->userWithRole('owner', $this->main);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 12000, 'Gas refill');

        $response = $this->actingAs($owner)->get(route('dashboard.reports.expenses.download', [
            'format' => 'pdf', 'from' => '2026-10-05', 'to' => '2026-10-11',
        ]));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $report = app(\App\Services\Reports\ExpenseReportService::class)
            ->build($this->monday, $this->monday->clone()->endOfWeek(), ['search' => 'gas'], ignoreBranchScope: true);
        $html = view('dashboard.reports.pdf.expenses', [
            'report' => $report,
            'columns' => app(\App\Services\Reports\ExpenseReportExporter::class)->columns($report),
            'address' => 'Adjacent Star Oil, Odumase · GW-0286-6918',
            'logoPath' => public_path('images/logo-web.png'),
            'fontDir' => resource_path('fonts/figtree'),
        ])->render();

        $this->assertStringContainsString(public_path('images/logo-web.png'), $html);
        $this->assertStringContainsString('Expenses report', $html);
        $this->assertStringContainsString('Period of report: 2026-10-05 - 2026-10-11', $html);
        $this->assertStringContainsString('Filtered by: Description contains &quot;gas&quot;', $html);
        $this->assertStringContainsString('Total expenses', $html);
        $this->assertStringContainsString('#9a0200', $html);
    }

    public function test_an_unknown_format_is_rejected(): void
    {
        $owner = $this->userWithRole('owner', $this->main);

        $this->actingAs($owner)->get(route('dashboard.reports.expenses.download', ['format' => 'docx']))->assertNotFound();
    }
}
