<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            // GHS scale here — the controller converts to pesewas before
            // saving, same boundary pattern as PromotionManagementController.
            'base_price' => ['required', 'numeric', 'min:0.01'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'option_group_ids' => ['nullable', 'array'],
            'option_group_ids.*' => ['integer', 'exists:option_groups,id'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
