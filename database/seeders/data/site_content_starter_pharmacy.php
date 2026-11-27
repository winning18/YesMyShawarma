<?php

/*
 * Starter website content for a new pharmacy with the Website module
 * (R1, 2026-11-25): as site_content_starter.php, written so it's true for
 * any pharmacy and makes no health claims. The owner edits it under
 * Website content. :name is the business name.
 */

return [
    'about' => <<<'MD'
Welcome to **:name**.

We stock everyday medicines, health and personal care products, and our pharmacists are here to help you choose what's right for you.

Order online and collect from us, or have it delivered where we deliver. Thank you for choosing :name.
MD,

    'faq' => [
        ['How do I order?', 'Find your products, add them to your cart and check out. You can collect your order, or have it delivered where we deliver.'],
        ['Can I order prescription medicines online?', 'Prescription medicines are supplied only against a valid prescription, checked by our pharmacist. Contact us or visit a branch with your prescription.'],
        ['How can I pay?', 'Pay at checkout using the options shown there. Every order shows its total, including any delivery fee, before you pay.'],
        ['Do you deliver to my area?', 'At checkout, choose your area: if we deliver there, the delivery fee is shown before you pay.'],
        ['Something is wrong with my order. What should I do?', 'Contact us straight away using the details on our Contact page, with your order number, and we will put it right.'],
    ],
];
