<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A question on the FAQ page, edited under Website content. */
#[Fillable(['question', 'answer', 'sort_order'])]
class FaqItem extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
