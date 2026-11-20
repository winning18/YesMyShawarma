import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// This install's own real-time server, from the page
// (partials/reverb-config), so one build serves every Express POS install.
// The build-time values are only a fallback for a page without the tag.
const runtime = (() => {
    try {
        return JSON.parse(document.querySelector('meta[name="reverb-config"]')?.content || '{}');
    } catch {
        return {};
    }
})();
const port = runtime.port || import.meta.env.VITE_REVERB_PORT;
const scheme = runtime.scheme || import.meta.env.VITE_REVERB_SCHEME || 'https';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: runtime.key || import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: runtime.host || import.meta.env.VITE_REVERB_HOST,
    wsPort: port ?? 80,
    wssPort: port ?? 443,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
