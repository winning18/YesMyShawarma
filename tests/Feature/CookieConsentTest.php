<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_sets_the_consent_cookie_and_keeps_tracking(): void
    {
        // Realistic order of events: the banner is shown on a page the
        // visitor has already loaded, so a visitor_token cookie already
        // exists by the time they answer it.
        $home = $this->get(route('home'));
        $token = $home->getCookie('visitor_token')->getValue();

        $response = $this->withCookie('visitor_token', $token)
            ->post(route('cookie-consent.update'), ['choice' => 'accept']);

        $response->assertRedirect();
        $this->assertSame('accept', $response->getCookie('cookie_consent')->getValue());

        $this->withCookie('visitor_token', $token)->withCookie('cookie_consent', 'accept')->get(route('menu.index'));

        $this->assertDatabaseCount('visitor_sessions', 1);
    }

    public function test_declining_sets_the_consent_cookie_and_forgets_the_visitor_cookie(): void
    {
        $home = $this->get(route('home'));
        $token = $home->getCookie('visitor_token')->getValue();

        $response = $this->withCookie('visitor_token', $token)
            ->post(route('cookie-consent.update'), ['choice' => 'decline']);

        $response->assertRedirect();
        $this->assertSame('decline', $response->getCookie('cookie_consent')->getValue());
        $this->assertLessThan(now()->timestamp, $response->getCookie('visitor_token')->getExpiresTime());
    }

    public function test_declining_stops_future_visits_from_being_tracked(): void
    {
        $this->withCookie('cookie_consent', 'decline')->get(route('home'));

        $this->assertDatabaseCount('visitor_sessions', 0);
        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_an_invalid_choice_is_rejected(): void
    {
        $this->post(route('cookie-consent.update'), ['choice' => 'maybe'])
            ->assertSessionHasErrors('choice');
    }
}
