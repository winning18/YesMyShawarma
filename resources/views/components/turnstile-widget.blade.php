{{--
    Renders nothing at all until TURNSTILE_SITE_KEY is set (see
    TurnstileVerifier's docblock) — the honeypot/timing checks alone still
    protect the form either way, this is layered on top once configured.
--}}
@if (config('services.turnstile.site_key'))
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
@endif
