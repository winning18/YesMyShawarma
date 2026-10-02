{{-- The only shift toggle in the app — dashboard/_channel-header.blade.php
     (Orders/POS/History), the sole caller of shiftWidget(). Riders have no
     shift widget at all (orders.md: "no shift required" for them).

     $forceStart is only ever true there too (staff, no active shift yet).
     $redirectToReportsOnEnd is passed as $isStaff by the caller: staff
     loses dashboard access the instant their shift ends and needs
     somewhere to land, manager/general_manager don't lose anything and
     stay put — this is its only remaining job now that total_sales/
     expenses are required of everyone who ends a shift, not staff alone. --}}
<script>
    function shiftWidget(redirectToReportsOnEnd = false, forceStart = false) {
        return {
            active: false,
            branch: null,
            redirectToReportsOnEnd: redirectToReportsOnEnd,
            forceStart: forceStart,

            startModalOpen: false,
            startingCash: '',
            openingNote: '',
            endModalOpen: false,
            totalSales: '',
            closingNote: '',
            expenses: [],
            noExpenses: false,
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

            // Plain, unmodalled actions — dead code today (nothing calls
            // these; the header above always goes through the modal/
            // confirm* pair instead) but left in place rather than
            // removed as part of an unrelated change.
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

            addExpenseRow() {
                this.expenses.push({ description: '', amount: '' });
            },

            removeExpenseRow(index) {
                this.expenses.splice(index, 1);
            },

            // A row only counts once both halves are filled — an
            // in-progress row (description typed, amount not yet, or vice
            // versa) is neither submitted nor allowed to silently satisfy
            // "at least one expense".
            completeExpenseRows() {
                return this.expenses.filter((row) => row.description && row.amount);
            },

            async confirmEnd() {
                this.error = null;

                if (!this.totalSales) {
                    this.error = @js(__('Total sales is required to end your shift.'));
                    return;
                }

                if (this.systemSales !== null && Math.round(parseFloat(this.totalSales) * 100) < this.systemSales) {
                    this.error = @js(__('Total sales cannot be less than today\'s recorded sales.'));
                    return;
                }

                const completeExpenses = this.completeExpenseRows();

                if (!this.noExpenses && completeExpenses.length === 0) {
                    this.error = @js(__('Add at least one expense, or confirm there were none today.'));
                    return;
                }

                const ok = await this.post('{{ route('shift.end') }}', {
                    total_sales: this.totalSales || null,
                    closing_note: this.closingNote || null,
                    no_expenses: this.noExpenses,
                    expenses: completeExpenses,
                });

                if (ok) {
                    this.endModalOpen = false;
                    this.totalSales = '';
                    this.closingNote = '';
                    this.expenses = [];
                    this.noExpenses = false;

                    // Some staff lose dashboard access the instant their
                    // shift ends, and land on Reports and invoices next —
                    // the natural place to review the shift they just
                    // closed out. A real navigation, not a reload: reload
                    // would leave them stuck on whatever dashboard-area
                    // page they were just locked out of. Manager/
                    // general_manager lose no access, so they stay put.
                    if (this.redirectToReportsOnEnd) {
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
