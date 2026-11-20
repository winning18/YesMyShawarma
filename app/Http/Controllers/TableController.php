<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Exceptions\TableTabException;
use App\Models\DiningTable;
use App\Models\TableTab;
use App\Services\Branches\BranchContext;
use App\Services\Cart\PosCartService;
use App\Services\Customers\CustomerService;
use App\Services\Printing\PrinterService;
use App\Services\Printing\PrintJobService;
use App\Services\Printing\ReceiptService;
use App\Services\Shifts\CounterService;
use App\Services\Shifts\ShiftService;
use App\Services\Tables\TableTabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tables on the POS (the `tables` module, TableTabService): the table
 * cards, a new table, a bill, settling it at this device's till, moving it
 * to a free table, and printing it. Anyone who takes orders (orders.create),
 * at their current branch.
 */
class TableController extends Controller
{
    public function __construct(
        private readonly TableTabService $tabs,
        private readonly BranchContext $context,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('orders.create');

        return response()->json(['tables' => $this->withUnsent($this->tabs->tablesFor($this->branchId()))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('orders.create');
        $validated = $request->validate(['name' => ['required', 'string', 'max:40']]);

        $table = $this->tabs->createTable($this->branchId(), $validated['name']);

        return response()->json(['table' => ['id' => $table->id, 'name' => $table->name], 'tables' => $this->withUnsent($this->tabs->tablesFor($this->branchId()))], 201);
    }

    public function update(Request $request, DiningTable $table): JsonResponse
    {
        $this->authorizeTable($table);
        $validated = $request->validate(['name' => ['required', 'string', 'max:40']]);

        try {
            $this->tabs->rename($table, $validated['name']);
        } catch (TableTabException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['tables' => $this->withUnsent($this->tabs->tablesFor($table->branch_id))]);
    }

    public function destroy(DiningTable $table): JsonResponse
    {
        $this->authorizeTable($table);

        try {
            $this->tabs->delete($table);
            app(PosCartService::class)->forTable($table->id)->clear();
        } catch (TableTabException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['tables' => $this->withUnsent($this->tabs->tablesFor($table->branch_id))]);
    }

    /** The table's customer, kept with its bill. */
    public function customer(Request $request, TableTab $tab, CustomerService $customers): JsonResponse
    {
        $this->authorizeTab($tab);
        $validated = $request->validate(['name' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:20']]);
        $phone = filled($validated['phone'] ?? null) ? $customers->normalizeGhanaPhone($validated['phone']) : null;

        $this->tabs->setCustomer($tab, $validated['name'] ?? null, $phone);

        return response()->json(['tables' => $this->withUnsent($this->tabs->tablesFor($tab->branch_id))]);
    }

    /** One bill: its rounds and total. */
    public function show(TableTab $tab): JsonResponse
    {
        $this->authorizeTab($tab);
        $tab->load(['table', 'orders.items.options']);

        return response()->json([
            'id' => $tab->id,
            'table' => $tab->table?->name,
            'open' => $tab->isOpen(),
            ...$this->tabs->summary($tab),
            'orders' => $this->tabs->rounds($tab)->map(fn ($order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status,
                'placed_at' => $order->placed_at?->clone()->timezone('Africa/Accra')->format('H:i'),
                'total' => $order->total,
                'items' => $order->items->map(fn ($item) => $item->quantity.'× '.$item->name_snapshot)->values(),
            ])->values(),
        ]);
    }

    public function settle(Request $request, TableTab $tab, ShiftService $shifts, PrintJobService $jobs, CounterService $counters): JsonResponse
    {
        $this->authorizeTab($tab);
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'momo'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $tab = $this->tabs->settle(
                $tab,
                $validated['payment_method'],
                $validated['payment_reference'] ?? null,
                $request->user(),
                $shifts->activeForBranch($tab->branch_id),
                $this->context->primaryRoleFor($request->user(), $tab->branch_id) ?? 'staff',
            );
        } catch (TableTabException|PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'total' => $tab->total,
            'printed_by_agent' => $tab->total > 0 && $jobs->forBill($tab, $counters->currentAt($tab->branch_id), $request->user()),
            'print_url' => route('dashboard.tables.bill', $tab),
            'tables' => $this->withUnsent($this->tabs->tablesFor($tab->branch_id)),
        ]);
    }

    public function move(Request $request, TableTab $tab): JsonResponse
    {
        $this->authorizeTab($tab);
        $validated = $request->validate(['table_id' => ['required', 'integer']]);
        $to = DiningTable::where('branch_id', $tab->branch_id)->findOrFail($validated['table_id']);

        $from = $tab->dining_table_id;
        try {
            $this->tabs->move($tab, $to);
        } catch (TableTabException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Its unsent items on this till go with it.
        if ($request->session()->has('pos_cart_table_'.$from)) {
            $request->session()->put('pos_cart_table_'.$to->id, $request->session()->pull('pos_cart_table_'.$from));
        }

        return response()->json(['tables' => $this->withUnsent($this->tabs->tablesFor($tab->branch_id))]);
    }

    /** The bill through the browser's print dialog (no print agent on). */
    public function bill(TableTab $tab, ReceiptService $receipts, PrinterService $printers, CounterService $counters): View
    {
        $this->authorizeTab($tab);

        return view('print.receipt', [
            'width' => $printers->receiptPrinterFor($tab->branch_id, $counters->currentAt($tab->branch_id))?->paper_width ?? 80,
            'data' => $receipts->tabBill($tab),
        ]);
    }

    /** Re-queue the bill on the print agent (a Print button). */
    public function printBill(Request $request, TableTab $tab, PrintJobService $jobs, CounterService $counters): JsonResponse
    {
        $this->authorizeTab($tab);

        return response()->json(['queued' => $jobs->forBill($tab, $counters->currentAt($tab->branch_id), $request->user()) ? 1 : 0, 'print_url' => route('dashboard.tables.bill', $tab)]);
    }

    /**
     * Each table's unsent items on this till (its own POS cart): a table
     * with an order in progress is in use even before anything is sent.
     *
     * @param  Collection<int, array<string, mixed>>  $tables
     * @return Collection<int, array<string, mixed>>
     */
    private function withUnsent($tables)
    {
        $cart = app(PosCartService::class);

        return $tables->map(fn (array $table) => [...$table, 'unsent' => $cart->forTable($table['id'])->itemCount()]);
    }

    private function branchId(): int
    {
        return (int) ($this->context->id() ?? abort(404));
    }

    private function authorizeTable(DiningTable $table): void
    {
        Gate::authorize('orders.create');
        abort_unless($table->branch_id === (int) $this->context->id() && $table->is_active, 404);
    }

    private function authorizeTab(TableTab $tab): void
    {
        Gate::authorize('orders.create');
        abort_unless($tab->branch_id === (int) $this->context->id(), 404);
    }
}
