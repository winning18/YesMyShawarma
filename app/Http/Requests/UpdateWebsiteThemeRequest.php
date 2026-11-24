<?php

namespace App\Http\Requests;

use App\Services\Content\WebsiteThemeService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteThemeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'theme' => ['required', 'string', Rule::in(array_keys(WebsiteThemeService::THEMES))],
        ];
    }
}
