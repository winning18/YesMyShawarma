{{--
    Expects an ancestor x-data="menuItemForm()" scope (see
    partials/menu-item-form-script.blade.php) — reads/clears its `errors`
    array. Lists every unsatisfied group at once (all of them are checked
    up front, not stopped at the first failure), so a customer with two
    required groups left blank sees both in one go instead of fixing one,
    resubmitting, and only then finding out about the next.
--}}
<div
    x-show="errors.length > 0" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    @click.self="errors = []"
>
    <div class="fixed inset-0 bg-black/50"></div>
    <div class="relative bg-brand-white rounded-lg shadow-lg max-w-sm w-full p-6 text-center">
        <h2 class="text-lg font-bold text-brand-black mb-2">{{ __('Almost there') }}</h2>
        <ul class="text-sm text-brand-gray-500 mb-5 space-y-1">
            <template x-for="message in errors" :key="message">
                <li x-text="message"></li>
            </template>
        </ul>
        <button
            type="button" @click="errors = []"
            class="w-full px-4 py-2.5 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark"
        >
            {{ __('OK') }}
        </button>
    </div>
</div>
