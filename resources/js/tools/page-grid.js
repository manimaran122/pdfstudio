/**
 * Page grid for page-based tool options (see PdfTools "pages").
 *
 * mode "select":   value = [pageNumber, ...]          (remove / extract)
 * mode "organize": value = [{page, rotation}, ...]     (the new page order)
 * mode "rotate":   value = {pageNumber: degrees, ...}  (clockwise)
 *
 * `value` is entangled with the Livewire option, `thumb` is a URL with
 * __PAGE__ in place of the page number.
 */
export default ({ mode, count, thumb, value }) => ({
    mode,
    count,
    value,
    items: [],
    dragging: null,
    over: null,
    anchor: null,
    nextKey: 1,

    init() {
        if (this.mode === 'organize') {
            const start = Array.isArray(this.value) && this.value.length
                ? this.value
                : this.pages().map((page) => ({ page, rotation: 0 }));
            this.items = start.map((item) => ({ ...item, key: this.nextKey++ }));
            this.sync();
        } else if (this.mode === 'rotate' && (Array.isArray(this.value) || !this.value)) {
            this.value = {};
        } else if (this.mode === 'select' && !Array.isArray(this.value)) {
            this.value = [];
        }
    },

    pages() {
        return Array.from({ length: this.count }, (_, i) => i + 1);
    },

    src(page) {
        return thumb.replace('__PAGE__', page);
    },

    // --- select -----------------------------------------------------------

    selected(page) {
        return this.value.includes(page);
    },

    toggle(page, event) {
        if (event?.shiftKey && this.anchor) {
            const [from, to] = [Math.min(this.anchor, page), Math.max(this.anchor, page)];
            const range = this.pages().filter((p) => p >= from && p <= to);
            this.value = [...new Set([...this.value, ...range])].sort((a, b) => a - b);
        } else {
            this.value = this.selected(page)
                ? this.value.filter((p) => p !== page)
                : [...this.value, page].sort((a, b) => a - b);
        }
        this.anchor = page;
    },

    pick(which) {
        const pick = { all: () => true, none: () => false, odd: (p) => p % 2 === 1, even: (p) => p % 2 === 0 }[which];
        this.value = this.pages().filter(pick);
    },

    get rangeText() {
        const ranges = [];
        for (const page of this.value) {
            const last = ranges[ranges.length - 1];
            if (last && page === last[1] + 1) last[1] = page;
            else ranges.push([page, page]);
        }
        return ranges.map(([a, b]) => (a === b ? `${a}` : `${a}-${b}`)).join(', ');
    },

    set rangeText(text) {
        const pages = new Set();
        for (const part of text.split(',')) {
            const match = part.trim().match(/^(\d*)\s*(?:-\s*(\d*))?$/);
            if (!match || (!match[1] && match[2] === undefined)) continue;
            const from = parseInt(match[1] || '1', 10);
            const to = match[2] === undefined ? from : parseInt(match[2] || String(this.count), 10);
            for (let p = Math.min(from, to); p <= Math.max(from, to); p++) {
                if (p >= 1 && p <= this.count) pages.add(p);
            }
        }
        this.value = [...pages].sort((a, b) => a - b);
    },

    // --- rotate -----------------------------------------------------------

    rotation(page) {
        return Number(this.value?.[page] ?? 0);
    },

    turn(page, degrees) {
        this.value = { ...this.value, [page]: (this.rotation(page) + degrees + 360) % 360 };
    },

    turnAll(degrees) {
        this.value = Object.fromEntries(this.pages().map((p) => [p, (this.rotation(p) + degrees + 360) % 360]));
    },

    // --- organize ---------------------------------------------------------

    sync() {
        this.value = this.items.map(({ page, rotation }) => ({ page, rotation }));
    },

    rotateItem(index) {
        this.items[index].rotation = (this.items[index].rotation + 90) % 360;
        this.sync();
    },

    duplicate(index) {
        this.items.splice(index + 1, 0, { ...this.items[index], key: this.nextKey++ });
        this.sync();
    },

    discard(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
            this.sync();
        }
    },

    move(index, offset) {
        const target = index + offset;
        if (target < 0 || target >= this.items.length) return;
        const [item] = this.items.splice(index, 1);
        this.items.splice(target, 0, item);
        this.sync();
    },

    drop(targetIndex) {
        if (this.dragging === null || this.dragging === targetIndex) return;
        const [item] = this.items.splice(this.dragging, 1);
        this.items.splice(targetIndex, 0, item);
        this.dragging = this.over = null;
        this.sync();
    },

    reset() {
        this.items = this.pages().map((page) => ({ page, rotation: 0, key: this.nextKey++ }));
        this.sync();
    },
});
