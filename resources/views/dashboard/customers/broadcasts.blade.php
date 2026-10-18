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
                audiences: @js($audiences),
                suffix: @js($sampleSuffix),
                message: @js(old('message', '')),
                branch: @js((string) old('branch_id', '')),
                mode: @js(old('send_mode', 'now')),
            })"
        >
            @csrf

            <div>
                <x-input-label for="message" :value="__('Message')" required />
                <textarea id="message" name="message" rows="4" maxlength="560" x-model="message" required
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                    placeholder="{{ __('e.g. It\'s Shawarma Wednesday! Buy 2, get 1 free at every branch today. Order: yesmyshawarma.com') }}"></textarea>
                <p class="text-xs text-gray-500 mt-1">
                    <span x-text="length"></span> {{ __('characters incl. the opt-out link') }} ·
                    <span class="font-semibold" x-text="parts"></span> <span x-text="parts === 1 ? @js(__('SMS')) : @js(__('SMS each'))"></span>
                    <template x-if="unicode"><span class="text-amber-700"> · {{ __('an emoji or special character makes each SMS 70 characters instead of 160') }}</span></template>
                </p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Every SMS ends with a personal link to stop these messages:') }} <span class="font-mono" x-text="suffix.trim()"></span></p>
                <x-input-error class="mt-2" :messages="$errors->get('message')" />
            </div>

            <div>
                <x-input-label for="branch_id" :value="__('Send to')" />
                <select id="branch_id" name="branch_id" x-model="branch" class="mt-1 block w-full max-w-sm rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('All customers') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ __('Customers who ordered from :branch', ['branch' => $branch->name]) }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    <span class="font-semibold text-gray-800" x-text="audience.recipients"></span> {{ __('numbers') }} ·
                    <span x-text="audience.duplicates"></span> {{ __('duplicates removed') }} ·
                    <span x-text="audience.invalid"></span> {{ __('invalid numbers skipped') }} ·
                    <span x-text="audience.opted_out"></span> {{ __('opted out') }}
                </p>
                <p class="text-xs text-gray-500 mt-1">{{ __('The list updates itself: new customers are included automatically when a broadcast starts.') }}</p>
            </div>

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
                    <span class="text-xs text-gray-500">· {{ __('max one broadcast every :days days', ['days' => $minDaysBetween]) }}</span>
                </p>
                <x-primary-button
                    x-bind:disabled="message.trim() === '' || audience.recipients === 0"
                    x-on:click="if (! confirm(@js(__('Send this SMS to')) + ' ' + audience.recipients + ' ' + @js(__('people?')))) $event.preventDefault()"
                >
                    <span x-text="mode === 'later' ? @js(__('Schedule broadcast')) : @js(__('Send broadcast'))"></span>
                </x-primary-button>
            </div>
        </form>

        <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
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
                            <td class="px-4 py-2 text-gray-500">{{ $broadcast->branch?->name ?? __('All customers') }}</td>
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
                            <td class="px-4 py-2 text-right">
                                @if ($broadcast->status === 'scheduled')
                                    <form method="POST" action="{{ route('dashboard.customers.broadcasts.cancel', $broadcast) }}" onsubmit="return confirm(@js(__('Cancel this broadcast?')))">
                                        @csrf
                                        <button class="text-sm text-red-600 hover:underline">{{ __('Cancel') }}</button>
                                    </form>
                                @endif
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
        function smsBroadcastForm({ audiences, suffix, message, branch, mode }) {
            const basic = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
            const extended = '^{}\\[~]|€';

            return {
                audiences, suffix, message, branch, mode,
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
                get audience() { return this.audiences[this.branch] ?? this.audiences['']; },
            };
        }
    </script>
</x-app-layout>
