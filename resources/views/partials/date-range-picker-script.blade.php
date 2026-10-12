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
                    singleMode: false,
                    autoApply: true,
                    allowRepick: true,
                    format: 'YYYY-MM-DD',
                    maxDate: config.maxDate,
                    startDate: config.from || undefined,
                    endDate: config.to || undefined,
                    footerHTML: config.footerHtml || '',
                    setup: (picker) => {
                        picker.on('selected', (date1, date2) => {
                            this.$refs.fromField.value = date1.format('YYYY-MM-DD');
                            this.$refs.toField.value = date2.format('YYYY-MM-DD');
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
