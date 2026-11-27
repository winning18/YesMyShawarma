<x-app-layout>
    <x-slot name="header">
        @include('dashboard.customers._tabs', ['active' => 'broadcasts'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif
        <x-input-error :messages="$errors->get('broadcast')" />

        <form
            method="POST"
            action="{{ route('dashboard.customers.broadcasts.store') }}"
            class="bg-white shadow rounded-lg p-6 space-y-5"
            x-data="smsBroadcastForm({
                lists: @js($lists),
                audienceUrl: @js(route('dashboard.customers.broadcasts.audience')),
                suffix: @js($sampleSuffix),
                message: @js(old('message', '')),
                all: @js(old('message') !== null ? (bool) old('all') : true),
                branches: @js(array_map('strval', old('branches', []))),
                uploaded: @js((bool) old('uploaded')),
                mode: @js(old('send_mode', 'now')),
            })"
            x-on:copy-broadcast.window="useBroadcast($event.detail)"
        >
            @csrf

            <div>
                <x-input-label for="message" :value="__('Message')" required />
                <textarea id="message" name="message" rows="4" maxlength="560" x-model="message" x-ref="message" required
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                    placeholder="{{ __('e.g. Today only: buy 2, get 1 free at every branch. Order on our website or visit us.') }}"></textarea>
                <p class="text-xs text-gray-500 mt-1">
                    <span x-text="length"></span> {{ __('characters incl. the opt-out link') }} ·
                    <span class="font-semibold" x-text="parts"></span> <span x-text="parts === 1 ? @js(__('SMS')) : @js(__('SMS each'))"></span>
                    <template x-if="unicode"><span class="text-amber-700"> · {{ __('an emoji or special character makes each SMS 70 characters instead of 160') }}</span></template>
                </p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Every SMS ends with a personal link to stop these messages:') }} <span class="font-mono" x-text="suffix.trim()"></span></p>
                <x-input-error class="mt-2" :messages="$errors->get('message')" />
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">{{ __('Send to') }}</legend>
                <p class="text-xs text-gray-500">{{ __('Tick one or more lists. A number on more than one list gets one SMS.') }}</p>
                <div class="mt-2 space-y-2 text-sm">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="all" value="1" x-model="all" class="rounded border-gray-300">
                        <span>{{ __('All customers') }} <span class="text-gray-500">· {{ __('everyone who has ordered or has an account') }} · {{ number_format($lists['all']['recipients']) }}</span></span>
                    </label>
                    @foreach ($branches as $branch)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="branches[]" value="{{ $branch->id }}" x-model="branches" class="rounded border-gray-300">
                            <span>{{ __('Customers who ordered from :branch', ['branch' => $branch->name]) }} <span class="text-gray-500">· {{ number_format($lists['branches'][$branch->id]['recipients']) }}</span></span>
                        </label>
                    @endforeach
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="uploaded" value="1" x-model="uploaded" class="rounded border-gray-300">
                        <span>{{ __('Uploaded contacts') }} <span class="text-gray-500">· {{ __('numbers from Import contacts') }} · {{ number_format($lists['uploaded']['recipients']) }}</span></span>
                    </label>
                </div>
                <p class="text-xs text-gray-500 mt-2" x-show="ticked">
                    <span class="font-semibold text-gray-800" x-text="audience.recipients.toLocaleString()"></span> {{ __('numbers') }} ·
                    <span x-text="audience.duplicates"></span> {{ __('duplicates removed') }} ·
                    <span x-text="audience.invalid"></span> {{ __('invalid numbers skipped') }} ·
                    <span x-text="audience.opted_out"></span> {{ __('opted out') }}
                    <span x-show="counting" x-cloak class="text-gray-400">· {{ __('counting…') }}</span>
                </p>
                <p class="text-xs text-gray-500 mt-1">{{ __('The lists update themselves: new customers are included automatically when a broadcast starts.') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('send_to')" />
                <x-input-error class="mt-2" :messages="$errors->get('branches.0')" />
            </fieldset>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">{{ __('When') }}</legend>
                <div class="mt-2 flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="send_mode" value="now" x-model="mode"> {{ __('Send now') }}</label>
                    <label class="flex items-center gap-2"><input type="radio" name="send_mode" value="later" x-model="mode"> {{ __('Schedule') }}</label>
                </div>
                <div x-show="mode === 'later'" x-cloak class="mt-3">
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                        min="{{ now('Africa/Accra')->format('Y-m-d\TH:i') }}"
                        x-bind:disabled="mode !== 'later'" x-bind:required="mode === 'later'"
                        class="rounded-md border-gray-300 text-sm">
                    <span class="text-xs text-gray-500 ml-2">{{ __('Accra time') }}</span>
                    <x-input-error class="mt-2" :messages="$errors->get('scheduled_at')" />
                </div>
            </fieldset>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                <p class="text-sm text-gray-700">
                    {{ __('Total:') }} <span class="font-semibold" x-text="(audience.recipients * parts).toLocaleString()"></span> {{ __('SMS charged') }}
                </p>
                <x-primary-button
                    x-bind:disabled="message.trim() === '' || ! ticked || audience.recipients === 0 || counting"
                    x-on:click="if (! confirm(@js(__('Send this SMS to')) + ' ' + audience.recipients + ' ' + @js(__('people?')))) $event.preventDefault()"
                >
                    <span x-text="mode === 'later' ? @js(__('Schedule broadcast')) : @js(__('Send broadcast'))"></span>
                </x-primary-button>
            </div>
        </form>

        <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto" x-data>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Send time') }}</th>
                        <th class="px-4 py-2">{{ __('Message') }}</th>
                        <th class="px-4 py-2">{{ __('To') }}</th>
                        <th class="px-4 py-2">{{ __('Status') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Sent') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Failed') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($broadcasts as $broadcast)
                        <tr>
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $broadcast->scheduled_at->timezone('Africa/Accra')->format('d M Y, g:ia') }}</td>
                            <td class="px-4 py-2 text-gray-800 max-w-xs truncate" title="{{ $broadcast->message }}">{{ $broadcast->message }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $pastAudiences[$broadcast->id]['label'] }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $badge = [
                                        'scheduled' => 'bg-blue-50 text-blue-700',
                                        'sending' => 'bg-amber-50 text-amber-700',
                                        'sent' => 'bg-green-50 text-green-700',
                                        'cancelled' => 'bg-gray-100 text-gray-500',
                                    ][$broadcast->status] ?? 'bg-gray-100 text-gray-500';
                                @endphp
                                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $badge }}">{{ __(ucfirst($broadcast->status)) }}</span>
                            </td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $broadcast->sent_count }}@if ($broadcast->recipient_count) / {{ $broadcast->recipient_count }}@endif</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $broadcast->failed_count }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center justify-end gap-3 whitespace-nowrap">
                                    {{-- Puts the message and its ticked lists back in the form above, to edit or schedule. --}}
                                    <button type="button" class="text-sm text-gray-600 hover:underline"
                                        x-on:click="$dispatch('copy-broadcast', { message: @js($broadcast->message), selection: @js($pastAudiences[$broadcast->id]['selection']) })">{{ __('Copy') }}</button>

                                    @if ($broadcast->status === 'sent')
                                        @php($resendTo = $pastAudiences[$broadcast->id]['recipients'])
                                        <form method="POST" action="{{ route('dashboard.customers.broadcasts.resend', $broadcast) }}"
                                            onsubmit="return confirm(@js($resendTo !== null ? __('Send this SMS again to :count people?', ['count' => $resendTo]) : __('Send this SMS again?')))">
                                            @csrf
                                            <button class="text-sm text-indigo-600 hover:underline">{{ __('Resend') }}</button>
                                        </form>

                                        @if ($broadcast->failed_count > 0)
                                            <form method="POST" action="{{ route('dashboard.customers.broadcasts.retry', $broadcast) }}"
                                                onsubmit="return confirm(@js(trans_choice('Retry the :count number this broadcast could not reach?|Retry the :count numbers this broadcast could not reach?', $broadcast->failed_count)))">
                                                @csrf
                                                <button class="text-sm text-amber-700 hover:underline">{{ __('Retry failed') }}</button>
                                            </form>
                                        @endif
                                    @endif

                                    @if ($broadcast->status === 'scheduled')
                                        <form method="POST" action="{{ route('dashboard.customers.broadcasts.cancel', $broadcast) }}" onsubmit="return confirm(@js(__('Cancel this broadcast?')))">
                                            @csrf
                                            <button class="text-sm text-red-600 hover:underline">{{ __('Cancel') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No broadcasts yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $broadcasts->links() }}
    </div>

    <script>
        // Mirrors SmsBroadcastService::smsParts() — a preview only; the
        // server's count is what's actually billed.
        function smsBroadcastForm({ lists, audienceUrl, suffix, message, all, branches, uploaded, mode }) {
            const basic = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
            const extended = '^{}\\[~]|€';
            const none = { recipients: 0, duplicates: 0, invalid: 0, opted_out: 0 };

            return {
                lists, suffix, message, all, branches, uploaded, mode,
                audience: none,
                counting: false,
                request: 0,

                init() {
                    this.recount();
                    this.$watch('all', () => this.recount());
                    this.$watch('branches', () => this.recount());
                    this.$watch('uploaded', () => this.recount());
                },

                get ticked() { return this.all || this.uploaded || this.branches.length > 0; },
                get text() { return this.message.trimEnd() + this.suffix; },
                get unicode() { return [...this.text].some(c => ! basic.includes(c) && ! extended.includes(c)); },
                get length() {
                    if (this.unicode) return this.text.length;
                    return [...this.text].reduce((n, c) => n + (extended.includes(c) ? 2 : 1), 0);
                },
                get parts() {
                    return this.unicode
                        ? (this.length <= 70 ? 1 : Math.ceil(this.length / 67))
                        : (this.length <= 160 ? 1 : Math.ceil(this.length / 153));
                },

                // One list ticked: its size is already on the page. Several:
                // the server counts them together, duplicates removed.
                async recount() {
                    const only = [this.all, this.uploaded, ...this.branches].filter(Boolean).length === 1;

                    if (! this.ticked) {
                        this.audience = none;
                        return;
                    }
                    if (only) {
                        this.audience = this.all ? lists.all : (this.uploaded ? lists.uploaded : lists.branches[this.branches[0]]) ?? none;
                        return;
                    }

                    const params = new URLSearchParams();
                    if (this.all) params.set('all', '1');
                    if (this.uploaded) params.set('uploaded', '1');
                    this.branches.forEach(id => params.append('branches[]', id));

                    const request = ++this.request;
                    this.counting = true;
                    try {
                        const response = await fetch(`${audienceUrl}?${params}`, { headers: { Accept: 'application/json' } });
                        if (response.ok && request === this.request) this.audience = await response.json();
                    } finally {
                        if (request === this.request) this.counting = false;
                    }
                },

                // A past broadcast's Copy button. Branches no longer in the
                // form (deactivated) are dropped.
                useBroadcast({ message, selection }) {
                    this.message = message;
                    this.all = selection.all;
                    this.uploaded = selection.uploaded;
                    this.branches = selection.branches.map(String).filter(id => id in lists.branches);
                    this.mode = 'now';
                    this.$refs.message.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    this.$refs.message.focus({ preventScroll: true });
                },
            };
        }
    </script>
</x-app-layout>
