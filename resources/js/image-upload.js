/**
 * Backs <x-image-upload> (ImageRules, owner 2026-11-24): checks a chosen
 * photo's type, file size and pixel size in the browser, before anything
 * is uploaded over slow mobile data, and shows how it will be cropped. A
 * problem marks the field invalid (setCustomValidity), so the form can't
 * be sent until it's fixed; the server checks the same rules again.
 */
window.imageUpload = (config) => ({
    error: '',
    preview: null,
    dimensions: '',
    previews: config.previews,

    fail(message) {
        this.error = message;
        this.$refs.input.setCustomValidity(message);
    },

    check(event) {
        const file = event.target.files[0];
        this.error = '';
        this.preview = null;
        this.dimensions = '';
        this.$refs.input.setCustomValidity('');
        if (! file) return;

        if (! config.types.includes(file.type)) {
            return this.fail(config.messages.type);
        }
        if (file.size > config.maxBytes) {
            const mb = (file.size / 1048576).toFixed(1).replace(/\.0$/, '');
            return this.fail(config.messages.size.replace(':size', mb + ' MB').replace(':max', config.maxLabel));
        }

        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            const [width, height] = [image.naturalWidth, image.naturalHeight];
            this.dimensions = `(${width}×${height} px)`;
            const [minWidth, minHeight] = config.min || [0, 0];
            if (width < (minWidth || 0) || height < (minHeight || 0)) {
                const min = minHeight ? `${minWidth}×${minHeight} px` : `${minWidth} px wide`;
                return this.fail(config.messages.small.replace(':size', `${width}×${height} px`).replace(':min', min));
            }
            if (config.square && width !== height) {
                return this.fail(config.messages.square);
            }
            this.preview = url;
        };
        image.onerror = () => this.fail(config.messages.unreadable);
        image.src = url;
    },
});
