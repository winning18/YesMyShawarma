<?php

namespace App\Http\Requests\Auth;

use App\Models\Terminal;
use App\Models\User;
use App\Services\Users\PinService;
use App\Services\Users\StaffCodeService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Staff ID + PIN at a counter (Express POS step 7b). A PIN is short, so
 * five wrong tries lock that Staff ID on that device for 15 minutes.
 */
class PinLoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_SECONDS = 15 * 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits_between:4,6'],
        ];
    }

    public function authenticate(Terminal $counter): User
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'staff_code' => __('Too many tries. Try again in :minutes minutes, or ask a manager.', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($this->throttleKey()) / 60),
                ]),
            ]);
        }

        $user = app(PinService::class)->attempt($this->string('staff_code'), $this->string('pin'), $counter);

        if (! $user) {
            RateLimiter::hit($this->throttleKey(), self::LOCK_SECONDS);

            throw ValidationException::withMessages(['staff_code' => __('That Staff ID and PIN don\'t match.')]);
        }

        if ($user->suspended_at !== null) {
            throw ValidationException::withMessages(['staff_code' => __('Your account is suspended. Please contact the branch manager.')]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    private function throttleKey(): string
    {
        return 'pin|'.StaffCodeService::normalize($this->string('staff_code')).'|'.$this->ip();
    }
}
