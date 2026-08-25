{{--
    Shared by every phone number field that wants the masked/validated UX
    (checkout, customer register/login, POS, tracking lookup, staff user
    create, profile) — one formatting/validation rule rather than copies
    drifting apart. Ghana numbers are entered as exactly 10 local digits
    (e.g. "0241234567"); the dash grouping (XXX-XXX-XXXX) is purely a
    display mask, submitted as-is, since normalizeGhanaPhone() already
    strips all non-digits server-side regardless of dashes.
--}}
<script>
    function phoneField(initial = '') {
        // $customer->phone / $user->phone come back from the database
        // already normalised to E.164 ("+233241234567") — an initial value
        // pre-filling this field (e.g. checkout for a logged-in customer,
        // or editing your own profile) needs converting back to the local
        // 10-digit form this field actually displays, or the country-code
        // digits would themselves get truncated into a garbage number.
        const toLocalDigits = (value) => {
            const digits = (value || '').replace(/\D/g, '');
            return digits.startsWith('233') && digits.length === 12
                ? '0' + digits.slice(3)
                : digits.slice(0, 10);
        };

        return {
            raw: toLocalDigits(initial),
            touched: toLocalDigits(initial).length > 0,

            get formatted() {
                const d = this.raw;
                if (d.length <= 3) return d;
                if (d.length <= 6) return d.slice(0, 3) + '-' + d.slice(3);
                return d.slice(0, 3) + '-' + d.slice(3, 6) + '-' + d.slice(6, 10);
            },

            get valid() {
                return this.raw.length === 10;
            },

            get invalid() {
                return this.touched && !this.valid;
            },

            onInput(event) {
                this.touched = true;
                this.raw = event.target.value.replace(/\D/g, '').slice(0, 10);
                event.target.value = this.formatted;
            },

            onBlur() {
                this.touched = true;
            },
        };
    }
</script>
