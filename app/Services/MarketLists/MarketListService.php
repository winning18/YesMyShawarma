<?php

namespace App\Services\MarketLists;

use App\Exceptions\MarketListException;
use App\Models\ExpenseCategory;
use App\Models\MarketList;
use App\Models\MarketListEvent;
use App\Models\Scopes\BranchScope;
use App\Models\Shift;
use App\Models\User;
use App\Services\Branches\BranchContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The market list: every purchase and expense, recorded by staff (or
 * fed in from the expenses entered at shift close), approved before it
 * counts (owner, 2026-11-11).
 *
 *  - staff / stock manager entries: approved by owner, GM or manager
 *  - manager / GM entries: approved by the owner only
 *  - the owner's own entries: approved automatically
 *
 * Nobody approves their own. An approver can correct kinds while
 * approving, or send the list back with a reason; the recorder then edits
 * and resubmits. Once approved a list is locked. Every step writes a
 * market_list_events row.
 *
 * Receipt photos live on the private 'local' disk, like damage photos,
 * but are kept permanently — they're financial records.
 */
class MarketListService
{
    private const DISK = 'local';

    private const DIRECTORY = 'market-lists';

    /**
     * Roles whose entries only the owner may approve.
     */
    public const OWNER_APPROVES_ROLES = ['manager', 'general_manager'];

    public function __construct(
        private readonly BranchContext $context,
        private readonly ExpenseKindMatcher $kinds,
    ) {}

    /**
     * $items: list of ['description' => string, 'amount' => int pesewas,
     * 'quantity' => ?float, 'unit' => ?string, 'expense_category_id' => ?int].
     * A missing or unknown kind falls back to the matcher's suggestion.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function record(User $recorder, int $branchId, string $purchasedOn, array $items, ?UploadedFile $receipt = null, ?string $note = null, ?Shift $shift = null): MarketList
    {
        $items = $this->cleanItems($items);
        $role = $this->context->primaryRoleFor($recorder, $branchId);
        $autoApproved = $role === 'owner';

        return DB::transaction(function () use ($recorder, $branchId, $purchasedOn, $items, $receipt, $note, $shift, $role, $autoApproved) {
            $list = MarketList::create([
                'branch_id' => $branchId,
                'purchased_on' => $purchasedOn,
                'recorded_by' => $recorder->id,
                'recorder_role' => $role,
                'shift_id' => $shift?->id,
                'status' => $autoApproved ? MarketList::STATUS_APPROVED : MarketList::STATUS_PENDING,
                'total' => array_sum(array_column($items, 'amount')),
                'receipt_path' => $receipt ? $this->storeReceipt($receipt) : null,
                'note' => $note,
                'reviewed_by' => $autoApproved ? $recorder->id : null,
                'reviewed_at' => $autoApproved ? now() : null,
            ]);

            $list->items()->createMany($items);
            $this->log($list, $recorder, MarketListEvent::SUBMITTED);

            if ($autoApproved) {
                $this->log($list, $recorder, MarketListEvent::AUTO_APPROVED);
            }

            return $list;
        });
    }

    /**
     * The expenses typed in when a shift ends land here as a pending list
     * recorded by whoever ended it (owner, 2026-11-11: "keep both, feed
     * in") — shift_expenses itself is untouched, it still drives the
     * Today report's per-shift Net.
     *
     * @param  list<array{description: string, amount: int}>  $expenses
     */
    public function recordFromShift(Shift $shift, User $endedBy, array $expenses): ?MarketList
    {
        if ($expenses === []) {
            return null;
        }

        return $this->record(
            recorder: $endedBy,
            branchId: $shift->branch_id,
            purchasedOn: now()->timezone('Africa/Accra')->toDateString(),
            items: array_map(fn (array $expense) => ['description' => $expense['description'], 'amount' => $expense['amount']], $expenses),
            note: __('Entered when ending the shift.'),
            shift: $shift,
        );
    }

