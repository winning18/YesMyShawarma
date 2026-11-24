<?php

namespace App\Http\Requests;

use App\Services\Media\ImageRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessProfileRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $emailList = function (string $attribute, mixed $value, Closure $fail): void {
            foreach (array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $value))) as $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $fail(__('":email" isn\'t a valid email address. Check for a missing @ or dot, and put a comma between addresses.', ['email' => $email]));
                }
            }
        };

        return [
            'name' => ['required', 'string', 'max:80'],
            'legal_name' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'public_emails' => ['nullable', 'string', 'max:500', $emailList],
            'contact_recipients' => ['nullable', 'string', 'max:500', $emailList],
            'instagram_url' => ['nullable', 'url:https', 'max:255'],
            'facebook_url' => ['nullable', 'url:https', 'max:255'],
            'tiktok_url' => ['nullable', 'url:https', 'max:255'],
            'main_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'city' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'cuisines' => ['nullable', 'string', 'max:200'],
            'receipt_message' => ['nullable', 'string', 'max:200'],
            'tin' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'primary_colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // PNG/JPG/WebP only — an SVG upload can carry script.
            'logo' => app(ImageRules::class)->rules('logo'),
            'icon' => app(ImageRules::class)->rules('icon'),
            'remove_logo' => ['boolean'],
            'remove_icon' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...app(ImageRules::class)->messages('logo', 'logo'),
            ...app(ImageRules::class)->messages('icon', 'icon'),
        ];
    }
}
