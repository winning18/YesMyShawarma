@php $editing = old('faq_item_id') !== null ? (int) old('faq_item_id') === $item?->id : $item === null; @endphp
<div>
    <x-input-label :for="$prefix.'-question'" :value="__('Question')" required />
    <x-text-input :id="$prefix.'-question'" name="question" class="mt-1 block w-full" :value="$editing ? old('question', $item?->question) : $item?->question" required maxlength="255" />
    @if ($editing)<x-input-error class="mt-2" :messages="$errors->get('question')" />@endif
</div>
<div>
    <x-input-label :for="$prefix.'-answer'" :value="__('Answer')" required />
    <textarea id="{{ $prefix }}-answer" name="answer" rows="3" required maxlength="2000" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ $editing ? old('answer', $item?->answer) : $item?->answer }}</textarea>
    @if ($editing)<x-input-error class="mt-2" :messages="$errors->get('answer')" />@endif
</div>
<div class="w-32">
    <x-input-label :for="$prefix.'-sort_order'" :value="__('Order')" />
    <x-text-input :id="$prefix.'-sort_order'" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="$editing ? old('sort_order', $item?->sort_order) : $item?->sort_order" />
    @if ($editing)<x-input-error class="mt-2" :messages="$errors->get('sort_order')" />@endif
</div>
