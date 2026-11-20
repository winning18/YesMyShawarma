<?php

namespace App\Services\Printing;

use App\Models\Order;
use App\Models\PrintAgent;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Settings\BusinessProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The print queue the Express POS Print Agent collects from (step 8b).
 *
 * Jobs are only queued while the branch's agent is online — a ticket that
 * prints an hour later when a PC is switched back on helps nobody — and
 * the caller falls back to printing through the browser otherwise.
 */
class PrintJobService
{
    /** A job sent to the agent but not reported back is sent again after this. */
    private const RETRY_AFTER_SECONDS = 120;

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly PrintAgentService $agents,
        private readonly PrinterService $printers,
        private readonly ReceiptService $receipts,
        private readonly EscPosRenderer $escPos,
        private readonly BusinessProfile $profile,
    ) {}

    /**
     * A POS sale: its receipt, its kitchen ticket(s), and the cash drawer
     * for a cash sale. Returns false when there's no agent to print them.
     */
    public function forPosSale(Order $order, ?User $by = null): bool
    {
        if (! $this->agents->isOnline($order->branch_id)) {
            return false;
        }

        $receiptPrinter = $this->printers->receiptPrinterFor($order->branch_id, $order->shift?->terminal);

        DB::transaction(function () use ($order, $by, $receiptPrinter) {
            if ($receiptPrinter) {
                $this->queue($order, $receiptPrinter, PrintJob::RECEIPT, $by);

                if ($order->payment_method === 'cash' && $receiptPrinter->opens_drawer) {
                    $this->queue($order, $receiptPrinter, PrintJob::DRAWER, $by);
                }
            }

            foreach ($this->printers->kitchenPrinters($order->branch_id) as $kitchen) {
                $this->queue($order, $kitchen, PrintJob::KITCHEN, $by);
            }
        });

        return true;
    }

    /** An accepted order's kitchen ticket(s). */
    public function forAccepted(Order $order, ?User $by = null): bool
    {
        if (! $this->agents->isOnline($order->branch_id)) {
            return false;
        }

        foreach ($this->printers->kitchenPrinters($order->branch_id) as $kitchen) {
            $this->queue($order, $kitchen, PrintJob::KITCHEN, $by);
        }

        return true;
    }

    /**
     * A reprint from a Print button. Returns how many jobs were queued:
     * 0 means there's no agent (or no such printer) and the browser prints.
     */
    public function reprint(Order $order, string $kind, ?User $by = null): int
    {
        if (! $this->agents->isOnline($order->branch_id)) {
            return 0;
        }

        $printers = $kind === PrintJob::RECEIPT
            ? collect([$this->printers->receiptPrinterFor($order->branch_id, $order->shift?->terminal)])->filter()
            : $this->printers->kitchenPrinters($order->branch_id);

        $printers->each(fn (Printer $printer) => $this->queue($order, $printer, $kind, $by));

        return $printers->count();
    }

    /**
     * "Print a test page" on the Printers page: the agent's job, or null
     * when there's no agent on (the page prints a test through the browser).
     */
    public function test(Printer $printer, ?User $by = null): ?PrintJob
    {
        if (! $this->agents->isOnline($printer->branch_id)) {
            return null;
        }

        return PrintJob::create([
            'branch_id' => $printer->branch_id,
            'printer_id' => $printer->id,
            'kind' => PrintJob::TEST,
            'requested_by_user_id' => $by?->id,
        ]);
    }

    /**
     * Each printer's latest test page, for the Printers page.
     *
     * @return Collection<int, PrintJob> keyed by printer id
     */
    public function latestTests(int $branchId): Collection
    {
        return PrintJob::where('branch_id', $branchId)->where('kind', PrintJob::TEST)
            ->orderByDesc('id')->get()->unique('printer_id')->keyBy('printer_id');
    }

    /**
     * Hands the agent its branch's waiting jobs, ready to print: each with
     * its printer's connection and the ESC/POS bytes. A job the agent took
     * but never reported on is offered again, up to three tries.
     *
     * @return list<array{id: int, printer: array{name: string, connection: string, address: ?string}, data: string}>
     */
    public function claim(PrintAgent $agent, int $limit = 10): array
    {
        $jobs = DB::transaction(function () use ($agent, $limit) {
            $jobs = PrintJob::with('printer')
                ->where('branch_id', $agent->branch_id)
                ->where(fn ($query) => $query->where('status', PrintJob::PENDING)
                    ->orWhere(fn ($query) => $query->where('status', PrintJob::SENT)->where('claimed_at', '<', now()->subSeconds(self::RETRY_AFTER_SECONDS))))
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($jobs as $job) {
                if ($job->attempts >= self::MAX_ATTEMPTS || ! $job->printer) {
                    $job->update(['status' => PrintJob::FAILED, 'error' => $job->printer ? __('The print agent never confirmed it.') : __('The printer was removed.')]);

                    continue;
                }

                $job->update(['status' => PrintJob::SENT, 'attempts' => $job->attempts + 1, 'claimed_at' => now()]);
            }

            return $jobs->where('status', PrintJob::SENT)->values();
        });

        return $jobs->map(fn (PrintJob $job) => [
            'id' => $job->id,
            'printer' => [
                'name' => $job->printer->name,
                'connection' => $job->printer->connection,
                'address' => $job->printer->address,
            ],
            'data' => base64_encode($this->render($job)),
        ])->all();
    }

    public function report(PrintAgent $agent, PrintJob $job, bool $printed, ?string $error = null): void
    {
        abort_unless($job->branch_id === $agent->branch_id, 404);

        $job->update($printed
            ? ['status' => PrintJob::PRINTED, 'printed_at' => now(), 'error' => null]
            : ['status' => PrintJob::FAILED, 'error' => mb_substr($error ?: __('The printer didn\'t print it.'), 0, 255)]);
    }

    /**
     * Receipts and tickets that didn't come out in the last few hours and
     * haven't been reprinted since — shown on the order board.
     *
     * @return Collection<int, PrintJob>
     */
    public function recentFailures(int $branchId): Collection
    {
        return PrintJob::with(['printer', 'order'])
            ->where('branch_id', $branchId)
            ->where('status', PrintJob::FAILED)
            ->whereNotIn('kind', [PrintJob::DRAWER, PrintJob::TEST])
            ->where('updated_at', '>', now()->subHours(3))
            ->whereNotExists(fn ($query) => $query->from('print_jobs as later')
                ->whereColumn('later.order_id', 'print_jobs.order_id')
                ->whereColumn('later.kind', 'print_jobs.kind')
                ->whereColumn('later.id', '>', 'print_jobs.id')
                ->where('later.status', PrintJob::PRINTED))
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    private function queue(Order $order, Printer $printer, string $kind, ?User $by): PrintJob
    {
        return PrintJob::create([
            'branch_id' => $order->branch_id,
            'printer_id' => $printer->id,
            'order_id' => $order->id,
            'kind' => $kind,
            'requested_by_user_id' => $by?->id,
        ]);
    }

    private function render(PrintJob $job): string
    {
        $order = $job->order;

        return match ($job->kind) {
            PrintJob::RECEIPT => $this->escPos->receipt($this->receipts->receipt($order), $job->printer, $this->profile->logoFilePath()),
            PrintJob::KITCHEN => $this->escPos->kitchenTicket($this->receipts->kitchenTicket($order), $job->printer),
            PrintJob::DRAWER => $this->escPos->drawerOnly(),
            PrintJob::TEST => $this->escPos->testPage($job->printer, $this->profile->name(), $job->printer->branch?->name),
        };
    }
}
