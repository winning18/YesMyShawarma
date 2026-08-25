{{--
    Shared by every "Add to cart" form with option groups (menu grid cards,
    the individual item page) — client-side option-group check that pops
    up instantly, instead of the old behaviour of POSTing, getting rejected
    server-side, and only finding out via a full page reload with a banner
    at the top. Mirrors MenuPricingService::assertGroupCountsSatisfied()'s
    two independent checks exactly — a min/max range, AND is_required
    separately (a group can have min_select 0 but is_required true, which
    the range check alone would never catch) — this is purely a faster
    front door, the server-side check stays exactly as it was and remains
    the actual authority.

    Reads data-min-select/data-max-select/data-required/data-group-name
    straight off each <fieldset> rather than duplicating option-group data
    into JS — see the two forms that @include this for how those
    attributes are set.
--}}
<script>
    function menuItemForm() {
        return {
            errors: [],

            submit(event) {
                const form = event.target;
                const errors = [];

                for (const fieldset of form.querySelectorAll('fieldset[data-group-name]')) {
                    const min = parseInt(fieldset.dataset.minSelect, 10) || 0;
                    const max = parseInt(fieldset.dataset.maxSelect, 10) || Infinity;
                    const required = fieldset.dataset.required === '1';
                    const checked = fieldset.querySelectorAll('input[type=checkbox]:checked').length;
                    const name = fieldset.dataset.groupName;

                    if (checked < min || checked > max) {
                        errors.push(`"${name}" requires between ${min} and ${max} selection(s).`);
                    } else if (required && checked < 1) {
                        errors.push(`"${name}" is required.`);
                    }
                }

                if (errors.length > 0) {
                    event.preventDefault();
                    this.errors = errors;
                }
            },
        };
    }
</script>
