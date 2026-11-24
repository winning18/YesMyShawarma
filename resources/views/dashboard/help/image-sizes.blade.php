@php($rules = app(\App\Services\Media\ImageRules::class))
<x-help-guide :title="__('Image sizes')" :summary="__('Every photo :business uploads, the shape it\'s shown in and the size that fits it best. A photo of any shape is accepted and cropped from the centre; one smaller than the minimum is refused because it would look blurred. Photos bigger than the best size are shrunk automatically, so customers on mobile data load them quickly.', ['business' => $business])">
    <section>
        <h3>{{ __('All uploads') }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200">
                        <th class="py-2 pe-4 font-medium">{{ __('Upload') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Shown on') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Shape') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Best size') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Smallest') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Largest file') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @foreach (\App\Services\Media\ImageRules::SLOTS as $slot)
                        <tr class="align-top">
                            <td class="py-2 pe-4 font-medium text-gray-800">{{ __($slot['label']) }}</td>
                            <td class="py-2 pe-4">{{ __($slot['shown']) }}</td>
                            <td class="py-2 pe-4">{{ __($slot['shape']) }}</td>
                            <td class="py-2 pe-4 whitespace-nowrap">{{ $slot['best'][1] ? $slot['best'][0].'×'.$slot['best'][1].' px' : __(':width px wide', ['width' => $slot['best'][0]]) }}</td>
                            <td class="py-2 pe-4 whitespace-nowrap">{{ $slot['min'] ? ($slot['min'][1] ? $slot['min'][0].'×'.$slot['min'][1].' px' : __(':width px wide', ['width' => $slot['min'][0]])) : '—' }}</td>
                            <td class="py-2 pe-4 whitespace-nowrap">{{ $rules->size($slot['max_kb']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h3>{{ __('Getting photos right') }}</h3>
        <ul>
            <li>{{ __('Keep the food in the middle of the photo, with some space around it: each look and each screen crops a little differently (a wide photo on a computer, a narrow one on a phone).') }}</li>
            <li>{{ __('Don\'t put words in photos: they get cut off on phones. Type them in the page\'s text instead.') }}</li>
            <li>{{ __('Daylight, a plain background and a clean plate make the biggest difference.') }}</li>
            <li>{{ __('Each upload shows how your photo will be cropped before you save it.') }}</li>
            <li>{{ __('A photo too big to upload? Send it to yourself on WhatsApp and save that copy: it comes back smaller.') }}</li>
        </ul>
    </section>

    <section>
        <h3>{{ __('Receipts and damage photos') }}</h3>
        <p>{{ __('These are evidence, seen only by the managers who check them. Each is deleted 24 hours after a manager has opened it and the market list is approved, or the damage report approved or denied. The list or report itself, with its amounts and notes, is kept.') }}</p>
    </section>
</x-help-guide>
