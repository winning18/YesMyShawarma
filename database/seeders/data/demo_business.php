<?php

/**
 * "Express POS Demo": the made-up restaurant on the shared staging install
 * (staging.pos.solusysdigital.com, owner 2026-11-24), loaded by
 * `php artisan expresspos:demo-data`. Nothing here is a real business.
 * Prices are in pesewas.
 */
return [
    'business' => 'Express POS Demo',
    'colours' => ['primary' => '#f59e0b', 'accent' => '#0f766e', 'background' => '#111827'],
    'tagline' => 'Ghanaian favourites, made fresh every day.',

    'branches' => [
        ['name' => 'Osu', 'phone' => '+233200000101', 'address' => 'Oxford Street, Osu, Accra', 'lat' => 5.5560, 'lng' => -0.1823],
        ['name' => 'East Legon', 'phone' => '+233200000102', 'address' => 'Lagos Avenue, East Legon, Accra', 'lat' => 5.6350, 'lng' => -0.1610],
    ],
    // Every day, both branches.
    'hours' => ['opens_at' => '08:00', 'closes_at' => '22:00'],
    'tables' => ['Table 1', 'Table 2', 'Table 3', 'Table 4'],

    'staff' => [
        ['role' => 'owner', 'name' => 'Demo Owner', 'email' => 'owner@demo.expresspos.test'],
        ['role' => 'general_manager', 'name' => 'Demo General Manager', 'email' => 'gm@demo.expresspos.test'],
        ['role' => 'manager', 'name' => 'Demo Manager', 'email' => 'manager@demo.expresspos.test'],
        ['role' => 'staff', 'name' => 'Demo Cashier', 'email' => 'cashier@demo.expresspos.test', 'pin' => true],
        ['role' => 'staff', 'name' => 'Demo Kitchen', 'email' => 'kitchen@demo.expresspos.test'],
        ['role' => 'rider', 'name' => 'Demo Rider', 'email' => 'rider@demo.expresspos.test'],
    ],

    'option_groups' => [
        'protein' => ['name' => 'Protein', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [['Chicken', 0], ['Beef', 500], ['Fish', 800]]],
        'extras' => ['name' => 'Extras', 'min' => 0, 'max' => 4, 'required' => false, 'options' => [['Fried egg', 300], ['Shito', 200], ['Fried plantain', 500], ['Coleslaw', 300]]],
        'size' => ['name' => 'Size', 'min' => 1, 'max' => 1, 'required' => true, 'options' => [['Regular', 0], ['Large', 1000]]],
    ],

    'categories' => [
        ['name' => 'Rice dishes', 'items' => [
            ['Jollof rice', 'Smoky party jollof with your choice of protein.', 4500, ['protein', 'extras']],
            ['Waakye', 'Rice and beans with gari, spaghetti, egg and shito.', 4000, ['protein', 'extras']],
            ['Fried rice', 'Vegetable fried rice with your choice of protein.', 4500, ['protein', 'extras']],
        ]],
        ['name' => 'Local dishes', 'items' => [
            ['Banku and tilapia', 'Grilled tilapia with banku and pepper.', 8000, ['size']],
            ['Fufu and light soup', 'Pounded fufu with goat light soup.', 6500, ['size']],
            ['Kenkey and fried fish', 'Ga kenkey with fried fish and pepper.', 4500, []],
        ]],
        ['name' => 'Grills', 'items' => [
            ['Grilled chicken', 'Quarter chicken, spiced and grilled.', 5500, ['extras']],
            ['Kebab skewers', 'Three beef kebabs with suya spice.', 3500, []],
        ]],
        ['name' => 'Drinks', 'beverage' => true, 'items' => [
            ['Sobolo', 'Chilled hibiscus drink.', 1500, []],
            ['Asaana', 'Caramelised corn drink.', 1500, []],
            ['Bottled water', '500 ml.', 500, []],
        ]],
    ],

    // Names only: delivery fees are worked out from distance (DeliveryFeeCalculator).
    'delivery_areas' => ['Osu', 'Labone', 'Cantonments', 'Airport Residential', 'East Legon', 'Adjiringanor', 'Madina', 'Spintex'],
];
