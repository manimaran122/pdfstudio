/**
 * Click-to-place page editor (see PdfTools "placements").
 *
 * value = [{kind, page, x, y, w, h, text?, size?, color?, asset?, name?}]
 * with x/y/w/h as fractions of the page as displayed. Pick a kind, then
 * click the page (default size) or drag out a box. Items can be moved,
 * resized from the corner handle, edited and deleted.
 *
 * Images and signatures are uploaded through $wire.addAsset(dataUrl),
 * which returns an asset id; the data URL is kept locally for display.
 */
const DEFAULTS = {
    text: { w: 0.3, h: 0.045, text: 'Text', size: 14, color: '#17181C' },
    rect: { w: 0.25, h: 0.12, color: '#2338A8' },
    highlight: { w: 0.3, h: 0.03, color: '#FACC15' },
    note: { w: 0.035, h: 0.035, text: '', color: '#FACC15', square: true },
    redact: { w: 0.3, h: 0.03 },
    image: { w: 0.25 },
    signature: { w: 0.25 },
    'field-text': { w: 0.3, h: 0.04 },
    'field-checkbox': { w: 0.03, square: true },
};

const LABELS = {
    text: 'Text', rect: 'Box', highlight: 'Highlight', note: 'Note', redact: 'Redact area',
    image: 'Image', signature: 'Signature', 'field-text': 'Text field', 'field-checkbox': 'Checkbox',
};

export default ({ count, large, kinds, value }) => ({
    count,
    kinds,
    value,
    labels: LABELS,
    page: 1,
    tool: kinds[0],
    items: [],
    selected: null,
    aspect: 1.294,
    previews: {},
    pendingAsset: null,
    action: null,
    nextId: 1,
    fieldCount: 0,
    signing: false,

    init() {
        this.items = (Array.isArray(this.value) ? this.value : []).map((item) => ({ ...item, _id: this.nextId++ }));
    },

    src(page) {
        return large.replace('__PAGE__', page);
    },

    get pageItems() {
        return this.items.filter((item) => item.page === this.page);
    },

    get current() {
        return this.items.find((item) => item._id === this.selected) ?? null;
    },

    sync() {
        this.value = this.items.map(({ _id, ...item }) => item);
    },

    go(page) {
        this.page = Math.min(this.count, Math.max(1, page));
        this.selected = null;
    },

    loaded(event) {
        this.aspect = event.target.naturalHeight / event.target.naturalWidth || this.aspect;
    },

    // --- picking a kind ----------------------------------------------------

    async choose(kind) {
        this.tool = kind;
        this.pendingAsset = null;

        if (kind === 'image') {
            this.$refs.imageInput.value = '';
            this.$refs.imageInput.click();
        } else if (kind === 'signature') {
            this.signing = true;
        }
    },

    async imagePicked(event) {
        const file = event.target.files[0];
        if (file) await this.useAsset(await readAsDataUrl(file));
    },

    async useAsset(dataUrl) {
        const id = await this.$wire.addAsset(dataUrl);
        if (!id) return;
        const size = await imageSize(dataUrl);
        this.previews[id] = dataUrl;
        this.pendingAsset = { id, ratio: size.height / size.width };
        this.signing = false;
    },

    // --- pointer handling ---------------------------------------------------

    point(event) {
        const box = this.$refs.page.getBoundingClientRect();
        return {
            x: Math.min(1, Math.max(0, (event.clientX - box.left) / box.width)),
            y: Math.min(1, Math.max(0, (event.clientY - box.top) / box.height)),
        };
    },

    startDraw(event) {
        if (event.button !== 0) return;
        if ((this.tool === 'image' || this.tool === 'signature') && !this.pendingAsset) {
            this.choose(this.tool);
            return;
        }
        const start = this.point(event);
        this.action = { type: 'draw', start, end: start };
        this.selected = null;
        event.currentTarget.setPointerCapture(event.pointerId);
    },

    startMove(event, item) {
        if (event.button !== 0) return;
        event.stopPropagation();
        this.selected = item._id;
        this.action = { type: 'move', item, from: this.point(event), origin: { x: item.x, y: item.y } };
        this.$refs.surface.setPointerCapture(event.pointerId);
    },

    startResize(event, item) {
        event.stopPropagation();
        this.selected = item._id;
        this.action = { type: 'resize', item, from: this.point(event), origin: { w: item.w, h: item.h } };
        this.$refs.surface.setPointerCapture(event.pointerId);
    },

    pointerMove(event) {
        if (!this.action) return;
        const at = this.point(event);
        const { type, item, from, origin } = this.action;

        if (type === 'draw') {
            this.action.end = at;
        } else if (type === 'move') {
            item.x = clamp(origin.x + at.x - from.x, 0, 1 - item.w);
            item.y = clamp(origin.y + at.y - from.y, 0, 1 - item.h);
        } else if (type === 'resize') {
            item.w = clamp(origin.w + at.x - from.x, 0.01, 1 - item.x);
            item.h = item.asset || DEFAULTS[item.kind].square
                ? Math.min(item.w * (item.ratio ?? 1) / this.aspect, 1 - item.y)
                : clamp(origin.h + at.y - from.y, 0.01, 1 - item.y);
        }
    },

    pointerUp() {
        if (!this.action) return;
        const action = this.action;
        this.action = null;

        if (action.type === 'draw') {
            this.add(action.start, action.end);
        } else {
            this.sync();
        }
    },

    get drawBox() {
        if (this.action?.type !== 'draw') return null;
        const { start, end } = this.action;
        return boxStyle({ x: Math.min(start.x, end.x), y: Math.min(start.y, end.y), w: Math.abs(end.x - start.x), h: Math.abs(end.y - start.y) });
    },

    add(start, end) {
        const kind = this.tool;
        const defaults = DEFAULTS[kind];
        const dragged = Math.abs(end.x - start.x) > 0.01 && Math.abs(end.y - start.y) > 0.005;
        const item = { _id: this.nextId++, kind, page: this.page };
        let w = defaults.w;
        let h = defaults.h;

        if (this.pendingAsset && (kind === 'image' || kind === 'signature')) {
            item.asset = this.pendingAsset.id;
            item.ratio = this.pendingAsset.ratio;
        }

        if (dragged) {
            w = Math.abs(end.x - start.x);
            h = Math.abs(end.y - start.y);
        }

        if (item.asset) h = w * item.ratio / this.aspect;
        else if (defaults.square) h = w / this.aspect;

        item.x = clamp(dragged ? Math.min(start.x, end.x) : start.x - w / 2, 0, 1 - w);
        item.y = clamp(dragged ? Math.min(start.y, end.y) : start.y - h / 2, 0, 1 - h);
        item.w = w;
        item.h = Math.min(h, 1);

        for (const key of ['text', 'size', 'color']) {
            if (defaults[key] !== undefined) item[key] = defaults[key];
        }
        if (kind.startsWith('field-')) {
            item.name = `${kind === 'field-text' ? 'text' : 'check'}_${++this.fieldCount}`;
        }

        this.items.push(item);
        this.selected = item._id;
        this.sync();
    },

    remove(id = this.selected) {
        this.items = this.items.filter((item) => item._id !== id);
        this.selected = null;
        this.sync();
    },

    style(item) {
        return boxStyle(item);
    },

    countOn(page) {
        return this.items.filter((item) => item.page === page).length;
    },
});

