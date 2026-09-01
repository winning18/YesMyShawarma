{{--
    Paired with App\Services\Spam\HoneypotGuard, which checks both fields
    this renders. Positioned off-screen (not display:none/visibility:hidden
    — some bots skip those specifically) rather than a type="hidden" input,
    since a bot reading the raw HTML for realistic-looking fields to fill
    is exactly what "website" as a visible-in-markup text input baits.
    tabindex="-1" and aria-hidden keep a real keyboard/screen-reader user
    from ever landing on it.
--}}
<div class="absolute -left-[9999px] w-px h-px overflow-hidden" aria-hidden="true">
    <label for="website">Website</label>
    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="form_rendered_at" value="{{ time() }}">
