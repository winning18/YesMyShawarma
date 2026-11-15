<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveHomeStripsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reset' => ['nullable', 'boolean'],
            'strips' => ['exclude_if:reset,1', 'required', 'array', 'min:1', 'max:12'],
            'strips.*.title' => ['exclude_if:reset,1', 'required', 'string', 'max:60'],
            'strips.*.slugs' => ['exclude_if:reset,1', 'required', 'array', 'min:1'],
            'strips.*.slugs.*' => ['exclude_if:reset,1', 'string', 'exists:categories,slug'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'strips.*.title.required' => __('Every strip needs a title.'),
            'strips.*.slugs.required' => __('Every strip needs at least one category.'),
        ];
    }
}
