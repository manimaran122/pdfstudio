import { readAsDataUrl } from './page-editor';

/**
 * A single-image option (e.g. watermark image). `value` is entangled with
 * the option and holds the asset id returned by $wire.addAsset().
 */
export default ({ value }) => ({
    value,
    preview: null,
    busy: false,

    async picked(event) {
        const file = event.target.files[0];
        if (!file) return;
        this.busy = true;
        const dataUrl = await readAsDataUrl(file);
        const id = await this.$wire.addAsset(dataUrl);
        this.busy = false;
        if (id) {
            this.value = id;
            this.preview = dataUrl;
        }
    },

    clear() {
        this.value = null;
        this.preview = null;
    },
});
