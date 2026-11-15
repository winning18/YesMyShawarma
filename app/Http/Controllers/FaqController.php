<?php

namespace App\Http\Controllers;

use App\Models\FaqItem;
use App\Services\Content\FaqService;
use App\Services\Content\SitePageService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FaqController extends Controller
{
    /**
     * One list (FaqService, edited under Website content) feeds both the
     * visible <details>/<summary> accordion and the FAQPage structured
     * data below, so the two can never drift apart.
     */
    public function index(FaqService $faq, SitePageService $pages): View
    {
        $items = $faq->items()->map(fn (FaqItem $item) => [
            'question' => $item->question,
            'answer' => $item->answer,
        ]);

        abort_if($items->isEmpty(), 404);

        return view('faq', [
            'meta' => $pages->meta('faq'),
            'heading' => $pages->heading('faq'),
            'items' => $items,
            'faqSchema' => $this->faqSchema($items),
        ]);
    }

    /**
     * @param  Collection<int, array{question: string, answer: string}>  $items
     * @return array<string, mixed>
     */
    private function faqSchema(Collection $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items->map(fn (array $item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ])->values()->all(),
        ];
    }
}
