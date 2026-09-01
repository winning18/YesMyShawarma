<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    /** @see ContactFormTest::honeypotPassing() */
    private function honeypotPassing(array $data = []): array
    {
        return array_merge(['form_rendered_at' => now()->subSeconds(5)->timestamp], $data);
    }

    public function test_a_visitor_can_subscribe(): void
    {
        $this->post(route('newsletter.subscribe'), $this->honeypotPassing(['email' => 'ama@example.com']))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'ama@example.com']);
    }

    public function test_subscribing_twice_with_the_same_email_does_not_error(): void
    {
        NewsletterSubscriber::create(['email' => 'ama@example.com', 'subscribed_at' => now()]);

        $this->post(route('newsletter.subscribe'), $this->honeypotPassing(['email' => 'ama@example.com']))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(1, NewsletterSubscriber::where('email', 'ama@example.com')->count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->post(route('newsletter.subscribe'), $this->honeypotPassing(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_filling_in_the_honeypot_field_silently_pretends_success(): void
    {
        $this->post(route('newsletter.subscribe'), $this->honeypotPassing([
            'email' => 'bot@example.com', 'website' => 'http://spam.example',
        ]))->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }
}