/**
 * Signature pad: draw with the pointer, type in a script font, or upload
 * an image. `done(dataUrl)` receives a transparent PNG.
 */
export const signaturePad = ({ done }) => ({
    tab: 'draw',
    typed: '',
    font: 'Caveat',
    color: '#17181C',
    drawing: false,
    empty: true,

    init() {
        this.$nextTick(() => this.clear());
    },

    clear() {
        const canvas = this.$refs.canvas;
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
        this.empty = true;
    },

    down(event) {
        const ctx = this.$refs.canvas.getContext('2d');
        const { x, y } = this.at(event);
        ctx.strokeStyle = this.color;
        ctx.lineWidth = 3;
        ctx.lineCap = ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(x, y);
        this.drawing = true;
        event.currentTarget.setPointerCapture(event.pointerId);
    },

    move(event) {
        if (!this.drawing) return;
        const ctx = this.$refs.canvas.getContext('2d');
        const { x, y } = this.at(event);
        ctx.lineTo(x, y);
        ctx.stroke();
        this.empty = false;
    },

    up() {
        this.drawing = false;
    },

    at(event) {
        const canvas = this.$refs.canvas;
        const box = canvas.getBoundingClientRect();
        return { x: (event.clientX - box.left) * canvas.width / box.width, y: (event.clientY - box.top) * canvas.height / box.height };
    },

    async save() {
        if (this.tab === 'draw') {
            if (!this.empty) done(trim(this.$refs.canvas));
        } else if (this.tab === 'type' && this.typed.trim()) {
            await document.fonts.load(`96px "${this.font}"`);
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.font = `96px "${this.font}"`;
            canvas.width = Math.ceil(ctx.measureText(this.typed).width) + 40;
            canvas.height = 150;
            ctx.font = `96px "${this.font}"`;
            ctx.fillStyle = this.color;
            ctx.textBaseline = 'middle';
            ctx.fillText(this.typed, 20, 75);
            done(trim(canvas));
        }
    },

    async upload(event) {
        const file = event.target.files[0];
        if (file) done(await readAsDataUrl(file));
    },
});

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), Math.max(min, max));
}

function boxStyle({ x, y, w, h }) {
    return `left:${x * 100}%;top:${y * 100}%;width:${w * 100}%;height:${h * 100}%`;
}

export function readAsDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

function imageSize(dataUrl) {
    return new Promise((resolve) => {
        const image = new Image();
        image.onload = () => resolve({ width: image.naturalWidth || 1, height: image.naturalHeight || 1 });
        image.src = dataUrl;
    });
}

/** Crop a canvas to its drawn pixels and return a PNG data URL. */
function trim(canvas) {
    const ctx = canvas.getContext('2d');
    const { data, width, height } = ctx.getImageData(0, 0, canvas.width, canvas.height);
    let [top, left, right, bottom] = [height, width, 0, 0];

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (data[(y * width + x) * 4 + 3] > 0) {
                top = Math.min(top, y);
                bottom = Math.max(bottom, y);
                left = Math.min(left, x);
                right = Math.max(right, x);
            }
        }
    }

    if (right < left) return canvas.toDataURL('image/png');

    const pad = 6;
    const out = document.createElement('canvas');
    out.width = right - left + pad * 2;
    out.height = bottom - top + pad * 2;
    out.getContext('2d').drawImage(canvas, left - pad, top - pad, out.width, out.height, 0, 0, out.width, out.height);

    return out.toDataURL('image/png');
}
