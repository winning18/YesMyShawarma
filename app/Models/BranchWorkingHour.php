<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'day_of_week', 'opens_at', 'closes_at'])]
class BranchWorkingHour extends Model
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
