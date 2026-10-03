<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Unlike login (its own LoginRequest already rate-limits per email+IP),
    // PasswordResetLinkController/NewPasswordController have no rate
    // limiting of their own — without this, either route is wide open to
    // being hammered with different emails/tokens (mail-bombing real
    // users' inboxes, or brute-forcing a reset token).
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

// auth:web,rider, not the bare 'auth' — email verification, password
// confirm/update, and the forced-password-change escape hatch are all
// shared between staff (default 'web' guard) and riders (config/auth.php's
// 'rider' guard). A rider hitting one of these while only authenticated
// under 'rider' must not be treated as a guest.
Route::middleware('auth:web,rider')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // Deliberately outside the dashboard/rider route groups' 'branch' +
    // 'password.change_required' middleware — this IS the escape hatch
    // that middleware redirects to, so it can't depend on itself.
    Route::get('force-password-change', [ForcePasswordChangeController::class, 'edit'])
        ->name('password.force-change');
    Route::put('force-password-change', [ForcePasswordChangeController::class, 'update'])
        ->name('password.force-change.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
