<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemComponentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'component_type' => ['required', 'in:base,modifier'],
            'component_menu_item_id' => ['required_if:component_type,base', 'nullable', 'integer', 'exists:menu_items,id'],
            'component_option_id' => ['required_if:component_type,modifier', 'nullable', 'integer', 'exists:options,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
