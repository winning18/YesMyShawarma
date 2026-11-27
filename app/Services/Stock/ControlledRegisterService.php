<?php

namespace App\Services\Stock;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\PurchaseOrder;
use App\Models\Refund;
use App\Models\StockItem;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The controlled drugs register (owner-approved 2026-11-27; plan step R5;
 * its exact format to confirm with the pilot pharmacist): for one
 * controlled medicine at a branch, every receipt, sale, return, count,
 * transfer and write-off in date order with the balance after each, and for
 * a sale the prescription (patient, prescriber) and the manager who approved
 * it. Read from the stock record (stock_movements), which nothing rewrites.
 */
class ControlledRegisterService
{
    /** @return Collection<int, MenuItem> */
    public function medicines(): Collection
    {
        return MenuItem::withTrashed()->where('medicine_class', MenuItem::CONTROLLED)->orderBy('name')->get(['id', 'name', 'strength', 'dosage_form', 'unit_name']);
    }

    /**
     * @return array{opening: float, rows: list<array{at: CarbonInterface, what: string, in: ?float, out: ?float, balance: float, reference: ?string, details: ?string, by: ?string}>}
     */
    public function entries(MenuItem $medicine, int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $item = StockItem::withoutGlobalScopes()->where('branch_id', $branchId)->where('menu_item_id', $medicine->id)->first();
        if (! $item) {
            return ['opening' => 0.0, 'rows' => []];
        }

        $signed = fn (StockMovement $m) => $m->type === StockMovement::TYPE_COUNT ? (float) $m->quantity : (in_array($m->type, StockMovement::OUTGOING, true) ? -1 : 1) * (float) $m->quantity;
        $after = $item->movements()->where('created_at', '>=', $from)->get()->sum($signed);
        $balance = (float) $item->quantity - $after;
        $opening = $balance;

        $rows = [];
        foreach ($item->movements()->with('actor:id,name')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->orderBy('id')->get() as $m) {
            $change = $signed($m);
            $balance += $change;
            [$reference, $details, $by] = $this->source($m);
            $rows[] = [
                'at' => $m->created_at,
                'what' => $this->label($m->type),
                'in' => $change > 0 ? $change : null,
                'out' => $change < 0 ? -$change : null,
                'balance' => $balance,
                'reference' => $reference,
                'details' => $details ?? $m->note,
                'by' => $by ?? $m->actor?->name,
            ];
        }

        return ['opening' => $opening, 'rows' => $rows];
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} reference, details, approved/done by */
    private function source(StockMovement $m): array
    {
        return match ($m->source_type) {
            StockMovement::SOURCE_ORDER => $this->sale(Order::withoutGlobalScopes()->find($m->source_id)),
            StockMovement::SOURCE_PURCHASE_ORDER => (function () use ($m) {
                $po = PurchaseOrder::withoutGlobalScopes()->with('supplier:id,name')->find($m->source_id);

                return [$po?->reference, collect([$po?->supplier?->name, $m->note])->filter()->join(' · '), null];
            })(),
            StockMovement::SOURCE_REFUND => (function () use ($m) {
                $refund = Refund::withoutGlobalScopes()->with('order:id,reference')->find($m->source_id);

                return [$refund?->order?->reference, __('Returned by the customer'), null];
            })(),
            default => [null, null, null],
        };
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private function sale(?Order $order): array
    {
        if (! $order) {
            return [null, null, null];
        }
        $prescription = Prescription::withoutGlobalScopes()->with('approver:id,name')->where('order_id', $order->id)->first();
        if (! $prescription) {
            return [$order->reference, __('No prescription recorded'), null];
        }

        return [
            $order->reference,
            __('Patient: :patient · Prescriber: :prescriber:facility · Issued :date:number', [
                'patient' => $prescription->patient_name,
                'prescriber' => $prescription->prescriber_name,
                'facility' => $prescription->prescriber_facility ? ' ('.$prescription->prescriber_facility.')' : '',
                'date' => $prescription->issued_on->format('j M Y'),
                'number' => $prescription->prescription_number ? ' · No. '.$prescription->prescription_number : '',
            ]),
            $prescription->approver?->name,
        ];
    }

    private function label(string $type): string
    {
        return match ($type) {
            StockMovement::TYPE_SALE => __('Sold'),
            StockMovement::TYPE_RECEIVED => __('Received'),
            StockMovement::TYPE_RESTOCK => __('Back in'),
            StockMovement::TYPE_RETURNED => __('Returned'),
            StockMovement::TYPE_COUNT => __('Count correction'),
            StockMovement::TYPE_TRANSFER_OUT => __('Sent to another branch'),
            StockMovement::TYPE_TRANSFER_IN => __('Arrived from another branch'),
            StockMovement::TYPE_EXPIRED => __('Expired, written off'),
            default => $type,
        };
    }
}
