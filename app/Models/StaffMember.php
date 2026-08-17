<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Public "Meet our staff" roster shown on the About page — deliberately
 * distinct from the users table (authentication, roles, branch scoping).
 * A staff member here has no login and isn't tied to any branch.
 */
#[Fillable(['name', 'title', 'sort_order', 'is_active', 'photo_path'])]
class StaffMember extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }
}
