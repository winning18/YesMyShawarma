<?php

namespace App\Http\Requests;

use App\Services\Modules\ModuleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModulesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'modules' => ['nullable', 'array:'.implode(',', array_keys(ModuleService::MODULES))],
            'modules.*' => ['boolean'],
        ];
    }
}
