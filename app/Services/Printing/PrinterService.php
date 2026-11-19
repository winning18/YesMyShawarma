<?php

namespace App\Services\Printing;

use App\Models\Branch;
use App\Models\Printer;
use App\Models\Terminal;
use Illuminate\Support\Collection;

/**
 * A branch's printers (Express POS step 8a): which printer a receipt or a
 * kitchen ticket goes to. A branch with no printers prints nothing and
 * shows no print buttons. In 8a printing goes through the browser of the
 * device that takes the action; the print agent (8b) will route each job
 * to its printer directly.
 */
class PrinterService
{
    public function hasPrinters(int $branchId): bool
    {
        return Printer::where('branch_id', $branchId)->where('is_active', true)->exists();
    }

    /**
     * A counter's own receipt printer, else the branch's first active one.
     */
    public function receiptPrinterFor(int $branchId, ?Terminal $counter = null): ?Printer
    {
        $own = $counter?->receipt_printer_id
            ? Printer::where('id', $counter->receipt_printer_id)->where('is_active', true)->first()
            : null;

        return $own ?? Printer::where('branch_id', $branchId)->where('role', Printer::RECEIPT)->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * @return Collection<int, Printer>
     */
    public function kitchenPrinters(int $branchId): Collection
    {
        return Printer::where('branch_id', $branchId)->where('role', Printer::KITCHEN)->where('is_active', true)->orderBy('id')->get();
    }

    /**
     * @param  array{name: string, role: string, paper_width: int, connection: string, address: ?string, opens_drawer?: bool, is_active?: bool}  $values
     */
    public function save(Branch $branch, array $values, ?Printer $printer = null): Printer
    {
        $fields = [
            'name' => trim($values['name']),
            'role' => $values['role'],
            'paper_width' => (int) $values['paper_width'],
            'connection' => $values['connection'],
            'address' => ($address = trim((string) ($values['address'] ?? ''))) === '' ? null : $address,
            // Only a receipt printer has a drawer cabled to it.
            'opens_drawer' => $values['role'] === Printer::RECEIPT && ($values['opens_drawer'] ?? false),
            'is_active' => $values['is_active'] ?? true,
        ];

        if ($printer) {
            $printer->update($fields);

            return $printer;
        }

        return Printer::create(['branch_id' => $branch->id, ...$fields]);
    }
}
