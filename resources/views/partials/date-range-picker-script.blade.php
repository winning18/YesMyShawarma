{{-- Backs every <x-date-range-picker> instance. See that component's own
     docblock for why only the custom-range path (this file) touches JS
     at all — named presets are plain links baked server-side. --}}
<script>
    function dateRangePicker(config) {
        return {
            picker: null,

            init() {
                this.picker = new window.Litepicker({
                    element: this.$refs.trigger,
                    singleMode: config.single,
                    autoApply: true,
                    allowRepick: true,
                    format: 'YYYY-MM-DD',
                    maxDate: config.maxDate,
                    startDate: config.from || undefined,
                    endDate: config.single ? undefined : (config.to || undefined),
                    footerHTML: config.footerHtml || '',
                    setup: (picker) => {
                        // Matches the reference picker exactly (confirmed
                        // against a recording of it): clicking a second
                        // day — or the same day again — completes the
                        // pick and both emit 'selected' the same way.
                        // Clicking only ONE day, then dismissing by
                        // clicking outside the calendar, does NOT mean
                        // "cancelled" there — it commits that one day as
                        // a single-day pick. Litepicker itself has no
                        // such behaviour (one click + click-outside just
                        // closes with nothing applied), so it's added
                        // here: track the last 'preselect' (fires on
                        // every day click, even the first) and, on
                        // 'hide', commit it as a single day if 'selected'
                        // never fired for it.
                        let lastPreselect = [];
                        let applied = false;

                        picker.on('show', () => {
                            lastPreselect = [];
                            applied = false;
                        });

                        picker.on('preselect', (...dates) => {
                            lastPreselect = dates;
                        });

                        // Single mode's setDate() emits 'selected' with
                        // just one argument — date2 is undefined there,
                        // never read.
                        picker.on('selected', (date1, date2) => {
                            applied = true;
                            this.$refs.fromField.value = date1.format('YYYY-MM-DD');
                            if (! config.single) {
                                this.$refs.toField.value = date2.format('YYYY-MM-DD');
                            }
                            this.$refs.fromField.closest('form').submit();
                        });

                        picker.on('hide', () => {
                            if (config.single || applied || lastPreselect.length !== 1) {
                                return;
                            }

                            const date = lastPreselect[0].format('YYYY-MM-DD');
                            this.$refs.fromField.value = date;
                            this.$refs.toField.value = date;
                            this.$refs.fromField.closest('form').submit();
                        });
                    },
                });
            },

            destroy() {
                this.picker?.destroy();
            },
        };
    }
</script>
