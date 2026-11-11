<?php

namespace App\Services\MarketLists;

use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

/**
 * Suggests a purchase's kind from its description — the longest keyword
 * found at the start of a word wins, so "vegetable oil" is Cooking oil
 * (not Vegetables) and "oil" never matches inside "toilet". Unmatched is
 * Others. Only ever a suggestion: staff can change it and the approver
 * confirms it (owner, 2026-11-11). The market-list form runs the same
 * rule in the browser (resources/views/dashboard/market-lists/_form) so
 * the suggestion shows while typing.
 */
class ExpenseKindMatcher
{
    /** @var Collection<int, ExpenseCategory>|null */
    private ?Collection $categories = null;

    public function match(string $description): ExpenseCategory
    {
        $text = mb_strtolower($description);
        $best = null;
        $bestLength = 0;

        foreach ($this->categories() as $category) {
            foreach ($category->keywords ?? [] as $keyword) {
                $length = mb_strlen($keyword);

                if ($length > $bestLength && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'/u', $text)) {
                    $best = $category;
                    $bestLength = $length;
                }
            }
        }

        return $best ?? $this->others();
    }

    public function others(): ExpenseCategory
    {
        return $this->categories()->firstWhere('name', ExpenseCategory::OTHERS)
            ?? ExpenseCategory::firstOrCreate(['name' => ExpenseCategory::OTHERS], ['keywords' => [], 'sort_order' => 999]);
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    public function categories(): Collection
    {
        return $this->categories ??= ExpenseCategory::where('is_active', true)->orderBy('sort_order')->get();
    }
}
