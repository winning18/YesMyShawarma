<?php

namespace Tests\Feature;

use App\Contracts\Notifier;
use App\Jobs\SendSms;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Notifications\CustomerOrderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendSmsTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        return Order::create([
            'reference' => 'ORD-1', 'track_token' => str_repeat('t', 32),
            'customer_id' => Customer::create(['phone' => '+233241234567'])->id,
            'branch_id' => $branch->id, 'fulfilment_type' => 'delivery',
            'subtotal' => 1000, 'total' => 1000, 'payment_method' => 'cash', 'payment_status' => 'paid',
        ]);
    }

    public function test_order_sms_is_queued_instead_of_sent_inside_the_request(): void
    {
        // Sentry PHP-LARAVEL-M: a stalled Arkesel held a staff "Delivered" tap for 10s+.
        Queue::fake();

        app(CustomerOrderNotifier::class)->delivered($this->order());

        Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->phone === '+233241234567'
            && str_starts_with($job->message, 'Your order ORD-1 has been delivered.'));
    }

    public function test_the_queued_job_sends_through_the_notifier(): void
    {
        $this->mock(Notifier::class)->shouldReceive('notify')->once()->with('+233241234567', 'Hello', ['order_id' => 1]);

        (new SendSms('+233241234567', 'Hello', ['order_id' => 1]))->handle(app(Notifier::class));
    }
}
