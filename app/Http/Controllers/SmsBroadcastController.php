<?php

namespace App\Http\Controllers;

use App\Exceptions\SmsBroadcastException;
use App\Http\Requests\StoreSmsBroadcastRequest;
use App\Models\Branch;
use App\Models\SmsBroadcast;
use App\Services\Sms\SmsBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SmsBroadcastController extends Controller
{
    public function __construct(private readonly SmsBroadcastService $broadcasts) {}

    public function index(): View
    {
        Gate::authorize('customers.broadcast');

        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $count = fn (array $selection) => $this->counts($this->broadcasts->audience($selection));

        // Size of each "Send to" list on its own, shown beside its tick;
        // the combined total comes from audience() below as ticks change.
        $lists = [
            'all' => $count(['all' => true]),
            'uploaded' => $count(['uploaded' => true]),
            'branches' => $branches->mapWithKeys(fn (Branch $branch) => [$branch->id => $count(['branches' => [$branch->id]])]),
        ];

        $broadcasts = SmsBroadcast::with(['creator:id,name'])->latest('scheduled_at')->paginate(20);
        $branchNames = Branch::pluck('name', 'id')->all();

        // Each audience is a full pass over customers; past broadcasts
        // mostly share the same ticks, so each distinct set is counted once.
        $sizes = [];
        $sizeOf = function (array $selection) use (&$sizes): int {
            $key = json_encode($selection);

            return $sizes[$key] ??= count($this->broadcasts->audience($selection)['recipients']);
        };

        return view('dashboard.customers.broadcasts', [
            'branches' => $branches,
            'lists' => $lists,
            'broadcasts' => $broadcasts,
            // Per past broadcast: its ticks, in words and as the form wants
            // them back (Copy), and today's size of that audience (Resend).
            'pastAudiences' => $broadcasts->getCollection()->mapWithKeys(fn (SmsBroadcast $broadcast) => [$broadcast->id => [
                'label' => $this->broadcasts->describe($broadcast->selection(), $branchNames),
                'selection' => $broadcast->selection(),
                'recipients' => $broadcast->status === SmsBroadcast::STATUS_SENT ? $sizeOf($broadcast->selection()) : null,
            ]]),
            'sampleSuffix' => $this->broadcasts->composeFor('', 'XXXXXXXX'),
        ]);
    }

    /**
     * The combined size of the ticked lists, for the form's live total.
     */
    public function audience(Request $request): JsonResponse
    {
        Gate::authorize('customers.broadcast');

        $validated = $request->validate([
            'all' => ['sometimes', 'boolean'],
            'branches' => ['sometimes', 'array'],
            'branches.*' => ['integer'],
            'uploaded' => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->counts($this->broadcasts->audience([
            'all' => (bool) ($validated['all'] ?? false),
            'branches' => $validated['branches'] ?? [],
            'uploaded' => (bool) ($validated['uploaded'] ?? false),
        ])));
    }

    public function store(StoreSmsBroadcastRequest $request): RedirectResponse
    {
        Gate::authorize('customers.broadcast');

        try {
            $broadcast = $this->broadcasts->schedule(
                $request->validated('message'),
                $request->selection(),
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

    public function retry(SmsBroadcast $broadcast): RedirectResponse
    {
        Gate::authorize('customers.broadcast');

        try {
            $retrying = $this->broadcasts->retryFailed($broadcast);
        } catch (SmsBroadcastException $e) {
            return back()->withErrors(['broadcast' => $e->getMessage()]);
        }

        return back()->with('status', trans_choice('Retrying :count failed number. Sending starts within a minute.|Retrying :count failed numbers. Sending starts within a minute.', $retrying));
    }

    public function resend(Request $request, SmsBroadcast $broadcast): RedirectResponse
    {
        Gate::authorize('customers.broadcast');

        try {
            $this->broadcasts->resend($broadcast, $request->user());
        } catch (SmsBroadcastException $e) {
            return back()->withErrors(['broadcast' => $e->getMessage()]);
        }

        return redirect()->route('dashboard.customers.broadcasts.index')->with('status', __('Broadcast queued again. Sending starts within a minute.'));
    }

    /**
     * @param  array{recipients: array<int, string>, duplicates: int, invalid: int, opted_out: int}  $audience
     * @return array{recipients: int, duplicates: int, invalid: int, opted_out: int}
     */
    private function counts(array $audience): array
    {
        return [
            'recipients' => count($audience['recipients']),
            'duplicates' => $audience['duplicates'],
            'invalid' => $audience['invalid'],
            'opted_out' => $audience['opted_out'],
        ];
    }
}
