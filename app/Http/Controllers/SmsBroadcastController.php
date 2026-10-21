<?php

namespace App\Http\Controllers;

use App\Exceptions\SmsBroadcastException;
use App\Http\Requests\StoreSmsBroadcastRequest;
use App\Models\Branch;
use App\Models\SmsBroadcast;
use App\Services\Sms\SmsBroadcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SmsBroadcastController extends Controller
{
    public function __construct(private readonly SmsBroadcastService $broadcasts) {}

    public function index(): View
    {
        Gate::authorize('customers.broadcast');

        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Audience size per choice in the branch dropdown, so the form can
        // show who a broadcast reaches before it's sent. Key '' = everyone.
        $audiences = collect(['' => null])->union($branches->pluck('id', 'id'))
            ->map(function (?int $branchId) {
                $audience = $this->broadcasts->audience($branchId);

                return [
                    'recipients' => count($audience['recipients']),
                    'duplicates' => $audience['duplicates'],
                    'invalid' => $audience['invalid'],
                    'opted_out' => $audience['opted_out'],
                ];
            });

        return view('dashboard.customers.broadcasts', [
            'branches' => $branches,
            'audiences' => $audiences,
            'broadcasts' => SmsBroadcast::with(['branch:id,name', 'creator:id,name'])->latest('scheduled_at')->paginate(20),
            'sampleSuffix' => $this->broadcasts->composeFor('', 'XXXXXXXX'),
        ]);
    }

    public function store(StoreSmsBroadcastRequest $request): RedirectResponse
    {
        Gate::authorize('customers.broadcast');

        try {
            $broadcast = $this->broadcasts->schedule(
                $request->validated('message'),
                $request->validated('branch_id'),
                $request->scheduledAt(),
                $request->user(),
            );
        } catch (SmsBroadcastException $e) {
            return back()->withInput()->withErrors(['message' => $e->getMessage()]);
        }

        return redirect()->route('dashboard.customers.broadcasts.index')->with('status', $request->scheduledAt()
            ? __('Broadcast scheduled for :time.', ['time' => $broadcast->scheduled_at->timezone('Africa/Accra')->format('D j M, g:ia')])
            : __('Broadcast queued. Sending starts within a minute.'));
    }

    public function cancel(SmsBroadcast $broadcast): RedirectResponse
    {
        Gate::authorize('customers.broadcast');

        try {
            $this->broadcasts->cancel($broadcast);
        } catch (SmsBroadcastException $e) {
            return back()->withErrors(['broadcast' => $e->getMessage()]);
        }

        return back()->with('status', __('Broadcast cancelled.'));
    }
}
