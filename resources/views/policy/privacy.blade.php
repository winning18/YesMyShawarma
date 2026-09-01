<x-customer-layout title="Privacy Policy · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Privacy Policy') }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Last updated: 2 September 2026') }}</p>

        <p class="leading-relaxed mb-4">
            {{ __("At Yes! My Shawarma, we take your privacy seriously. This Privacy Policy explains how we collect, use, share, and protect your personal information when you interact with us — through our website, our mobile app, our branches, our ordering channels, or any other platform we operate. By using our services, you confirm that you have read and understood this policy.") }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('Yes! My Shawarma is operated by YES! MY GRILL in Accra, Ghana.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Table of contents') }}</h2>
        <ol class="list-decimal pl-5 space-y-1 leading-relaxed mb-4">
            <li><a href="#information-we-collect" class="underline hover:text-brand-yellow-dark">{{ __('Information we collect') }}</a></li>
            <li><a href="#how-we-use" class="underline hover:text-brand-yellow-dark">{{ __('How we use your information') }}</a></li>
            <li><a href="#how-we-share" class="underline hover:text-brand-yellow-dark">{{ __('How we share your information') }}</a></li>
            <li><a href="#retention" class="underline hover:text-brand-yellow-dark">{{ __('How long we keep your information') }}</a></li>
            <li><a href="#protection" class="underline hover:text-brand-yellow-dark">{{ __('How we protect your information') }}</a></li>
            <li><a href="#rights" class="underline hover:text-brand-yellow-dark">{{ __('Your rights') }}</a></li>
            <li><a href="#marketing" class="underline hover:text-brand-yellow-dark">{{ __('Marketing and your choices') }}</a></li>
            <li><a href="#children" class="underline hover:text-brand-yellow-dark">{{ __("Children's privacy") }}</a></li>
            <li><a href="#third-party-links" class="underline hover:text-brand-yellow-dark">{{ __('Third-party links') }}</a></li>
            <li><a href="#changes" class="underline hover:text-brand-yellow-dark">{{ __('Changes to this policy') }}</a></li>
            <li><a href="#contact" class="underline hover:text-brand-yellow-dark">{{ __('Contact us') }}</a></li>
        </ol>

        <h2 id="information-we-collect" class="text-lg font-bold mt-8 mb-3">{{ __('1. Information we collect') }}</h2>
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
            {{ __('To take payment for your orders, we work with Paystack, our secure payment provider, to process card and mobile money transactions. Depending on how you choose to pay, this may involve your mobile money number or card details. Your card and payment credentials are handled directly by Paystack under encryption — we do not store full card numbers on our own systems.') }}
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

        <h2 id="how-we-use" class="text-lg font-bold mt-8 mb-3">{{ __('2. How we use your information') }}</h2>
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

        <h2 id="how-we-share" class="text-lg font-bold mt-8 mb-3">{{ __('3. How we share your information') }}</h2>
        <p class="leading-relaxed mb-2">{{ __('We do not sell your personal information. We share it only in the following situations:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li><strong>{{ __('Service providers:') }}</strong> {{ __("trusted partners who help us run the business, such as our payment provider (Paystack), our delivery riders and logistics partners, our SMS and messaging providers, and our technology vendors. They may only use your information to carry out the specific service we've asked them to provide.") }}</li>
            <li><strong>{{ __('Legal reasons:') }}</strong> {{ __('where we are required to by law, regulation, or a valid legal request, or where it is necessary to protect the rights, property, or safety of Yes! My Shawarma, our customers, or the public.') }}</li>
            <li><strong>{{ __('Business changes:') }}</strong> {{ __('if our business is reorganised, merged, or acquired, your information may be transferred as part of that process, subject to the protections in this policy.') }}</li>
        </ul>

        <h2 id="retention" class="text-lg font-bold mt-8 mb-3">{{ __('4. How long we keep your information') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We keep your personal information only for as long as we need it to fulfil your orders, run your account, meet our legal and tax obligations, and resolve any disputes. When we no longer need it, we securely delete it or anonymise it so it can no longer identify you. If you close your account, we may keep limited records where the law requires us to.') }}
        </p>

        <h2 id="protection" class="text-lg font-bold mt-8 mb-3">{{ __('5. How we protect your information') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We use appropriate technical and organisational measures to protect your information against loss, misuse, and unauthorised access. These include encryption of sensitive data, restricted staff access on a need-to-know basis, secure hosting, and processing payments through trusted providers such as Paystack. No system can be guaranteed to be completely secure, but we work continuously to keep your information safe and to respond quickly if a problem arises.') }}
        </p>

        <h2 id="rights" class="text-lg font-bold mt-8 mb-3">{{ __('6. Your rights') }}</h2>
        <p class="leading-relaxed mb-2">
            {{ __("Under Ghana's Data Protection Act, 2012 (Act 843), you have rights over the personal information we hold about you. You may:") }}
        </p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li><strong>{{ __('Access') }}</strong> {{ __('the personal information we hold about you.') }}</li>
            <li><strong>{{ __('Correct') }}</strong> {{ __('information that is inaccurate or out of date.') }}</li>
            <li><strong>{{ __('Request deletion') }}</strong> {{ __('of your information where there is no legal reason for us to keep it.') }}</li>
            <li><strong>{{ __('Object to or restrict') }}</strong> {{ __('certain uses of your information, including marketing.') }}</li>
            <li><strong>{{ __('Withdraw consent') }}</strong> {{ __('at any time where we rely on your consent to process your information.') }}</li>
            <li><strong>{{ __('Complain') }}</strong> {{ __('to the Data Protection Commission if you believe we have handled your information improperly.') }}</li>
        </ul>
        <p class="leading-relaxed mb-4">
            {{ __('To exercise any of these rights, contact us using the details below. We will respond within a reasonable time and in line with applicable law.') }}
        </p>

        <h2 id="marketing" class="text-lg font-bold mt-8 mb-3">{{ __('7. Marketing and your choices') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We will only send you marketing messages by email, SMS, or other channels if you have chosen to receive them. You can opt out at any time by following the unsubscribe instructions in the message, replying to stop, or contacting us directly. Opting out of marketing will not affect the essential messages we send about your orders, such as confirmations and delivery updates.') }}
        </p>

        <h2 id="children" class="text-lg font-bold mt-8 mb-3">{{ __("8. Children's privacy") }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Our services are not directed at children under the age of 18, and we do not knowingly collect personal information from them. If you believe a child has provided us with personal information, please contact us and we will take steps to delete it.') }}
        </p>

        <h2 id="third-party-links" class="text-lg font-bold mt-8 mb-3">{{ __('9. Third-party links') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Our website or app may contain links to other websites or services that we do not operate. This Privacy Policy does not apply to those third parties, and we are not responsible for their privacy practices. We encourage you to read the privacy policy of any website you visit.') }}
        </p>

        <h2 id="changes" class="text-lg font-bold mt-8 mb-3">{{ __('10. Changes to this policy') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We may update this Privacy Policy from time to time to reflect changes in our practices, technology, or the law. When we do, we will revise the "Last updated" date at the top of this page, and where the changes are significant, we will let you know through our website, app, or other appropriate means. We encourage you to review this policy periodically.') }}
        </p>

        <h2 id="contact" class="text-lg font-bold mt-8 mb-3">{{ __('11. Contact us') }}</h2>
        <p class="leading-relaxed mb-2">
            {{ __('If you have any questions about this Privacy Policy or how we handle your information, please get in touch:') }}
        </p>
        <p class="leading-relaxed mb-1"><strong>{{ __('Yes! My Shawarma') }}</strong></p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>
                {{ __('Email:') }}
                <a href="mailto:info@yesmyshawarma.com" class="underline hover:text-brand-yellow-dark">info@yesmyshawarma.com</a>
            </li>
            <li>
                {{ __('Phone:') }}
                <a href="tel:+233263702929" class="underline hover:text-brand-yellow-dark">026 370 2929</a>,
                <a href="tel:+233243635265" class="underline hover:text-brand-yellow-dark">024 363 5265</a>,
                <a href="tel:+233531907747" class="underline hover:text-brand-yellow-dark">053 190 7747</a>
            </li>
            <li>{{ __('Address: GA Odumase, Adjacent Star Oil, Accra, Ghana') }}</li>
        </ul>
    </div>
</x-customer-layout>
