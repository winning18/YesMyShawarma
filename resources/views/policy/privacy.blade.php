<x-customer-layout title="Privacy Policy · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Privacy Policy') }}</x-slot>

    {{--
        Verbatim content from a Termly-generated Privacy Notice (2026-08-30),
        reformatted into this page's existing heading/list markup — wording
        is unchanged from what Termly produced, including sections (Google
        Analytics, ad remarketing) that don't reflect what this app actually
        does. Published as-is by explicit decision rather than trimmed to
        match real behaviour like the rest of this page's content once did.
    --}}
    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Last updated: August 30, 2026') }}</p>

        <p class="leading-relaxed mb-4">
            {{ __("This Privacy Notice for Yes My Grill (doing business as Yes My Grill & Shawarma) ('we', 'us', or 'our'), describes how and why we might access, collect, store, use, and/or share ('process') your personal information when you use our services ('Services'), including when you:") }}
        </p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('Visit our website at :url or any website of ours that links to this Privacy Notice', ['url' => 'http://www.yesmyshawarma.com']) }}</li>
            <li>{{ __('Engage with us in other related ways, including any marketing or events') }}</li>
        </ul>
        <p class="leading-relaxed mb-4">
            {{ __("Questions or concerns? Reading this Privacy Notice will help you understand your privacy rights and choices. We are responsible for making decisions about how your personal information is processed. If you do not agree with our policies and practices, please do not use our Services. If you still have any questions or concerns, please contact us at :emails.", ['emails' => '']) }}
            <a href="mailto:info@yesmyshawarma.com" class="underline hover:text-brand-yellow-dark">info@yesmyshawarma.com</a>,
            <a href="mailto:yesmygrill@gmail.com" class="underline hover:text-brand-yellow-dark">yesmygrill@gmail.com</a>,
            <a href="mailto:yesmyshawarma@gmail.com" class="underline hover:text-brand-yellow-dark">yesmyshawarma@gmail.com</a>.
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Summary of key points') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('This summary provides key points from our Privacy Notice, but you can find out more details about any of these topics by using the table of contents below to find the section you are looking for.') }}
        </p>
        <p class="leading-relaxed mb-4">
            <strong>{{ __('What personal information do we process?') }}</strong>
            {{ __('When you visit, use, or navigate our Services, we may process personal information depending on how you interact with us and the Services, the choices you make, and the products and features you use.') }}
        </p>
        <p class="leading-relaxed mb-4">
            <strong>{{ __('Do we process any sensitive personal information?') }}</strong>
            {{ __("Some of the information may be considered 'special' or 'sensitive' in certain jurisdictions, for example your racial or ethnic origins, sexual orientation, and religious beliefs. We do not process sensitive personal information.") }}
        </p>
        <p class="leading-relaxed mb-4">
            <strong>{{ __('Do we collect any information from third parties?') }}</strong>
            {{ __('We do not collect any information from third parties.') }}
        </p>
        <p class="leading-relaxed mb-4">
            <strong>{{ __('How do we process your information?') }}</strong>
            {{ __('We process your information to provide, improve, and administer our Services, communicate with you, for security and fraud prevention, and to comply with law. We may also process your information for other purposes with your consent. We process your information only when we have a valid legal reason to do so.') }}
        </p>
        <p class="leading-relaxed mb-4">
            <strong>{{ __('In what situations and with which parties do we share personal information?') }}</strong>
            {{ __('We may share information in specific situations and with specific third parties.') }}
        </p>
        <p class="leading-relaxed mb-4">
            <strong>{{ __('How do we keep your information safe?') }}</strong>
            {{ __('We have adequate organisational and technical processes and procedures in place to protect your personal information. However, no electronic transmission over the internet or information storage technology can be guaranteed to be 100% secure, so we cannot promise or guarantee that hackers, cybercriminals, or other unauthorised third parties will not be able to defeat our security and improperly collect, access, steal, or modify your information.') }}
        </p>
        <p class="leading-relaxed mb-4">
            <strong>{{ __('What are your rights?') }}</strong>
            {{ __('Depending on where you are located geographically, the applicable privacy law may mean you have certain rights regarding your personal information.') }}
        </p>
        <p class="leading-relaxed mb-4">
            <strong>{{ __('How do you exercise your rights?') }}</strong>
            {{ __('The easiest way to exercise your rights is by submitting a') }}
            <a href="https://app.termly.io/dsar/a89d8031-2a27-49af-aa84-4a12795e8ec5" target="_blank" rel="noopener noreferrer" class="underline hover:text-brand-yellow-dark">{{ __('data subject access request') }}</a>,
            {{ __('or by contacting us. We will consider and act upon any request in accordance with applicable data protection laws.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Table of contents') }}</h2>
        <ol class="list-decimal pl-5 space-y-1 leading-relaxed mb-4">
            <li><a href="#what-we-collect" class="underline hover:text-brand-yellow-dark">{{ __('What information do we collect?') }}</a></li>
            <li><a href="#how-we-process" class="underline hover:text-brand-yellow-dark">{{ __('How do we process your information?') }}</a></li>
            <li><a href="#sharing" class="underline hover:text-brand-yellow-dark">{{ __('When and with whom do we share your personal information?') }}</a></li>
            <li><a href="#cookies" class="underline hover:text-brand-yellow-dark">{{ __('Do we use cookies and other tracking technologies?') }}</a></li>
            <li><a href="#retention" class="underline hover:text-brand-yellow-dark">{{ __('How long do we keep your information?') }}</a></li>
            <li><a href="#safety" class="underline hover:text-brand-yellow-dark">{{ __('How do we keep your information safe?') }}</a></li>
            <li><a href="#minors" class="underline hover:text-brand-yellow-dark">{{ __('Do we collect information from minors?') }}</a></li>
            <li><a href="#rights" class="underline hover:text-brand-yellow-dark">{{ __('What are your privacy rights?') }}</a></li>
            <li><a href="#dnt" class="underline hover:text-brand-yellow-dark">{{ __('Controls for Do-Not-Track features') }}</a></li>
            <li><a href="#updates" class="underline hover:text-brand-yellow-dark">{{ __('Do we make updates to this notice?') }}</a></li>
            <li><a href="#contact" class="underline hover:text-brand-yellow-dark">{{ __('How can you contact us about this notice?') }}</a></li>
            <li><a href="#review" class="underline hover:text-brand-yellow-dark">{{ __('How can you review, update, or delete the data we collect from you?') }}</a></li>
        </ol>

        <h2 id="what-we-collect" class="text-lg font-bold mt-8 mb-3">{{ __('1. What information do we collect?') }}</h2>
        <p class="leading-relaxed mb-1"><strong>{{ __('Personal information you disclose to us') }}</strong></p>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: We collect personal information that you provide to us.') }}</em>
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('We collect personal information that you voluntarily provide to us when you register on the Services, express an interest in obtaining information about us or our products and Services, when you participate in activities on the Services, or otherwise when you contact us.') }}
        </p>
        <p class="leading-relaxed mb-2">
            {{ __('Personal Information Provided by You. The personal information that we collect depends on the context of your interactions with us and the Services, the choices you make, and the products and features you use. The personal information we collect may include the following:') }}
        </p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('names') }}</li>
            <li>{{ __('phone numbers') }}</li>
            <li>{{ __('email addresses') }}</li>
            <li>{{ __('mailing addresses') }}</li>
            <li>{{ __('billing addresses') }}</li>
            <li>{{ __('debit/credit card numbers') }}</li>
            <li>{{ 'passwords' }}</li>
            <li>{{ __('contact or authentication data') }}</li>
            <li>{{ __('contact preferences') }}</li>
        </ul>
        <p class="leading-relaxed mb-4">
            {{ __('Sensitive Information. We do not process sensitive information.') }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('Payment Data. We may collect data necessary to process your payment if you choose to make purchases, such as your payment instrument number, and the security code associated with your payment instrument. All payment data is handled and stored by Paystack. You may find their privacy notice link(s) here:') }}
            <a href="https://paystack.com/terms" target="_blank" rel="noopener noreferrer" class="underline hover:text-brand-yellow-dark">https://paystack.com/terms</a>.
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('All personal information that you provide to us must be true, complete, and accurate, and you must notify us of any changes to such personal information.') }}
        </p>

        <p class="leading-relaxed mb-1"><strong>{{ __('Information automatically collected') }}</strong></p>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: Some information — such as your Internet Protocol (IP) address and/or browser and device characteristics — is collected automatically when you visit our Services.') }}</em>
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('We automatically collect certain information when you visit, use, or navigate the Services. This information does not reveal your specific identity (like your name or contact information) but may include device and usage information, such as your IP address, browser and device characteristics, operating system, language preferences, referring URLs, device name, country, location, information about how and when you use our Services, and other technical information. This information is primarily needed to maintain the security and operation of our Services, and for our internal analytics and reporting purposes.') }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('Like many businesses, we also collect information through cookies and similar technologies. You can find out more about this in our') }}
            <a href="{{ route('policy.cookies') }}" class="underline hover:text-brand-yellow-dark">{{ __('Cookie Notice') }}</a>.
        </p>
        <p class="leading-relaxed mb-2">{{ __('The information we collect includes:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __("Log and Usage Data. Log and usage data is service-related, diagnostic, usage, and performance information our servers automatically collect when you access or use our Services and which we record in log files. Depending on how you interact with us, this log data may include your IP address, device information, browser type, and settings and information about your activity in the Services (such as the date/time stamps associated with your usage, pages and files viewed, searches, and other actions you take such as which features you use), device event information (such as system activity, error reports (sometimes called 'crash dumps'), and hardware settings).") }}</li>
            <li>{{ __('Device Data. We collect device data such as information about your computer, phone, tablet, or other device you use to access the Services. Depending on the device used, this device data may include information such as your IP address (or proxy server), device and application identification numbers, location, browser type, hardware model, Internet service provider and/or mobile carrier, operating system, and system configuration information.') }}</li>
            <li>{{ __("Location Data. We collect location data such as information about your device's location, which can be either precise or imprecise. How much information we collect depends on the type and settings of the device you use to access the Services. For example, we may use GPS and other technologies to collect geolocation data that tells us your current location (based on your IP address). You can opt out of allowing us to collect this information either by refusing access to the information or by disabling your Location setting on your device. However, if you choose to opt out, you may not be able to use certain aspects of the Services.") }}</li>
        </ul>

        <h2 id="how-we-process" class="text-lg font-bold mt-8 mb-3">{{ __('2. How do we process your information?') }}</h2>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: We process your information to provide, improve, and administer our Services, communicate with you, for security and fraud prevention, and to comply with law. We may also process your information for other purposes with your consent.') }}</em>
        </p>
        <p class="leading-relaxed mb-2">{{ __('We process your personal information for a variety of reasons, depending on how you interact with our Services, including:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('To facilitate account creation and authentication and otherwise manage user accounts. We may process your information so you can create and log in to your account, as well as keep your account in working order.') }}</li>
            <li>{{ __('To deliver and facilitate delivery of services to the user. We may process your information to provide you with the requested service.') }}</li>
            <li>{{ __('To respond to user inquiries/offer support to users. We may process your information to respond to your inquiries and solve any potential issues you might have with the requested service.') }}</li>
            <li>{{ __('To send administrative information to you. We may process your information to send you details about our products and services, changes to our terms and policies, and other similar information.') }}</li>
            <li>{{ __('To fulfil and manage your orders. We may process your information to fulfil and manage your orders, payments, returns, and exchanges made through the Services.') }}</li>
            <li>{{ __('To enable user-to-user communications. We may process your information if you choose to use any of our offerings that allow for communication with another user.') }}</li>
            <li>{{ __("To send you marketing and promotional communications. We may process the personal information you send to us for our marketing purposes, if this is in accordance with your marketing preferences. You can opt out of our marketing emails at any time. For more information, see 'What are your privacy rights?' below.") }}</li>
            <li>
                {{ __('To deliver targeted advertising to you. We may process your information to develop and display personalised content and advertising tailored to your interests, location, and more. For more information see our') }}
                <a href="{{ route('policy.cookies') }}" class="underline hover:text-brand-yellow-dark">{{ __('Cookie Notice') }}</a>.
            </li>
            <li>{{ __('To evaluate and improve our Services, products, marketing, and your experience. We may process your information when we believe it is necessary to identify usage trends, determine the effectiveness of our promotional campaigns, and to evaluate and improve our Services, products, marketing, and your experience.') }}</li>
            <li>{{ __('To determine the effectiveness of our marketing and promotional campaigns. We may process your information to better understand how to provide marketing and promotional campaigns that are most relevant to you.') }}</li>
        </ul>

        <h2 id="sharing" class="text-lg font-bold mt-8 mb-3">{{ __('3. When and with whom do we share your personal information?') }}</h2>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: We may share information in specific situations described in this section and/or with the following third parties.') }}</em>
        </p>
        <p class="leading-relaxed mb-2">{{ __('We may need to share your personal information in the following situations:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('Business Transfers. We may share or transfer your information in connection with, or during negotiations of, any merger, sale of company assets, financing, or acquisition of all or a portion of our business to another company.') }}</li>
        </ul>

        <h2 id="cookies" class="text-lg font-bold mt-8 mb-3">{{ __('4. Do we use cookies and other tracking technologies?') }}</h2>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: We may use cookies and other tracking technologies to collect and store your information.') }}</em>
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('We may use cookies and similar tracking technologies (like web beacons and pixels) to gather information when you interact with our Services. Some online tracking technologies help us maintain the security of our Services and your account, prevent crashes, fix bugs, save your preferences, and assist with basic site functions.') }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('We also permit third parties and service providers to use online tracking technologies on our Services for analytics and advertising, including to help manage and display advertisements or to tailor advertisements to your interests. The third parties and service providers use their technology to provide advertising about products and services tailored to your interests which may appear either on our Services or on other websites.') }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('Specific information about how we use such technologies and how you can refuse certain cookies is set out in our') }}
            <a href="{{ route('policy.cookies') }}" class="underline hover:text-brand-yellow-dark">{{ __('Cookie Notice') }}</a>.
        </p>
        <p class="leading-relaxed mb-1"><strong>{{ __('Google Analytics') }}</strong></p>
        <p class="leading-relaxed mb-4">
            {{ __('We may share your information with Google Analytics to track and analyse the use of the Services. The Google Analytics Advertising Features that we may use include: Google Analytics Demographics and Interests Reporting, Google Display Network Impressions Reporting and Remarketing with Google Analytics. To opt out of being tracked by Google Analytics across the Services, visit') }}
            <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener noreferrer" class="underline hover:text-brand-yellow-dark">https://tools.google.com/dlpage/gaoptout</a>.
            {{ __('You can opt out of Google Analytics Advertising Features through Ads Settings and Ad Settings for mobile apps. Other opt out means include') }}
            <a href="http://optout.networkadvertising.org/" target="_blank" rel="noopener noreferrer" class="underline hover:text-brand-yellow-dark">http://optout.networkadvertising.org/</a>
            {{ __('and') }}
            <a href="http://www.networkadvertising.org/mobile-choice" target="_blank" rel="noopener noreferrer" class="underline hover:text-brand-yellow-dark">http://www.networkadvertising.org/mobile-choice</a>.
            {{ __('For more information on the privacy practices of Google, please visit the Google Privacy & Terms page.') }}
        </p>

        <h2 id="retention" class="text-lg font-bold mt-8 mb-3">{{ __('5. How long do we keep your information?') }}</h2>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: We keep your information for as long as necessary to fulfil the purposes outlined in this Privacy Notice unless otherwise required by law.') }}</em>
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('We will only keep your personal information for as long as it is necessary for the purposes set out in this Privacy Notice, unless a longer retention period is required or permitted by law (such as tax, accounting, or other legal requirements). No purpose in this notice will require us keeping your personal information for longer than the period of time in which users have an account with us.') }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('When we have no ongoing legitimate business need to process your personal information, we will either delete or anonymise such information, or, if this is not possible (for example, because your personal information has been stored in backup archives), then we will securely store your personal information and isolate it from any further processing until deletion is possible.') }}
        </p>

        <h2 id="safety" class="text-lg font-bold mt-8 mb-3">{{ __('6. How do we keep your information safe?') }}</h2>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: We aim to protect your personal information through a system of organisational and technical security measures.') }}</em>
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('We have implemented appropriate and reasonable technical and organisational security measures designed to protect the security of any personal information we process. However, despite our safeguards and efforts to secure your information, no electronic transmission over the Internet or information storage technology can be guaranteed to be 100% secure, so we cannot promise or guarantee that hackers, cybercriminals, or other unauthorised third parties will not be able to defeat our security and improperly collect, access, steal, or modify your information. Although we will do our best to protect your personal information, transmission of personal information to and from our Services is at your own risk. You should only access the Services within a secure environment.') }}
        </p>

        <h2 id="minors" class="text-lg font-bold mt-8 mb-3">{{ __('7. Do we collect information from minors?') }}</h2>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: We do not knowingly collect data from or market to children under 18 years of age.') }}</em>
        </p>
        <p class="leading-relaxed mb-4">
            {{ __("We do not knowingly collect, solicit data from, or market to children under 18 years of age, nor do we knowingly sell such personal information. By using the Services, you represent that you are at least 18 or that you are the parent or guardian of such a minor and consent to such minor dependent's use of the Services. If we learn that personal information from users less than 18 years of age has been collected, we will deactivate the account and take reasonable measures to promptly delete such data from our records. If you become aware of any data we may have collected from children under age 18, please contact us at") }}
            <a href="mailto:info@yesmyshawarma.com" class="underline hover:text-brand-yellow-dark">info@yesmyshawarma.com</a>.
        </p>

        <h2 id="rights" class="text-lg font-bold mt-8 mb-3">{{ __('8. What are your privacy rights?') }}</h2>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: You may review, change, or terminate your account at any time, depending on your country, province, or state of residence.') }}</em>
        </p>
        <p class="leading-relaxed mb-4">
            {{ __("Withdrawing your consent: If we are relying on your consent to process your personal information, which may be express and/or implied consent depending on the applicable law, you have the right to withdraw your consent at any time. You can withdraw your consent at any time by contacting us by using the contact details provided in the section 'How can you contact us about this notice?' below.") }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('However, please note that this will not affect the lawfulness of the processing before its withdrawal nor, when applicable law allows, will it affect the processing of your personal information conducted in reliance on lawful processing grounds other than consent.') }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __("Opting out of marketing and promotional communications: You can unsubscribe from our marketing and promotional communications at any time by clicking on the unsubscribe link in the emails that we send, replying 'STOP' or 'UNSUBSCRIBE' to the SMS messages that we send — Text About order updates, Login OTP — or by contacting us using the details provided in the section 'How can you contact us about this notice?' below. You will then be removed from the marketing lists. However, we may still communicate with you — for example, to send you service-related messages that are necessary for the administration and use of your account, to respond to service requests, or for other non-marketing purposes.") }}
        </p>
        <p class="leading-relaxed mb-1"><strong>{{ __('Account Information') }}</strong></p>
        <p class="leading-relaxed mb-2">{{ __('If you would at any time like to review or change the information in your account or terminate your account, you can:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('Log in to your account settings and update your user account.') }}</li>
        </ul>
        <p class="leading-relaxed mb-4">
            {{ __('Upon your request to terminate your account, we will deactivate or delete your account and information from our active databases. However, we may retain some information in our files to prevent fraud, troubleshoot problems, assist with any investigations, enforce our legal terms and/or comply with applicable legal requirements.') }}
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('Cookies and similar technologies: Most Web browsers are set to accept cookies by default. If you prefer, you can usually choose to set your browser to remove cookies and to reject cookies. If you choose to remove cookies or reject cookies, this could affect certain features or services of our Services. For further information, please see our') }}
            <a href="{{ route('policy.cookies') }}" class="underline hover:text-brand-yellow-dark">{{ __('Cookie Notice') }}</a>.
        </p>
        <p class="leading-relaxed mb-4">
            {{ __('If you have questions or comments about your privacy rights, you may email us at') }}
            <a href="mailto:info@yesmyshawarma.com" class="underline hover:text-brand-yellow-dark">info@yesmyshawarma.com</a>,
            <a href="mailto:yesmygrill@gmail.com" class="underline hover:text-brand-yellow-dark">yesmygrill@gmail.com</a>,
            <a href="mailto:yesmyshawarma@gmail.com" class="underline hover:text-brand-yellow-dark">yesmyshawarma@gmail.com</a>.
        </p>

        <h2 id="dnt" class="text-lg font-bold mt-8 mb-3">{{ __('9. Controls for Do-Not-Track features') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __("Most web browsers and some mobile operating systems and mobile applications include a Do-Not-Track ('DNT') feature or setting you can activate to signal your privacy preference not to have data about your online browsing activities monitored and collected. At this stage, no uniform technology standard for recognising and implementing DNT signals has been finalised. As such, we do not currently respond to DNT browser signals or any other mechanism that automatically communicates your choice not to be tracked online. If a standard for online tracking is adopted that we must follow in the future, we will inform you about that practice in a revised version of this Privacy Notice.") }}
        </p>

        <h2 id="updates" class="text-lg font-bold mt-8 mb-3">{{ __('10. Do we make updates to this notice?') }}</h2>
        <p class="leading-relaxed mb-4">
            <em>{{ __('In Short: Yes, we will update this notice as necessary to stay compliant with relevant laws.') }}</em>
        </p>
        <p class="leading-relaxed mb-4">
            {{ __("We may update this Privacy Notice from time to time. The updated version will be indicated by an updated 'Revised' date at the top of this Privacy Notice. If we make material changes to this Privacy Notice, we may notify you either by prominently posting a notice of such changes or by directly sending you a notification. We encourage you to review this Privacy Notice frequently to be informed of how we are protecting your information.") }}
        </p>

        <h2 id="contact" class="text-lg font-bold mt-8 mb-3">{{ __('11. How can you contact us about this notice?') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('If you have questions or comments about this notice, you may email us at') }}
            <a href="mailto:info@yesmyshawarma.com" class="underline hover:text-brand-yellow-dark">info@yesmyshawarma.com</a>
            {{ __('or contact us by post at:') }}
        </p>
        <p class="leading-relaxed mb-4">
            Yes My Grill<br>
            GA Odumase<br>
            Accra, Accra 00233<br>
            Ghana
        </p>

        <h2 id="review" class="text-lg font-bold mt-8 mb-3">{{ __('12. How can you review, update, or delete the data we collect from you?') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Based on the applicable laws of your country, you may have the right to request access to the personal information we collect from you, details about how we have processed it, correct inaccuracies, or delete your personal information. You may also have the right to withdraw your consent to our processing of your personal information. These rights may be limited in some circumstances by applicable law. To request to review, update, or delete your personal information, please fill out and submit a') }}
            <a href="https://app.termly.io/dsar/a89d8031-2a27-49af-aa84-4a12795e8ec5" target="_blank" rel="noopener noreferrer" class="underline hover:text-brand-yellow-dark">{{ __('data subject access request') }}</a>.
        </p>

        <p class="text-sm text-brand-gray-500 mt-8">
            {{ __("This Privacy Policy was created using Termly's") }}
            <a href="https://termly.io/products/privacy-policy-generator/" target="_blank" rel="noopener external" class="underline hover:text-brand-yellow-dark">{{ __('Privacy Policy Generator') }}</a>.
        </p>
    </div>
</x-customer-layout>
