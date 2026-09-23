// Minimal service worker whose only job is Web Push delivery for the staff
// dashboard's new-order alert (see .claude/rules/realtime.md's "Web Push"
// section and partials/order-alert-script.blade.php). Not a full PWA
// worker — no offline caching here, on purpose: that's a separate,
// unrelated feature (CLAUDE.md's performance budget) that this doesn't
// attempt to solve. A push notification is only ever delivered while a
// service worker is registered, which is why every push-enabled page
// registers this file even though the page itself works perfectly well
// with no service worker at all.

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'New order', body: event.data ? event.data.text() : '' };
    }

    const data = payload.data || {};

    event.waitUntil(
        self.registration.showNotification(payload.title || 'New order', {
            body: payload.body || '',
            icon: '/images/icon-192.png',
            badge: '/images/icon-192.png',
            // Same order re-notifying (e.g. a retried push) replaces the
            // existing notification instead of stacking a duplicate.
            tag: data.order_id ? `order-${data.order_id}` : undefined,
            data,
        }),
    );
});

// Focuses an already-open dashboard tab rather than always opening a new
// one — staff commonly already have it open in the background, which is
// the exact scenario this feature exists for.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        }),
    );
});
