{{--
    window.expressTax(food, fee, paymentMethod) for the POS and checkout
    running totals (Express POS step 9) — the same sums as
    TaxService::quote()/split(), which stays the one that's charged.
    Returns { lines: [{ label, amount, inclusive }], added } where `added`
    goes on top of the total (0 when prices include the tax). $taxConfig
    is TaxService::clientConfig() (null: no tax, nothing to show).
--}}
<script>
    window.expressTax = (function (config) {
        const split = (amount, rates, inclusive) => {
            if (amount <= 0) return rates.map(() => 0);
            if (!inclusive) return rates.map((rate) => Math.round(amount * rate / 10000));
            const combined = rates.reduce((sum, rate) => sum + rate, 0);
            const total = Math.round(amount * combined / (10000 + combined));
            let given = 0;
            return rates.map((rate, i) => {
                const share = i === rates.length - 1 ? total - given : Math.round(total * rate / combined);
                given += share;
                return share;
            });
        };

        return function (food, fee, paymentMethod) {
            if (!config || config.untaxed.includes(paymentMethod)) return { lines: [], added: 0 };
            const rates = config.lines.map((line) => line.rate_basis_points);
            const onFood = split(Math.max(0, food), rates, config.inclusive);
            const onFee = split(config.delivery_taxed ? Math.max(0, fee || 0) : 0, rates, config.inclusive);
            const lines = config.lines.map((line, i) => ({ label: line.label, amount: onFood[i] + onFee[i], inclusive: config.inclusive }));
            const total = lines.reduce((sum, line) => sum + line.amount, 0);

            return { lines, added: config.inclusive ? 0 : total };
        };
    })(@js($taxConfig ?? null));
</script>
