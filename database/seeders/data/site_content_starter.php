<?php

/*
 * Starter website content for a new client with the Website module
 * (owner, 2026-11-24): a plain About page and FAQ in the business's own
 * name, written so they're true for any food business. Seeded once by
 * ClientSetupService at onboarding; the owner edits them under Website
 * content. :name is the business name, filled in at seeding.
 *
 * Policies (terms, refunds, privacy, cookies) are deliberately not here:
 * they're legal text each business must write or approve itself.
 */

return [
    'about' => <<<'MD'
Welcome to **:name**.

We cook every order fresh and get it to you quickly, whether you collect it, eat in or have it delivered.

Order online in a few taps and pay the way that suits you. Thank you for choosing :name.
MD,

    'faq' => [
        ['How do I order?', 'Choose your items on our menu, add them to your cart and check out. You can collect your order, or have it delivered where we deliver.'],
        ['How can I pay?', 'Pay at checkout using the options shown there. Every order shows its total, including any delivery fee, before you pay.'],
        ['How long will my order take?', 'Most orders are ready in a short while. You can follow yours on its tracking page from the moment it is placed.'],
        ['Do you deliver to my area?', 'At checkout, choose your area: if we deliver there, the delivery fee is shown before you pay.'],
        ['Something is wrong with my order. What should I do?', 'Contact us straight away using the details on our Contact page, with your order number, and we will put it right.'],
    ],
];
