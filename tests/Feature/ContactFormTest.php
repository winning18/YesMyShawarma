<?php

namespace Tests\Feature;

use App\Mail\ContactMessageSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_submit_the_contact_form_with_an_email(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), [
            'name' => 'Ama',
            'email' => 'ama@example.com',
            'message' => 'Do you deliver to Adenta?',
        ])->assertRedirect()->assertSessionHas('status');

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

        $this->post(route('contact.submit'), [
            'name' => 'Kwame',
            'phone' => '0241234567',
            'message' => 'What time do you close on Sundays?',
        ])->assertRedirect()->assertSessionHas('status');

        Mail::assertSent(ContactMessageSubmitted::class, fn ($mail) => $mail->phone === '0241234567');
    }

    public function test_the_form_requires_either_an_email_or_a_phone(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), [
            'name' => 'Ama',
            'message' => 'Hello',
        ])->assertSessionHasErrors(['email', 'phone']);

        Mail::assertNothingSent();
    }

    public function test_the_form_requires_a_name_and_message(): void
    {
        $this->post(route('contact.submit'), [
            'email' => 'ama@example.com',
        ])->assertSessionHasErrors(['name', 'message']);
    }
}
