<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $extrasData = require database_path('seeders/data/extras.php');
        $categoriesData = require database_path('seeders/data/menu.php');

        $extrasGroup = OptionGroup::firstOrCreate(
            ['name' => $extrasData['name']],
            [
                'min_select' => $extrasData['min_select'],
                'max_select' => $extrasData['max_select'],
                'is_required' => $extrasData['is_required'],
            ]
        );

        foreach ($extrasData['options'] as $optionData) {
            Option::firstOrCreate(
                ['option_group_id' => $extrasGroup->id, 'name' => $optionData['name']],
                ['price_delta' => $optionData['price_delta']]
            );
        }

        $branches = Branch::all();

        foreach ($categoriesData as $categorySortOrder => $categoryData) {
            // updateOrCreate, not firstOrCreate — the data file is the
            // canonical source of ordering, so re-seeding must correct an
            // existing category's sort_order, not silently leave it as
            // whatever it was the first time this ran.
            $category = Category::updateOrCreate(
                ['slug' => Str::slug($categoryData['category'])],
                ['name' => $categoryData['category'], 'sort_order' => $categorySortOrder]
            );

            // Extras (Chicken/Beef/Sausage/Cheese/Egg/Fries add-ons) apply to
            // the food menu; drinks have no meaningful use for them.
            $attachExtras = $categoryData['category'] !== 'Drinks';

            foreach ($categoryData['items'] as $itemSortOrder => $itemData) {
                $menuItem = MenuItem::updateOrCreate(
                    ['slug' => Str::slug($itemData['name'])],
                    [
                        'category_id' => $category->id,
                        'name' => $itemData['name'],
                        'base_price' => $itemData['price'],
                        'sort_order' => $itemSortOrder,
                    ]
                );

                if ($attachExtras) {
                    $menuItem->optionGroups()->syncWithoutDetaching([$extrasGroup->id => ['sort_order' => 1]]);
                }

                foreach ($branches as $branch) {
                    $branch->menuItems()->syncWithoutDetaching([$menuItem->id => ['is_available' => true]]);
                }
            }
        }
    }
}
