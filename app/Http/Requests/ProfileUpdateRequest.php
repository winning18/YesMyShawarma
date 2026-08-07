<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Customers\CustomerService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Normalised the same way checkout does (CustomerService's Ghana E.164
     * logic), before validation runs — so the uniqueness check and the
     * eventual save both use the same value phone-based rider login
     * (Rider\LoginRequest) will look up against.
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => [
                'nullable',
                'string',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
