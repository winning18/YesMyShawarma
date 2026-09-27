<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Pins every role/permission check to the 'web' guard_name regardless
     * of which auth guard actually authenticated this user right now.
     *
     * config/auth.php's 'web' and 'rider' guards deliberately share this
     * same `users` provider (so a staff and a rider login can coexist in
     * one session — see that file's own comment) — but spatie/laravel-
     * permission's Guard::getDefaultName() resolves the guard_name to
     * check roles/permissions against from *config('auth.defaults.guard')
     * at the moment of the check*, not from which guard this specific
     * user instance was actually authenticated through. Once two guards
     * share a provider, that becomes ambiguous — a role assigned while
     * 'web' was the request's default guard (staff routes, and every row
     * RolesAndPermissionsSeeder creates) would silently fail to match once
     * a rider request's `auth:rider` middleware flips the default to
     * 'rider' for that request. Permissions in this app were never meant
     * to be a second, guard-specific namespace — they're the same roles
     * regardless of which of the two guards logged this user in — so this
     * pins the lookup guard explicitly rather than leaving it to whichever
     * guard happens to be "current". See permissions.md's "Guards"
     * section.
     */
    public function guardName(): string
    {
        return 'web';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