    /**
     * The recorder fixing their own list (pending or sent back), or an
     * approver tidying a pending one before approving it. A sent-back list
     * edited by its recorder goes back to pending.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function update(MarketList $list, User $editor, string $purchasedOn, array $items, ?UploadedFile $receipt = null, bool $removeReceipt = false, ?string $note = null): MarketList
    {
        if (! $list->isEditable()) {
            throw MarketListException::alreadyApproved();
        }

        $items = $this->cleanItems($items);

        return DB::transaction(function () use ($list, $editor, $purchasedOn, $items, $receipt, $removeReceipt, $note) {
            $resubmitting = $list->status === MarketList::STATUS_NEEDS_CHANGES && $editor->id === $list->recorded_by;
            $receiptPath = $list->receipt_path;

            if (($receipt || $removeReceipt) && $receiptPath) {
                Storage::disk(self::DISK)->delete($receiptPath);
                $receiptPath = null;
            }
            if ($receipt) {
                $receiptPath = $this->storeReceipt($receipt);
            }

            $list->items()->delete();
            $list->items()->createMany($items);
            $list->update([
                'purchased_on' => $purchasedOn,
                'total' => array_sum(array_column($items, 'amount')),
                'receipt_path' => $receiptPath,
                'note' => $note,
                'status' => $resubmitting ? MarketList::STATUS_PENDING : $list->status,
            ]);

            $this->log($list, $editor, $resubmitting ? MarketListEvent::RESUBMITTED : MarketListEvent::EDITED);

            return $list->fresh('items');
        });
    }

    /**
     * $kindCorrections: market_list_item id => expense_category id, for
     * the approver fixing a wrong suggestion as part of approving.
     *
     * @param  array<int, int>  $kindCorrections
     */
    public function approve(MarketList $list, User $reviewer, ?string $note = null, array $kindCorrections = []): MarketList
    {
        if ($list->status !== MarketList::STATUS_PENDING) {
            throw MarketListException::notPending();
        }

        return DB::transaction(function () use ($list, $reviewer, $note, $kindCorrections) {
            $validKinds = ExpenseCategory::pluck('id')->all();
            $corrected = 0;

            foreach ($list->items as $item) {
                $kindId = $kindCorrections[$item->id] ?? null;

                if ($kindId && in_array((int) $kindId, $validKinds, true) && (int) $kindId !== $item->expense_category_id) {
                    $item->update(['expense_category_id' => (int) $kindId]);
                    $corrected++;
                }
            }

            $list->update([
                'status' => MarketList::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $this->log($list, $reviewer, MarketListEvent::APPROVED, trim(($corrected ? __(':count kind(s) corrected.', ['count' => $corrected]).' ' : '').($note ?? '')) ?: null);

            return $list->fresh();
        });
    }

    public function sendBack(MarketList $list, User $reviewer, string $reason): MarketList
    {
        if ($list->status !== MarketList::STATUS_PENDING) {
            throw MarketListException::notPending();
        }

        $list->update([
            'status' => MarketList::STATUS_NEEDS_CHANGES,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $reason,
        ]);

        $this->log($list, $reviewer, MarketListEvent::SENT_BACK, $reason);

        return $list->fresh();
    }

    /**
     * Who may approve $list, before branch permission is even checked:
     * never the recorder, and only the owner for a manager's or GM's list.
     */
    public function approverTierAllows(User $user, MarketList $list): bool
    {
        if ($user->id === $list->recorded_by) {
            return false;
        }

        if (in_array($list->recorder_role, self::OWNER_APPROVES_ROLES, true)) {
            return $this->context->hasRoleAtAnyBranch($user, 'owner');
        }

        return true;
    }

    /**
     * How many pending lists $user could approve right now — the sidebar
     * badge. Same scope as the Market list page: owner every branch, GM
     * their GM branches, manager the current branch.
     */
    public function awaitingApprovalCount(User $user): int
    {
        $isOwner = $this->context->hasRoleAtAnyBranch($user, 'owner');

        if (! $isOwner && ! $user->can('market_lists.approve') && ! $this->context->hasRoleAtAnyBranch($user, 'general_manager')) {
            return 0;
        }

        $query = MarketList::withoutGlobalScope(BranchScope::class)
            ->where('status', MarketList::STATUS_PENDING)
            ->where('recorded_by', '!=', $user->id);

        if ($isOwner) {
            return $query->count();
        }

        $branchIds = $this->context->hasRoleAtAnyBranch($user, 'general_manager')
            ? $this->context->branchIdsForRole($user, 'general_manager')->all()
            : array_filter([$this->context->id()]);

        return $query->whereIn('branch_id', $branchIds)
            ->whereNotIn('recorder_role', self::OWNER_APPROVES_ROLES)
            ->count();
    }

    public function receiptResponse(MarketList $list): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($list->receipt_path);
    }

    /**
     * Blank rows dropped, amounts already in pesewas, kinds resolved. A
     * list with nothing left is refused.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function cleanItems(array $items): array
    {
        $validKinds = ExpenseCategory::pluck('id')->all();
        $clean = [];

        foreach ($items as $item) {
            $description = trim((string) ($item['description'] ?? ''));
            $amount = (int) ($item['amount'] ?? 0);

            if ($description === '' || $amount <= 0) {
                continue;
            }

            $kindId = isset($item['expense_category_id']) ? (int) $item['expense_category_id'] : null;
            $unit = trim((string) ($item['unit'] ?? ''));

            $clean[] = [
                'description' => Str::limit($description, 255, ''),
                'amount' => $amount,
                'quantity' => isset($item['quantity']) && $item['quantity'] !== '' ? round((float) $item['quantity'], 2) : null,
                'unit' => $unit !== '' ? Str::limit($unit, 30, '') : null,
                'expense_category_id' => $kindId && in_array($kindId, $validKinds, true) ? $kindId : $this->kinds->match($description)->id,
            ];
        }

        if ($clean === []) {
            throw MarketListException::noItems();
        }

        return $clean;
    }

    private function storeReceipt(UploadedFile $receipt): string
    {
        return $receipt->storeAs(self::DIRECTORY, Str::uuid().'.'.$receipt->extension(), self::DISK);
    }

    private function log(MarketList $list, ?User $actor, string $action, ?string $note = null): void
    {
        $list->events()->create(['actor_id' => $actor?->id, 'action' => $action, 'note' => $note]);
    }
}
