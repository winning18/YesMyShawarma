<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique(Promotion::class)->ignore($this->route('promotion'))],
            'type' => ['required', 'string', Rule::in(['percentage', 'fixed', 'buy_x_get_y_free'])],
            'value' => [
                Rule::requiredIf(fn () => $this->input('type') !== 'buy_x_get_y_free'),
                'nullable', 'numeric', 'min:0.01',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('type') === 'percentage' && ($value < 1 || $value > 100 || (float) $value != (int) $value)) {
                        $fail('A percentage discount must be a whole number between 1 and 100.');
                    }
                },
            ],
            'buy_quantity' => [Rule::requiredIf(fn () => $this->input('type') === 'buy_x_get_y_free'), 'nullable', 'integer', 'min:2'],
            'free_quantity' => [Rule::requiredIf(fn () => $this->input('type') === 'buy_x_get_y_free'), 'nullable', 'integer', 'min:1'],
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_automatic' => ['nullable', 'boolean'],
            'recurring_days' => [Rule::requiredIf($this->boolean('is_automatic')), 'nullable', 'array'],
            'recurring_days.*' => ['integer', 'between:0,6'],
            'banner_headline' => ['nullable', 'string', 'max:255'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_per_customer' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper((string) $this->input('code'))]);
    }
}
