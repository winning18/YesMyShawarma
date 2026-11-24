{{--
    In every layout's <head> (owner, 2026-11-24): the wording the shared
    error helpers use (resources/js/form-feedback.js), translated, and the
    last submit's validation errors, so the form can mark each field in
    red with its message and list them at the top.
--}}
<script>
    window.xposText = @js([
        'offline' => __('You\'re offline. Check the Wi-Fi or mobile data, then try again.'),
        'network' => __('Couldn\'t reach the server. Check the internet connection, then try again.'),
        'signedOut' => __('You\'ve been signed out. Sign in again, then try once more.'),
        'forbidden' => __('You don\'t have permission to do this. Ask a manager or the owner.'),
        'notFound' => __('This no longer exists: someone may have changed or removed it. Refresh the page.'),
        'tooLarge' => __('The file is too big to send. Make it smaller (under 4 MB) and try again.'),
        'expired' => __('This page was open too long and expired. Refresh the page, then try again.'),
        'tooMany' => __('Too many tries in a short time. Wait a minute, then try again.'),
        'server' => __('Something went wrong on our side. Try again in a moment; if it keeps happening, call Solusys Digital on 026 370 2929.'),
        'unavailable' => __('The system is being updated. Wait a minute, then try again.'),
        'summaryOne' => __('1 thing needs fixing before this can be saved:'),
        'summaryMany' => __(':count things need fixing before this can be saved:'),
    ]);
    @if (isset($errors) && $errors->any())
        window.xposFormErrors = @js($errors->getBag('default')->toArray());
    @endif
</script>
