{{--
    Free alternative to Google Maps JS (which needs a billed API key) — an
    OpenStreetMap tile layer via Leaflet (bundled in app.js, see its own
    comment) plus Nominatim (OSM's free geocoding endpoint) for the address
    search box. The existing customer-facing "Get directions" embed on
    branches/index.blade.php is untouched — it already uses the free,
    keyless Google Maps *embed* (output=embed), a different thing from the
    JS API this form would otherwise have needed.

    lat/lng inputs stay visible and manually editable (x-model) as a
    fallback: dragging/clicking the map or the search/GPS buttons update
    them, but typing a coordinate directly still works and re-centres the
    map on blur — the form never depends on the map having loaded.
--}}
<script>
    function branchLocationPicker(initialLat, initialLng) {
        return {
            lat: initialLat,
            lng: initialLng,
            map: null,
            marker: null,
            searchQuery: '',
            searching: false,
            locating: false,
            error: null,

            init() {
                const hasCoords = this.lat !== null && this.lat !== '' && this.lng !== null && this.lng !== '';
                const start = hasCoords ? [parseFloat(this.lat), parseFloat(this.lng)] : [5.6037, -0.1870];

                this.map = L.map(this.$refs.map).setView(start, hasCoords ? 16 : 12);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19,
                }).addTo(this.map);

                this.marker = L.marker(start, { draggable: true }).addTo(this.map);
                this.marker.on('dragend', () => this.setFromLatLng(this.marker.getLatLng()));
                this.map.on('click', (event) => {
                    this.marker.setLatLng(event.latlng);
                    this.setFromLatLng(event.latlng);
                });
            },

            setFromLatLng(latlng) {
                this.lat = Math.round(latlng.lat * 1e7) / 1e7;
                this.lng = Math.round(latlng.lng * 1e7) / 1e7;
            },

            recenter(lat, lng, zoom = 16) {
                this.map.setView([lat, lng], zoom);
                this.marker.setLatLng([lat, lng]);
                this.setFromLatLng({ lat, lng });
            },

            // Manual edits to the lat/lng fields still move the pin — the
            // map is a convenience, not the only way to set the location.
            recenterFromInputs() {
                const lat = parseFloat(this.lat);
                const lng = parseFloat(this.lng);

                if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    this.recenter(lat, lng);
                }
            },

            async search() {
                if (!this.searchQuery.trim()) {
                    return;
                }

                this.searching = true;
                this.error = null;

                try {
                    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=gh&q='
                        + encodeURIComponent(this.searchQuery);
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const results = await response.json();

                    if (! results.length) {
                        this.error = @js(__('No results found. Try dropping the pin manually instead.'));
                        return;
                    }

                    this.recenter(parseFloat(results[0].lat), parseFloat(results[0].lon));
                } catch (e) {
                    this.error = @js(__('Search failed — try again.'));
                } finally {
                    this.searching = false;
                }
            },

            useMyLocation() {
                if (! navigator.geolocation) {
                    this.error = @js(__('Location is not supported on this device.'));
                    return;
                }

                this.locating = true;
                this.error = null;

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.recenter(position.coords.latitude, position.coords.longitude, 17);
                        this.locating = false;
                    },
                    () => {
                        this.error = @js(__('Could not get your current location.'));
                        this.locating = false;
                    },
                );
            },
        };
    }
</script>
