<?php

/*
 * Starter website content for a new shop with the Website module (R1,
 * 2026-11-25): as site_content_starter.php, written so it's true for any
 * shop. The owner edits it under Website content. :name is the business
 * name.
 */

return [
    'about' => <<<'MD'
Welcome to **:name**.

Browse what we have in stock, order in a few taps and pay the way that suits you.

Collect your order from us, or have it delivered where we deliver. Thank you for choosing :name.
MD,

    'faq' => [
        ['How do I order?', 'Find your products, add them to your cart and check out. You can collect your order, or have it delivered where we deliver.'],
        ['How can I pay?', 'Pay at checkout using the options shown there. Every order shows its total, including any delivery fee, before you pay.'],
        ['Is everything on the website in stock?', 'The website shows what each branch has available. If something sells out after you order, we will contact you before your order goes any further.'],
        ['Do you deliver to my area?', 'At checkout, choose your area: if we deliver there, the delivery fee is shown before you pay.'],
        ['Something is wrong with my order. What should I do?', 'Contact us straight away using the details on our Contact page, with your order number, and we will put it right.'],
    ],
];
