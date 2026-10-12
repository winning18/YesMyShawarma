import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

/**
 * Bundled (not a CDN <script> tag) so it's self-hosted like the rest of
 * the app's assets — versioned, works offline once cached, no external
 * request. Exposed on window rather than imported per-view since every
 * other view-specific Alpine component in this app is a plain global
 * function defined in an inline <script> (see partials/shift-widget-
 * script.blade.php) rather than its own JS module.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet's default marker icon is hard-coded to a relative URL that only
// resolves when leaflet.js is loaded directly, not bundled — without this
// the pin renders as a broken image once Vite hashes/moves the asset.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

window.L = L;

/**
 * Backs the shared <x-date-range-picker> component (resources/views/
 * components/date-range-picker.blade.php) — a calendar + preset-buttons
 * popover for every date-range filter in the dashboard (Order History,
 * Detailed reports, Sales tab). Exposed on window for the same reason as
 * Leaflet above: every view-specific Alpine component here is a plain
 * global function in an inline <script>, not its own JS module.
 */
import Litepicker from 'litepicker';
import 'litepicker/dist/css/litepicker.css';

window.Litepicker = Litepicker;

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
