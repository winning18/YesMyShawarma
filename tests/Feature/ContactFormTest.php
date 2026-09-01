<?php

namespace Tests\Feature;

use App\Mail\ContactMessageSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Real submissions always clear HoneypotGuard's minimum-elapsed-time
     * check (see its docblock) — merged into every payload here so each
     * test only has to state what's actually relevant to it.
     */
    private function honeypotPassing(array $data = []): array
    {
        return array_merge(['form_rendered_at' => now()->subSeconds(5)->timestamp], $data);
    }

    public function test_a_visitor_can_submit_the_contact_form_with_an_email(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), $this->honeypotPassing([
            'name' => 'Ama',
            'email' => 'ama@example.com',
            'message' => 'Do you deliver to Adenta?',
        ]))->assertRedirect()->assertSessionHas('status');

        Mail::assertSent(ContactMessageSubmitted::class, fn ($mail) => $mail->hasTo('yesmyshawarma@gmail.com')
            && $mail->hasTo('yesmygrill@gmail.com')
            && $mail->hasTo('info@yesmyshawarma.com')
            && $mail->name === 'Ama'
            && $mail->email === 'ama@example.com'
        );
    }

    public function test_a_visitor_can_submit_the_contact_form_with_only_a_phone(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), $this->honeypotPassing([
            'name' => 'Kwame',
            'phone' => '0241234567',
            'message' => 'What time do you close on Sundays?',
        ]))->assertRedirect()->assertSessionHas('status');

        Mail::assertSent(ContactMessageSubmitted::class, fn ($mail) => $mail->phone === '0241234567');
    }

    public function test_the_form_requires_either_an_email_or_a_phone(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), $this->honeypotPassing([
            'name' => 'Ama',
            'message' => 'Hello',
        ]))->assertSessionHasErrors(['email', 'phone']);

        Mail::assertNothingSent();
    }

    public function test_the_form_requires_a_name_and_message(): void
    {
        $this->post(route('contact.submit'), $this->honeypotPassing([
            'email' => 'ama@example.com',
        ]))->assertSessionHasErrors(['name', 'message']);
    }

    public function test_filling_in_the_honeypot_field_silently_pretends_success(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), $this->honeypotPassing([
            'name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'spam',
            'website' => 'http://spam.example',
        ]))->assertRedirect()->assertSessionHas('status')->assertSessionDoesntHaveErrors();

        Mail::assertNothingSent();
    }

    public function test_submitting_faster_than_a_human_can_is_treated_as_spam(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), [
            'name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'spam',
            'form_rendered_at' => now()->timestamp,
        ])->assertRedirect()->assertSessionHas('status');

        Mail::assertNothingSent();
    }

    public function test_missing_the_render_timestamp_entirely_is_treated_as_spam(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), [
            'name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'spam',
        ])->assertRedirect()->assertSessionHas('status');

        Mail::assertNothingSent();
    }
}
