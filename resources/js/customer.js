/**
 * The customer site's whole bundle: Alpine and nothing else. The
 * dashboard bundle (app.js) also carries Leaflet, Litepicker and the
 * realtime client — about 330KB that every customer page used to download
 * and run before Alpine could start, so on slow mobile data the menu
 * button (and every other Alpine control) did nothing for the first few
 * seconds. Pages that need live updates (order tracking) add echo.js via
 * <x-customer-layout realtime>, loaded ahead of this so window.Echo exists
 * by the time Alpine components initialise.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * "Find my nearest branch": asks the phone for its location (only ever on
 * a tap, never on page load), then lets the server pick the branch
 * (BranchesController::nearest). apply: true also selects it and moves the
 * cart; false only reports, for checkout's "a closer branch" suggestion.
 */
window.nearestBranchFor = async (url, lat, lng, apply) => {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ lat, lng, apply }),
    });

    if (!response.ok) throw new Error('failed');

    return response.json();
};

window.findNearestBranch = (url, apply) => new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
        reject(new Error('unsupported'));
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => window.nearestBranchFor(url, position.coords.latitude, position.coords.longitude, apply).then(resolve, reject),
        () => reject(new Error('denied')),
        { timeout: 15000, maximumAge: 300000 },
    );
});

Alpine.data('nearestBranchButton', (url, messages) => ({
    locating: false,
    error: '',

    async find() {
        this.locating = true;
        this.error = '';

        try {
            const data = await window.findNearestBranch(url, true);
            window.location.href = data.redirect;
        } catch (error) {
            this.locating = false;
            this.error = error.message === 'denied' || error.message === 'unsupported'
                ? messages.denied
                : messages.failed;
        }
    },
}));

Alpine.start();
