<?php

namespace App\Services\Content;

use App\Models\FaqItem;
use Illuminate\Support\Collection;

/**
 * The FAQ page's questions (Express POS step 2c), edited under Settings →
 * Website content → FAQ. One list feeds both the visible page and its
 * FAQPage structured data (FaqController), so the two never differ.
 */
class FaqService
{
    /**
     * @return Collection<int, FaqItem>
     */
    public function items(): Collection
    {
        return FaqItem::orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * A new question goes to the end unless given a place.
     *
     * @param  array{question: string, answer: string, sort_order?: int|null}  $values
     */
    public function create(array $values): FaqItem
    {
        return FaqItem::create([
            'question' => trim($values['question']),
            'answer' => trim($values['answer']),
            'sort_order' => $values['sort_order'] ?? ((int) FaqItem::max('sort_order') + 1),
        ]);
    }

    /**
     * @param  array{question: string, answer: string, sort_order?: int|null}  $values
     */
    public function update(FaqItem $item, array $values): FaqItem
    {
        $item->update([
            'question' => trim($values['question']),
            'answer' => trim($values['answer']),
            'sort_order' => $values['sort_order'] ?? $item->sort_order,
        ]);

        return $item;
    }

    public function delete(FaqItem $item): void
    {
        $item->delete();
    }
}
