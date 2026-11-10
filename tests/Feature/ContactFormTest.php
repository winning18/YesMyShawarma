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
        return array_merge(['form_rendered_at' => \App\Services\Spam\HoneypotGuard::stamp(now()->subSeconds(5)->timestamp)], $data);
    }

    public function test_a_visitor_can_submit_the_contact_form_with_an_email(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), $this->honeypotPassing([
            'name' => 'Ama',
            'email' => 'ama@example.com',
            'message' => 'Do you deliver to Adenta?',
        ]))->assertRedirect()->assertSessionHas('status');

        Mail::assertQueued(ContactMessageSubmitted::class, fn ($mail) => $mail->hasTo('yesmyshawarma@gmail.com')
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

        Mail::assertQueued(ContactMessageSubmitted::class, fn ($mail) => $mail->phone === '0241234567');
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
            'form_rendered_at' => \App\Services\Spam\HoneypotGuard::stamp(now()->timestamp),
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

    public function test_a_forged_plain_timestamp_no_longer_passes_the_timing_check(): void
    {
        // Nov 2026 audit: the load time used to be a plain number a bot
        // could set to anything to skip the 2-second wait.
        Mail::fake();

        $this->post(route('contact.submit'), [
            'name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Hello',
            'form_rendered_at' => now()->subMinutes(5)->timestamp,
        ])->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_a_message_stuffed_with_links_is_quietly_dropped_but_one_link_is_fine(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), $this->honeypotPassing(['name' => 'Ama', 'email' => 'ama@example.com', 'message' => 'Cheap pills at https://a.example and www.b.example']))
            ->assertSessionHas('status');
        Mail::assertNothingQueued();

        $this->post(route('contact.submit'), $this->honeypotPassing(['name' => 'Ama', 'email' => 'ama@example.com', 'message' => 'My order on yesmyshawarma.com was late']))
            ->assertSessionHas('status');
        Mail::assertQueued(\App\Mail\ContactMessageSubmitted::class, 1);
    }

    public function test_an_address_is_capped_at_ten_messages_an_hour(): void
    {
        Mail::fake();

        foreach (range(1, 10) as $i) {
            $this->travel(25)->seconds();
            $this->post(route('contact.submit'), $this->honeypotPassing(['name' => 'Ama', 'email' => 'ama@example.com', 'message' => "Message {$i}"]))->assertRedirect();
        }

        $this->travel(25)->seconds();
        $this->post(route('contact.submit'), $this->honeypotPassing(['name' => 'Ama', 'email' => 'ama@example.com', 'message' => 'One too many']))->assertStatus(429);
    }
}
