{{-- Shared by the staff dashboard and rider layout — both wrap this in an
     element with x-data="shiftWidget()" x-init="init()". Guard-agnostic:
     shifts are keyed on user_id only (see ShiftService), so the same widget
     works for staff and riders alike.

     $requireTotalSalesOnEnd and $forceStart are only ever true for the
     staff-dashboard header (dashboard/_channel-header.blade.php) — the
     rider layout uses the zero-arg default and keeps the original plain
     start()/end() behaviour untouched. --}}
<script>
    function shiftWidget(requireTotalSalesOnEnd = false, forceStart = false) {
        return {
            active: false,
            branch: null,
            requireTotalSalesOnEnd: requireTotalSalesOnEnd,
            forceStart: forceStart,

            startModalOpen: false,
            startingCash: '',
            openingNote: '',
            endModalOpen: false,
            totalSales: '',
            closingNote: '',
            systemSales: null,
            error: null,

            init() {
                this.refresh();
            },

            async refresh() {
                const response = await fetch('{{ route('shift.show') }}', { headers: { Accept: 'application/json' } });
                const data = await response.json();
                this.active = data.active;
                this.branch = data.branch;
                this.systemSales = data.system_sales;

                // Runs after every refresh, not just on init — a shift
                // ending mid-session (e.g. staff clocks out, then the
                // widget refreshes) re-triggers the same forced popup
                // rather than leaving the dashboard usable with no shift.
                if (this.forceStart && !this.active) {
                    this.startModalOpen = true;
                }
            },

            // Plain, unmodalled actions — unchanged, still what the rider
            // layout's buttons call directly.
            async start() {
                await this.post('{{ route('shift.start') }}');
            },

            async end() {
                await this.post('{{ route('shift.end') }}');
            },

            openStartModal() {
                this.error = null;
                this.startModalOpen = true;
            },

            closeStartModal() {
                if (!this.forceStart) {
                    this.startModalOpen = false;
                }
            },

            async confirmStart() {
                this.error = null;

                const ok = await this.post('{{ route('shift.start') }}', {
                    starting_cash: this.startingCash || null,
                    opening_note: this.openingNote || null,
                });

                if (ok) {
                    this.startModalOpen = false;
                    this.startingCash = '';
                    this.openingNote = '';
                }
            },

            openEndModal() {
                this.error = null;
                this.endModalOpen = true;
            },

            closeEndModal() {
                this.endModalOpen = false;
            },

            async confirmEnd() {
                this.error = null;

                const ok = await this.post('{{ route('shift.end') }}', {
                    total_sales: this.totalSales || null,
                    closing_note: this.closingNote || null,
                });

                if (ok) {
                    this.endModalOpen = false;
                    this.totalSales = '';
                    this.closingNote = '';

                    // Some staff lose dashboard access the instant their
                    // shift ends, and land on Reports and invoices next —
                    // the natural place to review the shift they just
                    // closed out. A real navigation, not a reload: reload
                    // would leave them stuck on whatever dashboard-area
                    // page they were just locked out of.
                    if (this.requireTotalSalesOnEnd) {
                        window.location.href = '{{ route('dashboard.reports.index') }}';
                    }
                }
            },

            formatMoney(pesewas) {
                return 'GH₵' + ((pesewas ?? 0) / 100).toFixed(2);
            },

            async post(url, body = {}) {
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(body),
                    });

                    const payload = await response.json().catch(() => null);

                    if (!response.ok) {
                        this.error = payload?.message || 'Action failed.';
                        await this.refresh();
                        return false;
                    }

                    await this.refresh();
                    return true;
                } catch (e) {
                    this.error = 'Action failed.';
                    return false;
                }
            },
        };
    }
</script>
