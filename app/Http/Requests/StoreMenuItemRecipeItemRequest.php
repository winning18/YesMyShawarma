<?php

namespace App\Http\Requests;

use App\Services\Branches\BranchContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by RecipeManagementController's two store entry points (a menu
 * item's own base recipe, an option's extra consumption) — which source
 * FK gets set, and the branch, come from the route/ambient context, never
 * from user input, so this only validates the two fields the form actually
 * submits.
 */
class StoreMenuItemRecipeItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(BranchContext $context): array
    {
        $menuItem = $this->route('menuItem');
        $sourceType = $menuItem ? 'menu_item' : 'option';
        $sourceColumn = $menuItem ? 'source_menu_item_id' : 'source_option_id';
        $sourceId = $menuItem?->id ?? $this->route('option')?->id;

        return [
            // Scoped to the current branch, not just any stock_items row
            // — a manager at Branch A must never be able to wire a recipe
            // against Branch B's stock_item_id by tampering with the form.
            'stock_item_id' => [
                'required', 'integer',
                Rule::exists('stock_items', 'id')->where('branch_id', $context->id()),
                // One row per (branch, source, stock item) — the table's
                // own unique index would reject a duplicate anyway, this
                // just turns that into a clean validation message instead
                // of a raw DB exception.
                Rule::unique('menu_item_recipe_items')
                    ->where('branch_id', $context->id())
                    ->where('source_type', $sourceType)
                    ->where($sourceColumn, $sourceId),
            ],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
