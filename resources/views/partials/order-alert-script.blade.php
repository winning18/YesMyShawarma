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
