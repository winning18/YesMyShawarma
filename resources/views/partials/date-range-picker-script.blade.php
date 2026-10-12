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
                        // Single mode's setDate() emits 'selected' with
                        // just one argument — date2 is undefined there,
                        // never read.
                        picker.on('selected', (date1, date2) => {
                            this.$refs.fromField.value = date1.format('YYYY-MM-DD');
                            if (! config.single) {
                                this.$refs.toField.value = date2.format('YYYY-MM-DD');
                            }
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
