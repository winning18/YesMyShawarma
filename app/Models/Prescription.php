<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The prescription a Prescription-only or Controlled sale was made against
 * (R5, 2026-11-27), recorded at the till and approved by a manager. Health
 * data: shown only to staff of the branch; the photo is on the private disk.
 */
#[Fillable(['branch_id', 'order_id', 'patient_name', 'patient_phone', 'prescriber_name', 'prescriber_facility', 'issued_on', 'prescription_number', 'photo_path', 'approved_by', 'notes'])]
#[ScopedBy([BranchScope::class])]
class Prescription extends Model
{
    protected function casts(): array
    {
        return ['issued_on' => 'date'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
