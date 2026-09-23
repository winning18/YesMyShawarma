{{--
    Shared by dashboard/_channel-header.blade.php — included once there, so
    it's present on the Orders board, POS, and Order History alike (every
    page that header renders on). Staff working POS has no reason to be
    looking at the Orders board, so this is what tells them a web order
    just came in without needing to switch tabs: same private
    branch.{id}.orders channel and repeating alarm orders/dashboard.blade.php
    itself listens on, just decoupled from that page's own order-list
    rendering so it works anywhere the header appears.

    $isStaff/$forceShiftStart's own popup takes priority visually — this
    only starts polling/listening once init() runs, same as the shift
    widget it sits next to.

    Also where the Web Push subscription is registered (realtime.md's "Web
    Push" section, NewOrderPushNotifier) — the in-page alarm above only
    ever runs while this tab is open and unsuspended; the whole point of
    push is reaching staff once the browser is minimised or the screen is
    locked, so it has to piggyback on the one component already guaranteed
    to load wherever a staff/manager might be looking.
--}}
<script>
    function orderAlertWidget(branchId) {
        return {
            pendingCount: 0,
            alarmAudio: null,

            init() {
                this.alarmAudio = new Audio('{{ asset('audio/order-notification.wav') }}');
                this.alarmAudio.loop = true;

                this.refresh();
                this.registerPush();

                if (branchId) {
                    window.Echo.private(`branch.${branchId}.orders`)
                        .listen('.OrderPlaced', () => this.refresh())
                        .listen('.OrderStatusChanged', () => this.refresh());
                } else {
                    // Owner viewing an aggregate, cross-branch view has no
                    // single channel to subscribe to — fall back to polling.
                    setInterval(() => this.refresh(), 20000);
                }
            },

            // Best-effort, never blocking the rest of the page — an
            // unsupported browser, a denied/dismissed permission prompt,
            // or VAPID simply not being configured yet (LogPushNotifier's
            // fallback — see AppServiceProvider) all just mean this
            // silently does nothing, same tolerance as autoplay being
            // blocked in updateAlarm() below.
            async registerPush() {
                const vapidPublicKey = @js(config('services.vapid.public_key'));
                if (!vapidPublicKey || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                    return;
                }

                try {
                    const registration = await navigator.serviceWorker.register('/sw.js');

                    let permission = Notification.permission;
                    if (permission === 'default') {
                        permission = await Notification.requestPermission();
                    }
                    if (permission !== 'granted') {
                        return;
                    }

                    let subscription = await registration.pushManager.getSubscription();
                    if (!subscription) {
                        subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey),
                        });
                    }

                    await fetch('{{ route('push.subscribe') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(subscription.toJSON()),
                    });
                } catch (e) {
                    // Non-critical — same tolerance as refresh() below.
                }
            },

            // PushManager.subscribe() requires the VAPID public key as raw
            // bytes, not the base64url string PHP hands the page — this is
            // the standard conversion (browsers offer no built-in for it).
            urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);

                return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
            },

            async refresh() {
                try {
                    const response = await fetch('{{ route('dashboard.orders.data') }}', {
                        headers: { Accept: 'application/json' },
                    });

                    if (response.ok) {
                        const { data } = await response.json();
                        this.pendingCount = data.filter(o => o.status === 'paid').length;
                    }
                } catch (e) {
                    // Non-critical — the alert just doesn't update this cycle.
                }

                this.updateAlarm();
            },

            // Repeating audible alarm while any order sits in "paid" — not
            // a single chime, a loop. alarmAudio.loop handles the repeat
            // natively; this just starts/stops it on the pendingCount
            // transition.
            updateAlarm() {
                if (this.pendingCount > 0 && this.alarmAudio.paused) {
                    // Autoplay can be blocked before the first user gesture
                    // on the page — harmless to ignore, since by the time a
                    // real order arrives staff have already clicked
                    // something (login, at minimum).
                    this.alarmAudio.play().catch(() => {});
                } else if (this.pendingCount === 0 && !this.alarmAudio.paused) {
                    this.alarmAudio.pause();
                    this.alarmAudio.currentTime = 0;
                }
            },
        };
    }
</script>
