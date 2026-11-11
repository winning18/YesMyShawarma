<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A "kind" of purchase on the market list (Chicken, Transport, … Others).
 * The list itself comes from database/seeders/data/expense_categories.php.
 */
#[Fillable(['name', 'keywords', 'sort_order', 'is_active'])]
class ExpenseCategory extends Model
{
    public const OTHERS = 'Others';

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
