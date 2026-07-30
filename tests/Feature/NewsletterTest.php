<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_subscribe(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'ama@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'ama@example.com']);
    }

    public function test_subscribing_twice_with_the_same_email_does_not_error(): void
    {
        NewsletterSubscriber::create(['email' => 'ama@example.com', 'subscribed_at' => now()]);

        $this->post(route('newsletter.subscribe'), ['email' => 'ama@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(1, NewsletterSubscriber::where('email', 'ama@example.com')->count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }
}
