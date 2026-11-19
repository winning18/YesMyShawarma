<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Printer;
use App\Services\Printing\PrinterService;
use App\Services\Printing\ReceiptService;
use App\Services\Shifts\CounterService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A receipt or kitchen ticket laid out for its printer's paper (Express
 * POS step 8a), printed through the browser: the POS, the order board and
 * the order page load it in a hidden frame and print it.
 */
class PrintController extends Controller
{
    public function show(Order $order, string $kind, PrinterService $printers, ReceiptService $receipts, CounterService $counters): View
    {
        Gate::authorize('view', $order);
        abort_unless(in_array($kind, Printer::ROLES, true), 404);

        $printer = $kind === Printer::RECEIPT
            ? $printers->receiptPrinterFor($order->branch_id, $counters->currentAt($order->branch_id))
            : $printers->kitchenPrinters($order->branch_id)->first();

        return view('print.'.$kind, [
            'width' => $printer?->paper_width ?? 80,
            'data' => $kind === Printer::RECEIPT ? $receipts->receipt($order) : $receipts->kitchenTicket($order),
        ]);
    }
}
