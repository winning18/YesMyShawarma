{{-- The pharmacy and shop till's state and actions (R3), merged into posTerminal(). --}}
<script>
    function retailTillState() {
        const urls = {
            search: @js(route('dashboard.pos.products')),
            scan: @js(route('dashboard.pos.scan')),
            add: @js(route('dashboard.pos.add-product')),
            held: @js(route('dashboard.pos.held')),
            findSale: @js(route('dashboard.pos.returns.find')),
            base: @js(url('/dashboard/pos')),
        };

        return {
            query: '',
            results: [],
            searching: false,
            scanError: null,
            scanAdded: null,
            stockWarning: null,
            tillMessage: null,
            tillError: null,
            tillBusy: false,
            approval: { staff_code: '', pin: '' },
            priceLine: null,
            newPrice: '',
            heldOpen: false,
            held: [],
            returnOpen: false,
            returnReference: '',
            returnSale: null,
            returnQuantities: {},
            returnDamaged: [],
            returnReason: '',
            returnCredit: 0,
            pharmacy: @js($cart['pharmacy'] ?? null),
            checkOpen: false,

            openCheck() {
                this.checkOpen = true;
                this.tillError = null;
            },

            // With a photo, so a form post, not JSON.
            async saveCheck(form) {
                this.tillBusy = true;
                this.tillError = null;
                try {
                    const response = await fetch(@js(route('dashboard.pos.pharmacy-check')), {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: new FormData(form),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(window.failureMessage(response, payload));
                    this.applyPayload(payload);
                    this.checkOpen = false;
                    form.reset();
                } catch (e) {
                    this.tillError = window.explainError(e);
                } finally {
                    this.tillBusy = false;
                }
            },

            async tillRequest(url, method = 'GET', body = null) {
                const response = await fetch(url, { method, headers: this.headers(), body: body ? JSON.stringify(body) : undefined });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(window.failureMessage(response, payload));
                return payload;
            },

            applyPayload(payload) {
                if ('pharmacy' in payload) this.pharmacy = payload.pharmacy;
                this.lines = payload.lines;
                this.subtotal = payload.subtotal;
                this.foodSubtotal = payload.foodSubtotal;
                this.discount = payload.discount;
                this.promotionBanner = payload.promotionBanner;
            },

            async searchProducts() {
                this.scanError = null;
                const q = this.query.trim();
                if (!q) { this.results = []; return; }
                this.searching = true;
                try {
                    this.results = (await this.tillRequest(urls.search + '?q=' + encodeURIComponent(q))).products.map((p) => ({ ...p, qty: 1 }));
                } catch (e) {
                    this.scanError = window.explainError(e);
                } finally {
                    this.searching = false;
                }
            },

            // Enter (a scanner always sends one): the code first; a single match otherwise.
            async scanOrPick() {
                const code = this.query.trim();
                if (!code) return;
                this.scanError = null;
                try {
                    const payload = await this.tillRequest(urls.scan, 'POST', { code });
                    this.applyPayload(payload);
                    this.done(payload.added, payload.stock_warning);
                } catch (e) {
                    if (this.results.length === 1) return this.addProduct(this.results[0], null);
                    this.scanError = window.explainError(e);
                }
            },

            async addProduct(product, pack) {
                try {
                    const payload = await this.tillRequest(urls.add, 'POST', { menu_item_id: product.id, pack_id: pack ? pack.id : null, quantity: product.qty || 1 });
                    this.applyPayload(payload);
                    this.done(product.name + (pack ? ' (' + pack.name + ')' : ''), payload.stock_warning);
                } catch (e) {
                    this.scanError = window.explainError(e);
                }
            },

            done(added, warning = null) {
                this.scanAdded = added ? @js(__('Added:')) + ' ' + added : null;
                this.stockWarning = warning;
                this.query = '';
                this.results = [];
                this.error = null;
                this.$nextTick(() => this.$refs.scan?.focus());
            },

            openPrice(line) {
                this.priceLine = line;
                this.newPrice = (line.unit_price_snapshot / 100).toFixed(2);
                this.approval = { staff_code: '', pin: '' };
                this.tillError = null;
            },

            async savePrice() {
                this.tillBusy = true;
                try {
                    this.applyPayload(await this.tillRequest(urls.base + '/cart/' + this.priceLine.line_id + '/price', 'POST', {
                        price: this.newPrice === '' ? null : this.newPrice, ...this.approval,
                    }));
                    this.priceLine = null;
                } catch (e) {
                    this.tillError = window.explainError(e);
                } finally {
                    this.tillBusy = false;
                }
            },

            async holdSale() {
                const label = prompt(@js(__('A name to find it by (optional), e.g. the customer\'s name:')), '');
                if (label === null) return;
                try {
                    this.applyPayload(await this.tillRequest(urls.held, 'POST', { label }));
                    this.tillMessage = @js(__('Sale held. Recall it from Held sales.'));
                    this.$nextTick(() => this.$refs.scan?.focus());
                } catch (e) {
                    this.error = window.explainError(e);
                }
            },

            async openHeld() {
                this.heldOpen = true;
                this.tillError = null;
                try {
                    this.held = (await this.tillRequest(urls.held)).held;
                } catch (e) {
                    this.tillError = window.explainError(e);
                }
            },

            async recallSale(sale) {
                try {
                    this.applyPayload(await this.tillRequest(urls.held + '/' + sale.id + '/recall', 'POST'));
                    this.heldOpen = false;
                    this.tillMessage = null;
                } catch (e) {
                    this.tillError = window.explainError(e);
                }
            },

            async discardSale(sale) {
                if (!confirm(@js(__('Delete this held sale? Its items are not sold.')))) return;
                try {
                    await this.tillRequest(urls.held + '/' + sale.id, 'DELETE');
                    this.held = this.held.filter((s) => s.id !== sale.id);
                } catch (e) {
                    this.tillError = window.explainError(e);
                }
            },

            openReturn() {
                this.returnOpen = true;
                this.returnSale = null;
                this.returnReference = '';
                this.returnReason = '';
                this.returnQuantities = {};
                this.returnDamaged = [];
                this.approval = { staff_code: '', pin: '' };
                this.tillError = null;
            },

            async findSale() {
                this.tillError = null;
                try {
                    this.returnSale = await this.tillRequest(urls.findSale + '?reference=' + encodeURIComponent(this.returnReference.trim()));
                    this.returnQuantities = Object.fromEntries(this.returnSale.lines.map((l) => [l.id, 0]));
                } catch (e) {
                    this.returnSale = null;
                    this.tillError = window.explainError(e);
                }
            },

            returnTotal() {
                if (!this.returnSale) return 0;
                return this.returnSale.lines.reduce((sum, l) => sum + l.unit_refund * Math.min(l.returnable, Math.max(0, this.returnQuantities[l.id] || 0)), 0);
            },

            async saveReturn() {
                this.tillBusy = true;
                this.tillError = null;
                try {
                    const result = await this.tillRequest(urls.base + '/returns/' + this.returnSale.order.id, 'POST', {
                        quantities: this.returnQuantities, damaged: this.returnDamaged, reason: this.returnReason, ...this.approval,
                    });
                    this.returnOpen = false;
                    this.returnCredit = result.amount;
                    this.tillMessage = result.message;
                } catch (e) {
                    this.tillError = window.explainError(e);
                } finally {
                    this.tillBusy = false;
                }
            },
        };
    }
</script>
