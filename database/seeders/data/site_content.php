<?php

/*
 * Yes My Shawarma's website content as it was before Express POS step 2c:
 * page text, search titles and descriptions, the FAQ and the About page
 * figures. Copied into site_pages, faq_items and settings by the
 * 2026_11_15_120000_create_site_pages_and_faq_items migration, on an install
 * that already has orders. Not used by a new install.

 * Placeholders such as :name and :area are filled in when the page shows
 * (SitePageService::PAGES lists which each page takes).
 */

return [
    'pages' => [
        'home' => [
            'meta_title' => ':name: Shawarma, Burgers & More in Accra',
            'meta_description' => 'Order shawarma, burgers, loaded fries and fresh juices online from :name in :branches, Accra. Delivery or pickup, paid by mobile money, card or cash.',
        ],
        'menu' => [
            'meta_title' => 'Menu: Shawarma, Burgers & Fresh Juices · :name',
            'meta_description' => 'The full :name menu: chicken and beef shawarma, burgers, hot dogs, loaded fries, sandwiches and fresh juices. Order online for delivery or pickup in Accra.',
        ],
        'menu-item' => [
            'meta_title' => ':item: Order Online in Accra · :name',
        ],
        'branches' => [
            'meta_title' => 'Our Branches in Accra · :name',
            'meta_description' => 'Find :name near you: :branches. Opening hours, directions and online ordering for delivery or pickup.',
        ],
        'branch' => [
            'title' => 'Shawarma in :area',
            'meta_title' => 'Shawarma & Burgers in :area, Accra · :name',
            'meta_description' => ':name :branch, :address. Order shawarma, burgers and fresh juices online for delivery or pickup. Call :phone.',
            'body' => 'Fresh chicken and beef shawarma, burgers, loaded fries and fresh juices at our :branch branch, :address. Order online for delivery to your door or pickup from the branch, paying by mobile money, card or cash.
',
        ],
        'contact' => [
            'meta_title' => 'Contact us · :name',
            'meta_description' => 'Contact :name: branch phone numbers, addresses, opening hours and directions in Accra.',
        ],
        'reviews' => [
            'meta_title' => 'Reviews · :name',
            'meta_description' => 'Customer reviews and ratings for :name\'s shawarma, burgers and service across our Accra branches.',
        ],
        'faq' => [
            'title' => 'Frequently asked questions',
            'meta_title' => 'FAQ · :name',
            'meta_description' => 'Answers to common questions about ordering from :name: delivery, pickup, payment by mobile money, card or cash, opening hours and refunds.',
        ],
        'about' => [
            'title' => 'About us',
            'meta_title' => 'About us · :name',
            'meta_description' => 'The story behind :name, serving shawarma, burgers and fresh juices from our branches across Accra.',
            'body' => 'Yes! My Shawarma began as a bold idea inside Yes! My Grill to serve exceptional shawarma wraps built on two things too often missing from fast food: real health and true value for money.

We started small. One joint, one conviction: that a shawarma could be crafted, not just assembled. Our wraps tasted different, and at first, different drew questions. Some customers weren\'t sure what to make of a flavour that didn\'t copy everyone else. But we kept serving, kept explaining, and something began to shift. One bite at a time, people understood, this wasn\'t just another wrap. This was a recipe with a craft behind it, a quality they couldn\'t get anywhere else. The questions turned into cravings, and the first-timers turned into regulars.

Those returning customers gave us our first real hope, and they\'ve carried us ever since. **We served over 5,000 in-house orders before our second branch even opened**, launching in December and opening our second location by March. Today we run **three branches** and counting, with more strategic locations on the way to bring our wraps to even more of Accra.

Along the way, we\'ve built more than a menu. **We\'ve created jobs for many Ghanaian youth**, and together we show up every day to deliver the same thing: the best service, aimed squarely at one goal, a customer who leaves satisfied and comes back for more.

We\'re famous for our recipe, and we\'re proud of it. We\'re not like every other food joint. Our craft is our own and that\'s exactly the point. We\'re just getting started.

**One bite, and you\'ll want more.**
',
        ],
        'terms' => [
            'title' => 'Terms & Conditions',
            'meta_title' => 'Terms & Conditions · :name',
            'meta_description' => 'The terms that apply when you order from :name online, for delivery or pickup.',
            'body' => '*Last updated: 2 September 2026*

Welcome to Yes! My Shawarma. These Terms and Conditions ("Terms") govern your use of our website, www.yesmyshawarma.com (the "Site"), and the services we provide through it. By accessing or using the Site, you agree to be bound by these Terms. If you do not agree, please do not use the Site.

## 1. Eligibility and acceptance

By using the Site, you confirm that you are at least 18 years old, or that you are using it with the permission of a parent or guardian. If you are using the Site on behalf of a business, you confirm that you have the authority to bind that business to these Terms.

## 2. Your account

To use certain features of the Site, you may need to create an account. You are responsible for keeping your account details confidential and for everything that happens under your account. Please let us know straight away if you notice any unauthorised use of your account.

## 3. Ordering and payment

All orders you place through the Site are subject to our acceptance, and we may refuse or cancel any order at our discretion. Prices may change without notice. Payment is made at the time you place your order, using the payment methods offered on the Site, including card and mobile money via our secure payment provider.

## 4. Delivery

Delivery times are estimates and may vary depending on your location and other factors outside our control. While we always aim to deliver on time, we are not liable for delays.

## 5. Refunds and cancellations

If you wish to cancel an order, please contact us as soon as possible. Refunds are handled in line with our [Refund Policy](/refund-policy), which is available on the Site.

## 6. Intellectual property

All content on the Site, including text, graphics, logos, images, and software, belongs to Yes! My Shawarma or its licensors and is protected by copyright and other intellectual property laws. You may not use any of this content without our express written permission.

## 7. Limitation of liability

To the fullest extent permitted by law, Yes! My Shawarma shall not be liable for any indirect, incidental, special, or consequential damages arising out of or in connection with your use of the Site or our services.

## 8. Indemnification

You agree to defend, indemnify, and hold harmless Yes! My Shawarma, its affiliates, and their respective officers, directors, employees, and agents from any claims, damages, liabilities, losses, and expenses arising out of your use of the Site or your breach of these Terms.

## 9. Governing law

These Terms are governed by and interpreted in accordance with the laws of the Republic of Ghana, without regard to its conflict of law principles. You agree that the courts of Ghana shall have jurisdiction over any dispute arising from these Terms.

## 10. Changes to these Terms

We may update these Terms at any time. When we do, we\'ll post the revised Terms on this page and update the "Last updated" date above. We encourage you to review these Terms from time to time. Continuing to use the Site after changes are posted means you accept the updated Terms.

## 11. Contact us

If you have any questions about these Terms, please contact us:

- Email: [info@yesmyshawarma.com](mailto:info@yesmyshawarma.com)
- Phone: [024 363 5265](tel:+233243635265)
',
        ],
        'refunds' => [
            'title' => 'Refund Policy',
            'meta_title' => 'Refund Policy · :name',
            'meta_description' => 'How refunds work at :name: when you qualify, how to ask, and how long it takes.',
            'body' => '*Effective date: 2 September 2026*

At Yes! My Shawarma, we are committed to serving great food and giving you a great experience every time you order. We know things occasionally go wrong, and when they do, we want to put them right. This policy explains how we handle refunds and how to raise a concern with us.

## 1. Wrong or incomplete orders

We work hard to get every order right. If you receive the wrong item, or your order arrives incomplete or incorrect:

- **Tell us quickly.** Contact our customer service team within 1 hour of delivery, by phone or email.
- **Share the details.** Have your order number ready, tell us exactly what was wrong, and send a photo if it helps.
- **We\'ll fix it.** Once we\'ve confirmed the issue, we\'ll send you the correct item at no extra cost.

## 2. Quality concerns

We take real pride in the quality of our food. If something isn\'t up to standard:

- **Show us the problem.** Take a clear photo of the item, highlighting the issue (for example, undercooked or spoiled).
- **Get in touch.** Contact us within 1 hour of delivery with your order number and photos.
- **We\'ll review it.** Our team will look into your report. If we agree the quality falls short of our standards, we\'ll offer a replacement or a full refund, depending on the situation.

## 3. Delivery delays

Getting your food to you on time matters to us. If your order is significantly late:

- **Let us know.** If your order is more than 60 minutes past the estimated delivery time, please contact our customer service team.
- **We\'ll make it right.** Depending on the circumstances, we may offer a discount on your next order or a partial refund for the inconvenience.

## 4. How refunds are processed

- **Processing time.** Once a refund is approved, we\'ll process it back to your original payment method (card or mobile money) within 2 business days. Depending on your bank or mobile money provider, the funds may take a little longer to reflect.
- **Confirmation.** We\'ll send you a message by SMS or email once your refund has been processed.

## 5. When refunds don\'t apply

Some situations don\'t qualify for a refund:

- **Reported too late.** Orders confirmed as delivered that are not reported within the time frames set out above.
- **Personal taste.** Refunds aren\'t given for personal preferences, for example a topping you requested but then didn\'t enjoy.
- **Third-party orders.** If you ordered through a third-party delivery platform rather than directly from us, that platform\'s own refund policy applies, and refunds must be requested through them.

## 6. How to reach us

We\'re here to help. To report an issue or request a refund, contact our customer service team:

- Phone: [024 363 5265](tel:+233243635265)
- Email: [info@yesmyshawarma.com](mailto:info@yesmyshawarma.com)
- WhatsApp: available during business hours.

## 7. Your satisfaction

Your satisfaction is what drives us. We take every concern seriously and genuinely welcome your feedback. If you have ideas on how we can serve you better, please tell us.

## 8. Changes to this policy

We may update this Refund Policy from time to time. Any changes will be posted here with a revised effective date, so we encourage you to check back now and then.

Thank you for choosing Yes! My Shawarma. We appreciate your business and are committed to making every order a good one.
',
        ],
        'privacy' => [
            'title' => 'Privacy Policy',
            'meta_title' => 'Privacy Policy · :name',
            'meta_description' => 'How :name collects, uses and protects your personal information when you order online.',
            'body' => '*Last updated: 2 September 2026*

At Yes! My Shawarma, we take your privacy seriously. This Privacy Policy explains how we collect, use, share, and protect your personal information when you interact with us, through our website, our mobile app, our branches, our ordering channels, or any other platform we operate. By using our services, you confirm that you have read and understood this policy.

Yes! My Shawarma is operated by Yes! My Grill in Accra, Ghana.

## Table of contents

1. [Information we collect](#information-we-collect)
2. [How we use your information](#how-we-use)
3. [How we share your information](#how-we-share)
4. [How long we keep your information](#retention)
5. [How we protect your information](#protection)
6. [Your rights](#rights)
7. [Marketing and your choices](#marketing)
8. [Children\'s privacy](#children)
9. [Third-party links](#third-party-links)
10. [Changes to this policy](#changes)
11. [Contact us](#contact)

## 1. Information we collect {#information-we-collect}

We collect different types of information so we can serve you well and keep improving. It falls into the following categories.

### 1.1 Personal information

When you create an account, place an order, or get in touch with us, we may collect:

- Your name
- Email address
- Phone number
- Delivery address

### 1.2 Payment information

To take payment for your orders, we work with Paystack, our secure payment provider, to process card and mobile money transactions. Depending on how you choose to pay, this may involve your mobile money number or card details. Your card and payment credentials are handled directly by Paystack under encryption. We do not store full card numbers on our own systems.

### 1.3 Order information

We keep information tied to your orders, including:

- The menu items you order
- Any special instructions or preferences
- Your order history
- Delivery instructions

This lets us prepare your orders accurately and offer you a more personal service over time.

### 1.4 Usage data

When you visit our website or use our app, we automatically collect technical information such as:

- Your IP address
- Browser type and version
- The pages you view and how long you spend on them
- The date and time of your visit
- The website that referred you to us

We use this to spot trends, fix problems, and make our platform easier to use.

### 1.5 Cookies and similar technologies

We use cookies and similar tools to improve your experience on our website. Cookies are small files stored on your device that help us remember your preferences and keep things working smoothly. You can control or switch off cookies through your browser settings, although some features may not work properly if you do.

## 2. How we use your information {#how-we-use}

We use the information we collect to:

- **Fulfil your orders:** prepare, process, and deliver what you\'ve ordered.
- **Support you:** respond to your questions, complaints, and feedback.
- **Manage your account:** let you sign in, view your order history, and save your preferences.
- **Send you updates:** share offers, news, and promotions, but only if you\'ve chosen to receive them.
- **Personalise your experience:** tailor what we show you based on your past orders and preferences.
- **Improve our platform:** understand how our website and app are used so we can make them better.
- **Meet our legal obligations:** comply with the laws and regulations that apply to us.

## 3. How we share your information {#how-we-share}

We do not sell your personal information. We share it only in the following situations:

- **Service providers:** trusted partners who help us run the business, such as our payment provider (Paystack), our delivery riders and logistics partners, our SMS and messaging providers, and our technology vendors. They may only use your information to carry out the specific service we\'ve asked them to provide.
- **Legal reasons:** where we are required to by law, regulation, or a valid legal request, or where it is necessary to protect the rights, property, or safety of Yes! My Shawarma, our customers, or the public.
- **Business changes:** if our business is reorganised, merged, or acquired, your information may be transferred as part of that process, subject to the protections in this policy.

## 4. How long we keep your information {#retention}

We keep your personal information only for as long as we need it to fulfil your orders, run your account, meet our legal and tax obligations, and resolve any disputes. When we no longer need it, we securely delete it or anonymise it so it can no longer identify you. If you close your account, we may keep limited records where the law requires us to.

## 5. How we protect your information {#protection}

We use appropriate technical and organisational measures to protect your information against loss, misuse, and unauthorised access. These include encryption of sensitive data, restricted staff access on a need-to-know basis, secure hosting, and processing payments through trusted providers such as Paystack. No system can be guaranteed to be completely secure, but we work continuously to keep your information safe and to respond quickly if a problem arises.

## 6. Your rights {#rights}

Under Ghana\'s Data Protection Act, 2012 (Act 843), you have rights over the personal information we hold about you. You may:

- **Access** the personal information we hold about you.
- **Correct** information that is inaccurate or out of date.
- **Request deletion** of your information where there is no legal reason for us to keep it.
- **Object to or restrict** certain uses of your information, including marketing.
- **Withdraw consent** at any time where we rely on your consent to process your information.
- **Complain** to the Data Protection Commission if you believe we have handled your information improperly.

To exercise any of these rights, contact us using the details below. We will respond within a reasonable time and in line with applicable law.

## 7. Marketing and your choices {#marketing}

We will only send you marketing messages by email, SMS, or other channels if you have chosen to receive them. You can opt out at any time by following the unsubscribe instructions in the message, replying to stop, or contacting us directly. Opting out of marketing will not affect the essential messages we send about your orders, such as confirmations and delivery updates.

## 8. Children\'s privacy {#children}

Our services are not directed at children under the age of 18, and we do not knowingly collect personal information from them. If you believe a child has provided us with personal information, please contact us and we will take steps to delete it.

## 9. Third-party links {#third-party-links}

Our website or app may contain links to other websites or services that we do not operate. This Privacy Policy does not apply to those third parties, and we are not responsible for their privacy practices. We encourage you to read the privacy policy of any website you visit.

## 10. Changes to this policy {#changes}

We may update this Privacy Policy from time to time to reflect changes in our practices, technology, or the law. When we do, we will revise the "Last updated" date at the top of this page, and where the changes are significant, we will let you know through our website, app, or other appropriate means. We encourage you to review this policy periodically.

## 11. Contact us {#contact}

If you have any questions about this Privacy Policy or how we handle your information, please get in touch:

**Yes! My Shawarma**

- Email: [info@yesmyshawarma.com](mailto:info@yesmyshawarma.com)
- Phone: [026 370 2929](tel:+233263702929), [024 363 5265](tel:+233243635265), [053 190 7747](tel:+233531907747)
- Address: GA Odumase, Adjacent Star Oil, Accra, Ghana
',
        ],
        'cookies' => [
            'title' => 'Cookie Policy',
            'meta_title' => 'Cookie Policy · :name',
            'meta_description' => 'Which cookies :name uses on this website, why, and how to control them.',
            'body' => '*Last updated: 10 November 2026*

At Yes! My Shawarma, we care about your privacy and want to be open about the technologies we use. This Cookie Policy explains what cookies are, how we use them, and the choices you have.

## 1. What are cookies?

Cookies are small text files placed on your device (your computer, tablet, or phone) when you visit a website. They\'re widely used to help websites work properly and to give site owners useful information. Cookies let us recognise your device and remember things like your preferences and past actions, so your next visit is smoother.

## 2. How we use cookies

We use cookies to improve your browsing experience and make our services better. The cookies we use fall into these categories:

1. **Essential cookies.** These are needed for our website to work. They help you move around the site and use key features such as secure areas and your cart. Without them, parts of the site simply won\'t function.
2. **Performance cookies.** These tell us how visitors use our site: which pages are popular, where people run into trouble, so we can keep improving how it works.
3. **Functional cookies.** These remember your choices, such as your language or region, so we can give you a more personal experience. For example, when you choose a branch (or let us find your nearest one), we remember that branch on your device for up to a year so you don\'t have to pick it again. We never store your location. Declining non-essential cookies turns this off.
4. **Targeting cookies.** These help us show you offers and adverts that are relevant to you, limit how often you see the same ad, and measure how well our campaigns perform.

## 3. Third-party cookies

Some cookies are set by trusted third parties acting on our behalf. For example, we may use analytics tools such as Google Analytics to understand how our site is used, and our payment provider (Paystack) may set cookies to process your payment securely. These third parties may use cookies to collect information about your activity across different websites. Their use of your information is governed by their own privacy and cookie policies.

## 4. Your consent and choices

When you first visit our website, we\'ll ask for your consent to use non-essential cookies (performance, functional, and targeting). You can accept, decline, or adjust your choices at any time. Essential cookies don\'t require consent, because the site can\'t run without them.

## 5. Managing cookies

You can also control cookies through your browser settings, where most browsers let you view, manage, delete, or block cookies for any website. Keep in mind that if you block or delete certain cookies, some features of our site may not work as they should.

## 6. Changes to this Cookie Policy

We may update this Cookie Policy from time to time to reflect changes in our practices or the law. When we do, we\'ll revise the "Last updated" date at the top of this page.

## 7. Contact us

If you have any questions about how we use cookies, please contact us at [info@yesmyshawarma.com](mailto:info@yesmyshawarma.com) or on [024 363 5265](tel:+233243635265).
',
        ],
        'default' => [
            'meta_description' => 'Shawarma, burgers and fresh juices in Accra. Order online for delivery or pickup.',
        ],
        'llms' => [
            'body' => '# :name

> Yes My Shawarma is a shawarma, burger, and fast-food restaurant with multiple branches in Accra, Ghana, offering online ordering for pickup and delivery.

## Ordering

- [Menu](:url/menu): Full menu of shawarma, burgers, hot dogs, loaded fries, sandwiches, and drinks, with prices in Ghanaian cedis (GHS).
- [Branches](:url/branches): Branch locations, addresses, phone numbers, and opening hours across Accra.
- [Track an order](:url/track): Look up the status of an existing order.

## About

- [About us](:url/about): Background on the business and its story.
- [Contact us](:url/contact): Contact form and branch phone numbers.
- [FAQ](:url/faq): Answers to common ordering questions.
- [Customer reviews](:url/reviews): Public customer reviews and ratings.

## Policies

- [Terms & Conditions](:url/terms-and-conditions)
- [Refund Policy](:url/refund-policy)
- [Privacy Policy](:url/privacy-policy)
- [Cookie Policy](:url/cookie-policy)

## Notes

- Payment: cash on pickup/delivery, or online via card/Mobile Money through Paystack.
- Delivery is available in select areas of Accra; pickup is available at every branch during its opening hours.
',
        ],
    ],

    'faq' => [
        ['question' => 'Do I need an account to order?', 'answer' => 'No, check out as a guest with just your phone number. If you set a password later using that same number, your past orders are automatically linked to the new account.'],
        ['question' => 'How can I pay?', 'answer' => 'Cash on pickup or delivery, or pay online upfront with Paystack. Cards and Mobile Money (MoMo) are both supported.'],
        ['question' => 'Do you deliver, or is it pickup only?', 'answer' => 'Both. Pickup is always available at any branch. Delivery is offered where we have an active delivery area, with the fee worked out by distance from the branch at checkout.'],
        ['question' => 'How do I track my order?', 'answer' => 'Your order confirmation includes a tracking link. You can also look it up anytime from Track order using the phone number you ordered with.'],
        ['question' => 'Which branches can I order from?', 'answer' => 'Ga Odumase and Pokuase Y-Junction are open now, with another branch on the way. Opening hours for each are shown on the Branches page.'],
        ['question' => 'Something\'s wrong with my order, what do I do?', 'answer' => 'Call or WhatsApp the branch you ordered from, or send a message through Contact us, and our team will sort out a refund or replacement.'],
    ],

    'settings' => [
        'about_mission' => 'We\'re here to turn a simple craving into a reliable favourite, delivering bold, quality meals quickly and consistently across every neighbourhood we reach.',
        'about_vision' => 'To be the first name Ghanaians think of when they crave quality authentic meals, growing from Accra across the country and beyond.',
        'about_founded_year' => '2023',
        'about_customers_label' => '5,000+',
        'business_city' => 'Accra',
        'business_region' => 'Greater Accra',
        'business_cuisines' => 'Shawarma, Burgers, Fast food',
    ],
];
