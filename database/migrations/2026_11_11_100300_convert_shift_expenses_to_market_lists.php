<?php

use App\Services\MarketLists\ExpenseKindMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data only: every shift's expenses from before the market list existed
     * become one approved market list each, kinds suggested from the
     * description. Reports now read the market list alone, so without this
     * past months would suddenly lose their expenses. Skips any shift that
     * already has a list, so it's safe to re-run. shift_expenses rows stay
     * exactly as they were.
     */
    public function up(): void
    {
        $kinds = app(ExpenseKindMatcher::class);

        DB::table('shifts')
            ->whereExists(fn ($query) => $query->from('shift_expenses')->whereColumn('shift_expenses.shift_id', 'shifts.id'))
            ->whereNotExists(fn ($query) => $query->from('market_lists')->whereColumn('market_lists.shift_id', 'shifts.id'))
            ->orderBy('id')
            ->each(function (object $shift) use ($kinds) {
                $expenses = DB::table('shift_expenses')->where('shift_id', $shift->id)->orderBy('id')->get();
                $recordedAt = Carbon::parse($expenses->first()->created_at, 'UTC');

                $listId = DB::table('market_lists')->insertGetId([
                    'branch_id' => $shift->branch_id,
                    'purchased_on' => $recordedAt->clone()->timezone('Africa/Accra')->toDateString(),
                    'recorded_by' => $shift->ended_by_user_id ?? $shift->user_id,
                    'recorder_role' => 'staff',
                    'shift_id' => $shift->id,
                    'status' => 'approved',
                    'total' => (int) $expenses->sum('amount'),
                    'note' => 'Entered when ending the shift.',
                    'reviewed_at' => now(),
                    'review_note' => 'Recorded before market list approvals existed, counted as approved.',
                    'created_at' => $recordedAt,
                    'updated_at' => now(),
                ]);

                foreach ($expenses as $expense) {
                    DB::table('market_list_items')->insert([
                        'market_list_id' => $listId,
                        'expense_category_id' => $kinds->match($expense->description)->id,
                        'description' => $expense->description,
                        'amount' => $expense->amount,
                        'created_at' => $expense->created_at,
                        'updated_at' => now(),
                    ]);
                }

                DB::table('market_list_events')->insert([
                    'market_list_id' => $listId,
                    'actor_id' => null,
                    'action' => 'converted',
                    'note' => 'Converted from shift expenses.',
                    'created_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
