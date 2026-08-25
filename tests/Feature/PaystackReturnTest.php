<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackReturnTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function makeOrder(string $status = 'pending_payment'): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 7000,
            'total' => 7000,
            'payment_method' => 'paystack',
            'payment_status' => 'pending',
        ]);
        $order->status = $status;
        $order->placed_at = now();
        $order->save();

        $order->payments()->create([
            'provider' => 'paystack', 'provider_reference' => $order->reference.'-REF',
            'amount' => 7000, 'currency' => 'GHS', 'status' => 'pending',
        ]);

        return $order;
    }

    public function test_already_paid_order_redirects_straight_to_confirmation_without_calling_paystack(): void
    {
        Http::fake();

        $order = $this->makeOrder('paid');

        $this->get(route('checkout.paystack-return', $order))
            ->assertRedirect(route('checkout.confirmation', $order));

        Http::assertNothingSent();
    }

    public function test_a_verified_success_confirms_the_payment_and_redirects_to_confirmation(): void
    {
        $order = $this->makeOrder();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 7000],
            ], 200),
        ]);

        $this->get(route('checkout.paystack-return', $order))
            ->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_a_verified_failure_redirects_to_the_declined_page_without_transitioning(): void
    {
        $order = $this->makeOrder();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'failed', 'amount' => 0],
            ], 200),
        ]);

        $this->get(route('checkout.paystack-return', $order))
            ->assertRedirect(route('checkout.declined', $order));

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_an_abandoned_transaction_redirects_to_the_declined_page(): void
    {
        $order = $this->makeOrder();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'abandoned', 'amount' => 0],
            ], 200),
        ]);

        $this->get(route('checkout.paystack-return', $order))
            ->assertRedirect(route('checkout.declined', $order));
    }

    public function test_a_still_processing_transaction_falls_back_to_the_tracking_page(): void
    {
        $order = $this->makeOrder();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'processing', 'amount' => 0],
            ], 200),
        ]);

        $this->get(route('checkout.paystack-return', $order))
            ->assertRedirect(route('tracking.show', $order));

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_the_declined_page_renders(): void
    {
        $order = $this->makeOrder();

        $this->get(route('checkout.declined', $order))
            ->assertOk()
            ->assertSee($order->reference);
    }

    public function test_visiting_confirmation_directly_for_an_unconfirmed_paystack_order_redirects_to_the_return_flow(): void
    {
        // Closes the direct-URL/bookmark/back-button gap — the confirmation
        // page must never assert "confirmed" for an order that isn't.
        $order = $this->makeOrder();

        $this->get(route('checkout.confirmation', $order))
            ->assertRedirect(route('checkout.paystack-return', $order));
    }

    public function test_visiting_confirmation_for_a_paid_paystack_order_renders_normally(): void
    {
        $order = $this->makeOrder('paid');

        $this->get(route('checkout.confirmation', $order))
            ->assertOk()
            ->assertSee($order->reference);
    }

    public function test_visiting_confirmation_for_a_cash_order_is_unaffected_by_the_guard(): void
    {
        $order = $this->makeOrder('paid');
        $order->update(['payment_method' => 'cash']);

        $this->get(route('checkout.confirmation', $order))
            ->assertOk()
            ->assertSee($order->reference);
    }

    // Regression: an earlier version of this guard only checked
    // `!== 'pending_payment'`, so an abandoned order (never paid) fell
    // through as "resolved" and rendered the confirmed "Thank you!" page —
    // caught live against production before anyone else could hit it.
    public function test_an_abandoned_order_never_shows_the_confirmed_page(): void
    {
        $order = $this->makeOrder('abandoned');

        $this->get(route('checkout.confirmation', $order))
            ->assertRedirect(route('checkout.paystack-return', $order));

        $this->get(route('checkout.paystack-return', $order))
            ->assertRedirect(route('checkout.declined', $order));
    }

    public function test_a_status_only_reachable_from_paid_goes_to_tracking_not_declined_or_confirmation(): void
    {
        // rejected/cancelled/failed/refunded all imply payment DID succeed
        // (orders.md's TRANSITIONS graph) — declined's "you were not
        // charged" would be factually wrong, and confirmation's "thank
        // you" would misrepresent an order that didn't go through. Only
        // the tracking page tells this story correctly.
        foreach (['rejected', 'cancelled', 'failed', 'refunded'] as $status) {
            $order = $this->makeOrder($status);

            $this->get(route('checkout.paystack-return', $order))
                ->assertRedirect(route('tracking.show', $order));

            $this->get(route('checkout.confirmation', $order))
                ->assertRedirect(route('checkout.paystack-return', $order));
        }
    }

    public function test_confirmed_downstream_statuses_all_render_the_confirmation_page(): void
    {
        foreach (['paid', 'accepted', 'preparing', 'ready', 'dispatched', 'delivered'] as $status) {
            $order = $this->makeOrder($status);

            $this->get(route('checkout.confirmation', $order))
                ->assertOk()
                ->assertSee($order->reference);
        }
    }
}
