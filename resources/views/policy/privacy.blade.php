<x-customer-layout title="Privacy Policy · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Privacy Policy') }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Last updated: 2 September 2026') }}</p>

        <p class="leading-relaxed mb-4">
            {{ __("At Yes! My Shawarma, we take your privacy seriously. This Privacy Policy explains how we collect, use, share, and protect your personal information when you interact with us through our website, our mobile app, our branches, our ordering channels, or any other platform we operate. By using our services, you confirm that you have read and understood this policy.") }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('Yes! My Shawarma is operated by YES! MY GRILL in Accra, Ghana.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('1. Information we collect') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We collect different types of information so we can serve you well and keep improving. It falls into the following categories.') }}
        </p>

        <h3 class="font-semibold mt-6 mb-2">{{ __('1.1 Personal information') }}</h3>
        <p class="leading-relaxed mb-2">{{ __('When you create an account, place an order, or get in touch with us, we may collect:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('Your name') }}</li>
            <li>{{ __('Email address') }}</li>
            <li>{{ __('Phone number') }}</li>
            <li>{{ __('Delivery address') }}</li>
        </ul>

        <h3 class="font-semibold mt-6 mb-2">{{ __('1.2 Payment information') }}</h3>
        <p class="leading-relaxed mb-4">
            {{ __('To take payment for your orders, we work with Paystack, cash, momo, our secure payment provider, to process card and mobile money transactions. Depending on how you choose to pay, this may involve your mobile money number or card details. Your card and payment credentials are handled directly by Paystack under encryption — we do not store full card numbers on our own systems.') }}
        </p>

        <h3 class="font-semibold mt-6 mb-2">{{ __('1.3 Order information') }}</h3>
        <p class="leading-relaxed mb-2">{{ __('We keep information tied to your orders, including:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('The menu items you order') }}</li>
            <li>{{ __('Any special instructions or preferences') }}</li>
            <li>{{ __('Your order history') }}</li>
            <li>{{ __('Delivery instructions') }}</li>
        </ul>
        <p class="leading-relaxed mb-4">
            {{ __('This lets us prepare your orders accurately and offer you a more personal service over time.') }}
        </p>

        <h3 class="font-semibold mt-6 mb-2">{{ __('1.4 Usage data') }}</h3>
        <p class="leading-relaxed mb-2">{{ __('When you visit our website or use our app, we automatically collect technical information such as:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('Your IP address') }}</li>
            <li>{{ __('Browser type and version') }}</li>
            <li>{{ __('The pages you view and how long you spend on them') }}</li>
            <li>{{ __('The date and time of your visit') }}</li>
            <li>{{ __('The website that referred you to us') }}</li>
        </ul>
        <p class="leading-relaxed mb-4">
            {{ __('We use this to spot trends, fix problems, and make our platform easier to use.') }}
        </p>

        <h3 class="font-semibold mt-6 mb-2">{{ __('1.5 Cookies and similar technologies') }}</h3>
        <p class="leading-relaxed mb-4">
            {{ __('We use cookies and similar tools to improve your experience on our website. Cookies are small files stored on your device that help us remember your preferences and keep things working smoothly. You can control or switch off cookies through your browser settings, although some features may not work properly if you do.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('2. How we use your information') }}</h2>
        <p class="leading-relaxed mb-2">{{ __('We use the information we collect to:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li><strong>{{ __('Fulfil your orders:') }}</strong> {{ __("prepare, process, and deliver what you've ordered.") }}</li>
            <li><strong>{{ __('Support you:') }}</strong> {{ __('respond to your questions, complaints, and feedback.') }}</li>
            <li><strong>{{ __('Manage your account:') }}</strong> {{ __('let you sign in, view your order history, and save your preferences.') }}</li>
            <li><strong>{{ __('Send you updates:') }}</strong> {{ __("share offers, news, and promotions, but only if you've chosen to receive them.") }}</li>
            <li><strong>{{ __('Personalise your experience:') }}</strong> {{ __('tailor what we show you based on your past orders and preferences.') }}</li>
            <li><strong>{{ __('Improve our platform:') }}</strong> {{ __('understand how our website and app are used so we can make them better.') }}</li>
            <li><strong>{{ __('Meet our legal obligations:') }}</strong> {{ __('comply with the laws and regulations that apply to us.') }}</li>
        </ul>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('3. How we share your information') }}</h2>
        <p class="leading-relaxed mb-2">{{ __('We do not sell your personal information. We share it only in the following situations:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li><strong>{{ __('Service providers:') }}</strong> {{ __("trusted partners who help us run the business, such as our payment provider (Paystack), our delivery riders and logistics partners, our SMS and messaging providers, and our technology vendors. They may only use your information to carry out the specific service we've asked them to provide.") }}</li>
            <li><strong>{{ __('Legal reasons:') }}</strong> {{ __('where we are required to by law, regulation, or a valid legal request, or where it is necessary to protect the rights, property, or safety of Yes! My Shawarma, our customers, or the public.') }}</li>
            <li><strong>{{ __('Business changes:') }}</strong> {{ __('if our business is reorganised, merged, or acquired, your information may be transferred as part of that process, subject to the protections in this policy.') }}</li>
        </ul>
    </div>
</x-customer-layout>
