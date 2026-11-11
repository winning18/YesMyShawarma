<?php

/*
|--------------------------------------------------------------------------
| Kinds of purchase for the market list
|--------------------------------------------------------------------------
|
| Owner-approved list (2026-11-11). Reports group every purchase of a kind
| together for the period ("all chicken this month"). Keywords drive the
| suggestion staff see as they type a description: the longest keyword
| found at the start of a word wins (so "vegetable oil" is Cooking oil, not
| Vegetables, and "oil" never matches inside "toilet"). Anything unmatched
| is Others. Lower-case; add words here as staff's own spellings show up.
|
*/

return [
    ['name' => 'Chicken', 'keywords' => ['chicken', 'fowl', 'gizzard', 'drumstick', 'broiler']],
    ['name' => 'Beef', 'keywords' => ['beef', 'meat', 'sausage', 'mince', 'goat']],
    ['name' => 'Bread & Pita', 'keywords' => ['bread', 'pita', 'lebanese', 'bun', 'tortilla', 'wrap bread']],
    ['name' => 'Vegetables', 'keywords' => ['vegetable', 'veg', 'cabbage', 'onion', 'tomato', 'carrot', 'cucumber', 'lettuce', 'pepper', 'green pepper', 'garlic', 'ginger', 'potato', 'spring onion', 'parsley']],
    ['name' => 'Cooking oil', 'keywords' => ['oil', 'cooking oil', 'vegetable oil', 'frytol', 'sunflower']],
    ['name' => 'Gas', 'keywords' => ['gas', 'lpg', 'cylinder']],
    ['name' => 'Sauces & spices', 'keywords' => ['sauce', 'mayonnaise', 'mayo', 'ketchup', 'shito', 'spice', 'seasoning', 'maggi', 'curry', 'salt', 'sugar', 'black pepper', 'pepper powder', 'vinegar', 'garlic sauce']],
    ['name' => 'Drinks & juice ingredients', 'keywords' => ['coke', 'coca', 'sprite', 'fanta', 'water', 'juice', 'mango', 'pineapple', 'orange', 'watermelon', 'sobolo', 'ice', 'soft drink', 'minerals', 'lemon']],
    ['name' => 'Packaging', 'keywords' => ['packaging', 'pack', 'box', 'foil', 'cling', 'rubber', 'polythene', 'bag', 'takeaway', 'napkin', 'serviette', 'straw', 'cup', 'container', 'sticker', 'label', 'wrapper']],
    ['name' => 'Cleaning', 'keywords' => ['cleaning', 'soap', 'detergent', 'sponge', 'bleach', 'broom', 'mop', 'sanitizer', 'sanitiser', 'glove', 'dettol', 'omo', 'tissue', 'toilet roll']],
    ['name' => 'Transport', 'keywords' => ['transport', 'trotro', 'tro tro', 'taxi', 'okada', 'uber', 'bolt', 'yango', 'fare', 'fuel', 'petrol', 'diesel', 'lorry', 'kayayo', 'porter', 'aboboyaa']],
    // Always last, no keywords: whatever matches nothing else.
    ['name' => 'Others', 'keywords' => []],
];
