<?php

namespace App\Services\DamageReports;

use App\Exceptions\DamageReportException;
use App\Models\DamageReport;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photos live on the private 'local' disk (config/filesystems.php), never
 * the public one branch/menu images use — a damage photo must never be a
 * guessable public URL, and it's meant to self-delete, which the public
 * disk has no mechanism for. Served only through
 * DamageReportController::photo() (permission-gated via DamageReportPolicy),
 * never a direct storage URL.
 */
class DamageReportService
{
    private const DISK = 'local';

    private const DIRECTORY = 'damage-reports';

    /**
     * $order set means a rider reporting damage on the order they're
     * actually carrying (DamageReportPolicy::create() already confirmed
     * it's assigned to them before this runs); $stockItem set means staff
     * optionally linking the report to a specific stock item. The
     * filename is a fresh UUID, not the eventual row's id — the photo has
     * to exist before the row does to get its path into the insert.
     */
    public function file(
        User $reporter,
        string $reporterRole,
        int $branchId,
        string $description,
        UploadedFile $photo,
        ?Order $order = null,
        ?StockItem $stockItem = null,
    ): DamageReport {
        $path = $photo->storeAs(self::DIRECTORY, Str::uuid().'.'.$photo->extension(), self::DISK);

        return DamageReport::create([
            'branch_id' => $branchId,
            'order_id' => $order?->id,
            'stock_item_id' => $stockItem?->id,
            'reported_by' => $reporter->id,
            'reporter_role' => $reporterRole,
            'description' => $description,
            'photo_path' => $path,
            'status' => DamageReport::STATUS_PENDING,
        ]);
    }

    /**
     * Approving is an acknowledgement only — no automatic stock or
     * financial consequence (a deliberate v1 scope decision, not an
     * oversight: schema.md's Damage reports section).
     */
    public function approve(DamageReport $damageReport, User $reviewer, ?string $note): DamageReport
    {
        $this->assertStatus($damageReport);

        $damageReport->update([
            'status' => DamageReport::STATUS_APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $damageReport->fresh();
    }

    public function deny(DamageReport $damageReport, User $reviewer, ?string $note): DamageReport
    {
        $this->assertStatus($damageReport);

        $damageReport->update([
            'status' => DamageReport::STATUS_DENIED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $damageReport->fresh();
    }

    /**
     * Starts the 24h deletion countdown — only the first time matters.
     * The caller (DamageReportController::photo()) is responsible for only
     * calling this when the viewer is actually reviewing (not the reporter
     * looking at their own submission back — DamageReportPolicy::viewPhoto()
     * allows both, but only a reviewer's view should start the clock).
     */
    public function markPhotoViewed(DamageReport $damageReport): void
    {
        if ($damageReport->photo_viewed_at === null) {
            $damageReport->update(['photo_viewed_at' => now()]);
        }
    }

    public function photoResponse(DamageReport $damageReport): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($damageReport->photo_path);
    }

    /**
     * Called by the scheduled DeleteExpiredDamageReportPhotos command —
     * deletes the file and nulls photo_path, but keeps the report row
     * itself permanently for audit, same as every other audit table in
     * this app (order_events, stock_movements) outliving the artifact it
     * was about.
     *
     * @return int how many photos were deleted, for the command's own output
     */
    public function deleteExpiredPhotos(): int
    {
        $expired = DamageReport::whereNotNull('photo_viewed_at')
            ->whereNotNull('photo_path')
            ->where('photo_viewed_at', '<', now()->subDay())
            ->get();

        foreach ($expired as $report) {
            Storage::disk(self::DISK)->delete($report->photo_path);
            $report->update(['photo_path' => null]);
        }

        return $expired->count();
    }

    private function assertStatus(DamageReport $damageReport): void
    {
        if ($damageReport->status !== DamageReport::STATUS_PENDING) {
            throw DamageReportException::wrongStatus(DamageReport::STATUS_PENDING, $damageReport->status);
        }
    }
}
