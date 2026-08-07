{{--
    Shared by orders/dashboard.blade.php and pos/index.blade.php — the
    dashboard now serves two purposes (Orders / POS), and both pages carry
    the same channel-switcher + shift widget on one line. $title and
    $active ('orders' or 'pos') are passed in by the including view.
--}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center" x-data="shiftWidget()" x-init="init()">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $title }}
    </h2>

    <div class="flex items-center justify-center gap-3 order-last sm:order-none">
        <a
            href="{{ route('dashboard') }}"
            class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700 {{ $active === 'orders' ? 'ring-2 ring-offset-2 ring-red-600' : '' }}"
        >{{ __('Orders') }}</a>
        <a
            href="{{ route('dashboard.pos.index') }}"
            class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 {{ $active === 'pos' ? 'ring-2 ring-offset-2 ring-green-600' : '' }}"
        >{{ __('POS') }}</a>
    </div>

    <div class="flex items-center justify-end gap-3 text-sm">
        <span x-show="active" class="text-gray-500">
            {{ __('On shift') }} <span x-text="branch"></span>
        </span>
        <button
            type="button"
            x-show="!active"
            @click="start()"
            class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900"
        >{{ __('Start shift') }}</button>
        <button
            type="button"
            x-show="active"
            @click="end()"
            class="px-3 py-1.5 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300"
        >{{ __('End shift') }}</button>
    </div>
</div>
