<?php

namespace App\Services\Pos;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Settings\BusinessType;
use App\Services\Stock\BatchService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

/**
 * A pharmacy's restricted sales at the till (owner-approved 2026-11-27;
 * plan step R5; the rules are marked for the pilot pharmacist to confirm):
 *
 * - A Pharmacy medicine, Prescription-only or Controlled item can't be sold
 *   until a manager approves with their Staff ID and PIN (owner: any
 *   manager's PIN; PinService::approver()).
 * - Prescription-only and Controlled also need the prescription recorded:
 *   patient, prescriber, date (number, facility, phone and a photo optional).
 * - The approval covers the items in the sale when it was given; adding
 *   another restricted item needs approving again.
 * - Expired stock is never sold (BatchService::assertSellable()).
 * Placed, the sale gets an order_events row naming who approved, and the
 * prescription is linked to it.
 */
class PharmacySaleService
{
    private const SESSION_KEY = 'pos_pharmacy_check';

    public function applies(): bool
    {
        return app(BusinessType::class)->current() === BusinessType::PHARMACY;
    }

    /**
     * What the sale on screen needs, for the till.
     *
     * @param  list<array<string, mixed>>  $lines  the cart's priced lines
     * @return array{needs_approval: bool, needs_prescription: bool, approved: bool, approved_by: ?string}|null
     */
    public function status(array $lines): ?array
    {
        if (! $this->applies()) {
            return null;
        }
        $restricted = $this->restricted($lines);
        $check = Session::get(self::SESSION_KEY);

        return [
            'needs_approval' => $restricted['approval'] !== [],
            'needs_prescription' => $restricted['prescription'] !== [],
            'approved' => $this->covers($check, $restricted),
            'approved_by' => $check['approver_name'] ?? null,
        ];
    }

    /**
     * A manager's approval (and the prescription) for the restricted items now in the sale.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $prescription  patient_name, prescriber_name, issued_on, …
     */
    public function approve(array $lines, User $approver, array $prescription, ?UploadedFile $photo, int $branchId): void
    {
        $restricted = $this->restricted($lines);
        if ($restricted['approval'] === []) {
            throw ValidationException::withMessages(['approval' => __('Nothing in this sale needs approving.')]);
        }

        $record = null;
        if ($restricted['prescription'] !== []) {
            foreach (['patient_name' => __('the patient\'s name'), 'prescriber_name' => __('the prescriber\'s name'), 'issued_on' => __('the date on the prescription')] as $field => $label) {
                if (trim((string) ($prescription[$field] ?? '')) === '') {
                    throw ValidationException::withMessages(["prescription.{$field}" => __('This sale has prescription-only medicine: enter :what.', ['what' => $label])]);
                }
            }
            $record = [
                'patient_name' => trim($prescription['patient_name']),
                'patient_phone' => trim((string) ($prescription['patient_phone'] ?? '')) ?: null,
                'prescriber_name' => trim($prescription['prescriber_name']),
                'prescriber_facility' => trim((string) ($prescription['prescriber_facility'] ?? '')) ?: null,
                'issued_on' => $prescription['issued_on'],
                'prescription_number' => trim((string) ($prescription['prescription_number'] ?? '')) ?: null,
                'notes' => trim((string) ($prescription['notes'] ?? '')) ?: null,
                // Health data: the private disk, never public.
                'photo_path' => $photo ? $photo->store("prescriptions/{$branchId}", 'local') : null,
            ];
        }

        Session::put(self::SESSION_KEY, [
            'approved_by' => $approver->id,
            'approver_name' => $approver->name,
            'items' => $restricted['approval'],
            'prescription' => $record,
        ]);
    }

    /**
     * Before the sale is placed: expired stock refused, restricted items approved.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function assertCanSell(array $lines, int $branchId): void
    {
        if (! $this->applies()) {
            return;
        }
        foreach (collect($lines)->pluck('menu_item_id')->unique() as $id) {
            if ($product = MenuItem::find($id)) {
                app(BatchService::class)->assertSellable($product, $branchId);
            }
        }

        $restricted = $this->restricted($lines);
        if ($restricted['approval'] !== [] && ! $this->covers(Session::get(self::SESSION_KEY), $restricted)) {
            throw ValidationException::withMessages(['approval' => $restricted['prescription'] !== []
                ? __('This sale has prescription-only medicine: record the prescription and have a manager approve it first.')
                : __('This sale has a pharmacy medicine: a manager approves it first.')]);
        }
    }

    /** After the sale is placed: the record, then the check is used up. */
    public function recordFor(Order $order): void
    {
        $check = Session::pull(self::SESSION_KEY);
        if (! $this->applies() || ! $check) {
            return;
        }

        $prescription = $check['prescription']
            ? Prescription::create([...$check['prescription'], 'branch_id' => $order->branch_id, 'order_id' => $order->id, 'approved_by' => $check['approved_by']])
            : null;

        // Bookkeeping, not a status change (as refunds and MoMo checks).
        $order->events()->create([
            'from_status' => $order->status,
            'to_status' => $order->status,
            'actor_type' => 'staff',
            'actor_id' => $check['approved_by'],
            'shift_id' => $order->shift_id,
            'meta' => ['action' => 'pharmacy_check', 'approved_by' => $check['approved_by'], 'prescription_id' => $prescription?->id, 'items' => $check['items']],
        ]);
    }

    public function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{approval: list<int>, prescription: list<int>}
     */
    private function restricted(array $lines): array
    {
        $ids = collect($lines)->pluck('menu_item_id')->unique()->values();
        $products = MenuItem::whereIn('id', $ids)->get();

        return [
            'approval' => $products->filter->needsApproval()->pluck('id')->sort()->values()->all(),
            'prescription' => $products->filter->needsPrescription()->pluck('id')->sort()->values()->all(),
        ];
    }

    /** @param  array{approval: list<int>, prescription: list<int>}  $restricted */
    private function covers(?array $check, array $restricted): bool
    {
        if ($restricted['approval'] === []) {
            return true;
        }
        if (! $check || array_diff($restricted['approval'], $check['items']) !== []) {
            return false;
        }

        return $restricted['prescription'] === [] || ! empty($check['prescription']);
    }
}
