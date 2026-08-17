<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkingHoursRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
        ];

        foreach (range(1, 7) as $day) {
            $rules["days.{$day}.opens_at"] = ['nullable', 'date_format:H:i', "required_with:days.{$day}.closes_at"];
            $rules["days.{$day}.closes_at"] = ['nullable', 'date_format:H:i', "required_with:days.{$day}.opens_at", "after:days.{$day}.opens_at"];
        }

        return $rules;
    }
}
