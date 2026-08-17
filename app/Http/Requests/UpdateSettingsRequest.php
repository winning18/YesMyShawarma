<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Letters, digits and dashes only — this becomes the literal
            // prefix of a customer- and staff-facing order reference
            // (OrderCreationService::generateReference()), so spaces or
            // punctuation that would look wrong printed on a receipt are
            // rejected outright rather than silently allowed.
            'order_reference_prefix_pos' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'order_reference_prefix_web' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }
}
