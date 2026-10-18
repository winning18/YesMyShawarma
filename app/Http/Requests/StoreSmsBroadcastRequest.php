<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreSmsBroadcastRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 4 SMS parts' worth before the opt-out line — every part is
            // billed per recipient, so a cap guards against a costly typo.
            'message' => ['required', 'string', 'max:560'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'send_mode' => ['required', Rule::in(['now', 'later'])],
            'scheduled_at' => ['exclude_unless:send_mode,later', 'required', 'date'],
        ];
    }

    /**
     * The schedule picker is a datetime-local in Accra time; stored UTC.
     */
    public function scheduledAt(): ?Carbon
    {
        return $this->input('send_mode') === 'later'
            ? Carbon::parse($this->input('scheduled_at'), 'Africa/Accra')->utc()
            : null;
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->input('send_mode') === 'later' && $this->input('scheduled_at') && ! $validator->errors()->has('scheduled_at')
                    && $this->scheduledAt()->isPast()) {
                    $validator->errors()->add('scheduled_at', __('Pick a time in the future.'));
                }
            },
        ];
    }
}
