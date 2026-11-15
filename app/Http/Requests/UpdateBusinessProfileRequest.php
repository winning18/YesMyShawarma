<?php

namespace App\Http\Requests;

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
                    $fail(__('":email" is not a valid email address.', ['email' => $email]));
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
            'primary_colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // PNG/JPG/WebP only — an SVG upload can carry script.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'icon' => ['nullable', 'image', 'mimes:png', 'max:1024', 'dimensions:min_width=192,min_height=192,ratio=1'],
            'remove_logo' => ['boolean'],
            'remove_icon' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['icon.dimensions' => __('The icon must be square and at least 192×192 pixels (512×512 is best).')];
    }
}
