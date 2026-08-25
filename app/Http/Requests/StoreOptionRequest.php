<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOptionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // GHS scale — the controller converts to pesewas. A pure add-on
            // ("no cheese") is priced 0, so min is 0 here, not 0.01 like a
            // menu item's own base_price.
            'price_delta' => ['required', 'numeric', 'min:0'],
        ];
    }
}
