<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\StockBatch;
use App\Services\Branches\BranchContext;
use App\Services\Settings\BusinessType;
use App\Services\Stock\BatchService;
use App\Services\Stock\ControlledRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A pharmacy's Stock → Expiry and Controlled drugs register, and a sale's
 * prescription photo (R5, 2026-11-27). Pharmacies only.
 */
class PharmacyController extends Controller
{
    private function pharmacyOnly(): void
    {
        abort_unless(app(BusinessType::class)->current() === BusinessType::PHARMACY, 404);
    }

    public function expiry(Request $request, BranchContext $context, BatchService $batches): View|RedirectResponse
    {
        $this->pharmacyOnly();
        Gate::authorize('stock.manage');
        if (! $branch = $context->branch()) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to see its stock.'));
        }
        $days = max(1, min(730, $request->integer('days', $batches->warningDays())));

        return view('dashboard.inventory.expiry', ['branch' => $branch, 'days' => $days, 'batches' => $batches->expiring($branch->id, $days)]);
    }

    public function writeOff(Request $request, StockBatch $batch, BranchContext $context, BatchService $batches): RedirectResponse
    {
        $this->pharmacyOnly();
        Gate::authorize('stock.manage');
        abort_unless($batch->stockItem?->branch_id === $context->id(), 404);

        $batches->writeOff($batch, $request->user());

        return back()->with('status', __('Written off: stock down by what was left in the batch.'));
    }

    public function register(Request $request, BranchContext $context, ControlledRegisterService $register): View|RedirectResponse
    {
        $this->pharmacyOnly();
        Gate::authorize('stock.manage');
        if (! $branch = $context->branch()) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to see its stock.'));
        }

        $medicines = $register->medicines();
        $medicine = $medicines->firstWhere('id', $request->integer('medicine')) ?? $medicines->first();
        $from = $request->date('from', 'Y-m-d', 'Africa/Accra')?->startOfDay() ?? Carbon::now('Africa/Accra')->startOfMonth();
        $to = $request->date('to', 'Y-m-d', 'Africa/Accra')?->endOfDay() ?? Carbon::now('Africa/Accra')->endOfDay();

        return view('dashboard.inventory.register', [
            'branch' => $branch,
            'medicines' => $medicines,
            'medicine' => $medicine,
            'from' => $from, 'to' => $to,
            'register' => $medicine ? $register->entries($medicine, $branch->id, $from->copy()->utc(), $to->copy()->utc()) : null,
        ]);
    }

    /** A prescription's photo: health data, managers of the branch only, never cached. */
    public function photo(Prescription $prescription): StreamedResponse
    {
        $this->pharmacyOnly();
        Gate::authorize('orders.refund');
        abort_unless($prescription->photo_path && Storage::disk('local')->exists($prescription->photo_path), 404);

        return Storage::disk('local')->response($prescription->photo_path, null, ['Cache-Control' => 'private, no-store']);
    }
}
