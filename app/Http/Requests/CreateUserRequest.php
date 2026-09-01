<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Customers\CustomerService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    /**
     * Normalised the same way the profile form does — see
     * ProfileUpdateRequest for why this has to happen before validation
     * runs, not just before saving.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge([
                'phone' => app(CustomerService::class)->normalizeGhanaPhone($this->string('phone')->toString()),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => ['nullable', 'string', Rule::unique(User::class)],
            'role' => ['required', 'string', Rule::in(['staff', 'rider', 'manager', 'general_manager', 'owner', 'stock_manager'])],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ];
    }
}
