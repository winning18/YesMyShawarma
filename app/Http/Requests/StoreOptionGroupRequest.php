<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOptionGroupRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'min_select' => ['required', 'integer', 'min:0'],
            'max_select' => [
                'required', 'integer', 'min:1',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ((int) $value < (int) $this->input('min_select', 0)) {
                        $fail(__('Max select must be at least min select.'));
                    }
                },
            ],
            'is_required' => ['nullable', 'boolean'],
        ];
    }
}
