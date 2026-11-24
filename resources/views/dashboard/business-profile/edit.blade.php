<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Business profile') }}</h2>
    </x-slot>

    <div class="max-w-2xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">{{ __('Who the business is: shown to customers on the website, receipts and reports, and to staff inside the app.') }}</p>

        <form method="POST" action="{{ route('dashboard.business-profile.update') }}" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6 space-y-8">
            @csrf
            @method('PUT')

            <section class="space-y-4">
                <h3 class="font-semibold text-gray-800">{{ __('Name and wording') }}</h3>
                <div>
                    <x-input-label for="name" :value="__('Business name')" required />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $profile->name())" required maxlength="80" />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="legal_name" :value="__('Company that runs it (for legal pages, optional)')" />
                    <x-text-input id="legal_name" name="legal_name" class="mt-1 block w-full" :value="old('legal_name', $profile->legalName() === $profile->name() ? '' : $profile->legalName())" maxlength="120" />
                    <x-input-error class="mt-2" :messages="$errors->get('legal_name')" />
                </div>
                <div>
                    <x-input-label for="receipt_message" :value="__('Receipt message (optional)')" />
                    <x-text-input id="receipt_message" name="receipt_message" class="mt-1 block w-full" :value="old('receipt_message', $profile->receiptMessage())" maxlength="200" placeholder="{{ __('e.g. Thank you, come again!') }}" />
                    <p class="text-xs text-gray-500 mt-1">{{ __('Printed at the bottom of every receipt.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('receipt_message')" />
                </div>
                <div>
                    <x-input-label for="tin" :value="__('Tax identification number (TIN, optional)')" />
                    <x-text-input id="tin" name="tin" class="mt-1 block w-full" :value="old('tin', $profile->tin())" maxlength="20" placeholder="{{ __('e.g. C0012345678') }}" />
                    <p class="text-xs text-gray-500 mt-1">{{ __('Your GRA TIN. Printed on receipts when you charge tax.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('tin')" />
                </div>
                <div>
                    <x-input-label for="tagline" :value="__('Tagline (website footer, optional)')" />
                    <x-text-input id="tagline" name="tagline" class="mt-1 block w-full" :value="old('tagline', $profile->tagline())" maxlength="200" />
                    <x-input-error class="mt-2" :messages="$errors->get('tagline')" />
                </div>
            </section>

            <section class="space-y-4 border-t border-gray-100 pt-6">
                <h3 class="font-semibold text-gray-800">{{ __('Location and food') }}</h3>
                <p class="text-xs text-gray-500">{{ __('Used in search results and the information Google shows about each branch. Page titles can use :city.') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="city" :value="__('City (optional)')" />
                        <x-text-input id="city" name="city" class="mt-1 block w-full" :value="old('city', $profile->city())" maxlength="80" placeholder="{{ __('e.g. Kumasi') }}" />
                        <x-input-error class="mt-2" :messages="$errors->get('city')" />
                    </div>
                    <div>
                        <x-input-label for="region" :value="__('Region (optional)')" />
                        <x-text-input id="region" name="region" class="mt-1 block w-full" :value="old('region', $profile->region())" maxlength="80" placeholder="{{ __('e.g. Ashanti') }}" />
                        <x-input-error class="mt-2" :messages="$errors->get('region')" />
                    </div>
                </div>
                <div>
                    <x-input-label for="cuisines" :value="__('What you serve (optional)')" />
                    <x-text-input id="cuisines" name="cuisines" class="mt-1 block w-full" :value="old('cuisines', implode(', ', $profile->cuisines()))" maxlength="200" placeholder="{{ __('e.g. Ghanaian, Grills, Fast food') }}" />
                    <p class="text-xs text-gray-500 mt-1">{{ __('Kinds of food, separated by commas.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('cuisines')" />
                </div>
            </section>

            <section class="space-y-4 border-t border-gray-100 pt-6">
                <h3 class="font-semibold text-gray-800">{{ __('Contact') }}</h3>
                <p class="text-xs text-gray-500">{{ __('Branch phone numbers come from each branch. Separate several email addresses with commas.') }}</p>
                <div>
                    <x-input-label for="public_emails" :value="__('Email addresses shown on the Contact page')" />
                    <x-text-input id="public_emails" name="public_emails" class="mt-1 block w-full" :value="old('public_emails', implode(', ', $profile->publicEmails()))" />
                    <x-input-error class="mt-2" :messages="$errors->get('public_emails')" />
                </div>
                <div>
                    <x-input-label for="contact_recipients" :value="__('Contact form messages go to')" />
                    <x-text-input id="contact_recipients" name="contact_recipients" class="mt-1 block w-full" :value="old('contact_recipients', implode(', ', $profile->contactRecipients()))" />
                    <x-input-error class="mt-2" :messages="$errors->get('contact_recipients')" />
                </div>
                @php $socials = $profile->socialLinks(); @endphp
                @foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok'] as $network => $label)
                    <div>
                        <x-input-label for="{{ $network }}_url" :value="__(':network link (optional)', ['network' => $label])" />
                        <x-text-input id="{{ $network }}_url" name="{{ $network }}_url" type="url" class="mt-1 block w-full" :value="old($network.'_url', $socials[$network] ?? '')" placeholder="https://" />
                        <x-input-error class="mt-2" :messages="$errors->get($network.'_url')" />
                    </div>
                @endforeach
            </section>

            <section class="space-y-4 border-t border-gray-100 pt-6">
                <h3 class="font-semibold text-gray-800">{{ __('Logo, icon and colours') }}</h3>
                <div>
                    <x-input-label for="logo" :value="__('Logo')" />
                    @if ($profile->logoUrl())
                        <div class="mt-2 inline-block bg-gray-900 rounded-md p-3"><img src="{{ $profile->logoUrl() }}" alt="" class="h-10 w-auto"></div>
                        <label class="flex items-center gap-2 text-xs text-gray-500 mt-1"><input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300"> {{ __('Remove the logo (the name shows instead)') }}</label>
                    @endif
                    <x-image-upload rule="logo" name="logo" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="icon" :value="__('App icon')" />
                    <p class="text-xs text-gray-500">{{ __('Shown in the browser tab and when the site is added to a phone\'s home screen.') }}</p>
                    @if ($profile->iconUrl())
                        <img src="{{ $profile->iconUrl() }}" alt="" class="mt-2 h-12 w-12 rounded-md border border-gray-200">
                        <label class="flex items-center gap-2 text-xs text-gray-500 mt-1"><input type="checkbox" name="remove_icon" value="1" class="rounded border-gray-300"> {{ __('Remove the icon') }}</label>
                    @endif
                    <x-image-upload rule="icon" name="icon" class="mt-2" />
                </div>
                <div class="flex flex-wrap gap-8">
                    <div>
                        <x-input-label for="primary_colour" :value="__('Main colour')" required />
                        <input id="primary_colour" type="color" name="primary_colour" value="{{ old('primary_colour', $colours['brand_primary']) }}" class="mt-1 h-10 w-20 rounded border-gray-300">
                        <x-input-error class="mt-2" :messages="$errors->get('primary_colour')" />
                    </div>
                    <div>
                        <x-input-label for="accent_colour" :value="__('Accent colour')" required />
                        <input id="accent_colour" type="color" name="accent_colour" value="{{ old('accent_colour', $colours['brand_accent']) }}" class="mt-1 h-10 w-20 rounded border-gray-300">
                        <x-input-error class="mt-2" :messages="$errors->get('accent_colour')" />
                    </div>
                </div>
                <p class="text-xs text-gray-500">{{ __('Used across the customer website. Lighter and darker shades are worked out for you.') }}</p>
            </section>

            <section class="space-y-4 border-t border-gray-100 pt-6">
                <h3 class="font-semibold text-gray-800">{{ __('Main branch') }}</h3>
                <div>
                    <x-input-label for="main_branch_id" :value="__('Its address heads reports covering every branch')" />
                    <select id="main_branch_id" name="main_branch_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) old('main_branch_id', $mainBranchId) === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('main_branch_id')" />
                </div>
            </section>

            <x-primary-button>{{ __('Save business profile') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>
