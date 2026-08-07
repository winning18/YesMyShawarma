{{-- Shared by the staff dashboard and rider layout — both wrap this in an
     element with x-data="shiftWidget()" x-init="init()". Guard-agnostic:
     shifts are keyed on user_id only (see ShiftService), so the same widget
     works for staff and riders alike. --}}
<script>
    function shiftWidget() {
        return {
            active: false,
            branch: null,

            init() {
                this.refresh();
            },

            async refresh() {
                const response = await fetch('{{ route('shift.show') }}', { headers: { Accept: 'application/json' } });
                const data = await response.json();
                this.active = data.active;
                this.branch = data.branch;
            },

            async start() {
                await this.post('{{ route('shift.start') }}');
            },

            async end() {
                await this.post('{{ route('shift.end') }}');
            },

            async post(url) {
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: '{}',
                });
                this.refresh();
            },
        };
    }
</script>
