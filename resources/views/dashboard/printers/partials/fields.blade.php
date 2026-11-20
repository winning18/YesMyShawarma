@php $id = $printer ? 'p'.$printer->id : 'new'; @endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <div>
        <label for="{{ $id }}-name" class="block text-xs font-medium text-gray-500">{{ __('Name') }}</label>
        <input id="{{ $id }}-name" name="name" value="{{ $printer ? $printer->name : old('name') }}" maxlength="60" required placeholder="{{ __('e.g. Front counter receipts') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
    </div>
    <div>
        <label for="{{ $id }}-role" class="block text-xs font-medium text-gray-500">{{ __('Prints') }}</label>
        <select id="{{ $id }}-role" name="role" x-model="role" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option value="receipt">{{ __('Customer receipts') }}</option>
            <option value="kitchen">{{ __('Kitchen tickets') }}</option>
        </select>
    </div>
    <div>
        <span class="block text-xs font-medium text-gray-500">{{ __('Paper') }}</span>
        <div class="mt-2 flex gap-4 text-sm">
            @foreach ([80, 58] as $mm)
                <label class="flex items-center gap-1.5"><input type="radio" name="paper_width" value="{{ $mm }}" @checked($f['paper_width'] === $mm)> {{ $mm }} mm</label>
            @endforeach
        </div>
    </div>
    <div>
        <span class="block text-xs font-medium text-gray-500">{{ __('Connected by') }}</span>
        <div class="mt-2 flex gap-4 text-sm">
            <label class="flex items-center gap-1.5"><input type="radio" name="connection" value="network" x-model="connection"> {{ __('Network (Ethernet / Wi-Fi)') }}</label>
            <label class="flex items-center gap-1.5"><input type="radio" name="connection" value="usb" x-model="connection"> {{ __('USB') }}</label>
        </div>
    </div>
    <div class="sm:col-span-2">
        <label for="{{ $id }}-address" class="block text-xs font-medium text-gray-500" x-text="connection === 'network' ? @js(__('IP address')) : @js(__('Printer name in Windows'))"></label>
        <input id="{{ $id }}-address" name="address" value="{{ $printer ? $printer->address : old('address') }}" maxlength="120" :placeholder="connection === 'network' ? '192.168.1.50' : 'EPSON TM-T82III Receipt'" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono">
        <p class="text-xs text-gray-400 mt-1" x-text="connection === 'network' ? @js(__('The print agent sends to this address. Find it on the printer\'s network status slip.')) : @js(__('Exactly as Windows lists it under Settings, Printers & scanners, on the PC with the print agent.'))"></p>
    </div>
    <label class="flex items-center gap-2 text-sm" x-show="role === 'receipt'">
        <input type="checkbox" name="opens_drawer" value="1" @checked($printer?->opens_drawer) class="rounded border-gray-300">
        {{ __('A cash drawer is plugged into this printer') }}
    </label>
</div>
