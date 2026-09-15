<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\View\View;

class FaqController extends Controller
{
    /**
     * The single source for both the visible <details>/<summary> accordion
     * (faq.blade.php) and the FAQPage structured data below — one list so
     * the two can never drift out of sync with each other.
     *
     * @var list<array{question: string, answer: string}>
     */
    private const ITEMS = [
        [
            'question' => 'Do I need an account to order?',
            'answer' => 'No, check out as a guest with just your phone number. If you set a password later using that same number, your past orders are automatically linked to the new account.',
        ],
        [
            'question' => 'How can I pay?',
            'answer' => 'Cash on pickup or delivery, or pay online upfront with Paystack. Cards and Mobile Money (MoMo) are both supported.',
        ],
        [
            'question' => 'Do you deliver, or is it pickup only?',
            'answer' => 'Both. Pickup is always available at any branch. Delivery is offered where we have an active delivery area, with the fee worked out by distance from the branch at checkout.',
        ],
        [
            'question' => 'How do I track my order?',
            'answer' => 'Your order confirmation includes a tracking link. You can also look it up anytime from Track order using the phone number you ordered with.',
        ],
        [
            'question' => 'Which branches can I order from?',
            'answer' => 'Ga Odumase and Pokuase Y-Junction are open now, with another branch on the way. Opening hours for each are shown on the Branches page.',
        ],
        [
            'question' => "Something's wrong with my order, what do I do?",
            'answer' => 'Call or WhatsApp the branch you ordered from, or send a message through Contact us, and our team will sort out a refund or replacement.',
        ],
    ];

    public function index(): View
    {
        $items = collect(self::ITEMS)->map(fn (array $item) => [
            'question' => __($item['question']),
            'answer' => __($item['answer']),
        ]);

        return view('faq', [
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
