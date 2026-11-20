<?php

namespace App\Http\Requests;

use App\Services\Tax\TaxService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxSettingsRequest extends FormRequest
{
    /** Blank rows on the form are dropped before validating. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'lines' => collect($this->input('lines', []))
                ->filter(fn ($line) => is_array($line) && (trim((string) ($line['name'] ?? '')) !== '' || trim((string) ($line['rate'] ?? '')) !== ''))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inclusive' => ['required', 'boolean'],
            'lines' => ['array', 'max:'.TaxService::MAX_LINES],
            'lines.*.name' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'lines.*.rate' => ['required', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['lines.*.name' => __('tax name'), 'lines.*.rate' => __('rate')];
    }
}
