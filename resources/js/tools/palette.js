/**
 * Tool search palette. Opens on Ctrl/Cmd+K or an `open-palette` event;
 * arrow keys move, Enter opens, Escape closes.
 */
export default ({ tools }) => ({
    tools,
    open: false,
    q: '',
    selected: 0,

    init() {
        window.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                this.open ? this.close() : this.show();
            }
        });
    },

    show() {
        this.open = true;
        this.q = '';
        this.selected = 0;
        this.$nextTick(() => this.$refs.input.focus());
    },

    close() {
        this.open = false;
    },

    get results() {
        const q = this.q.trim().toLowerCase();
        return this.tools.filter((tool) => !q || `${tool.name} ${tool.desc} ${tool.badge} ${tool.category}`.toLowerCase().includes(q));
    },

    move(step) {
        const count = this.results.length;
        if (!count) return;
        this.selected = Math.min(Math.max(this.selected + step, 0), count - 1);
        this.$nextTick(() => this.$refs.list.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' }));
    },

    go() {
        const tool = this.results[this.selected];
        if (tool?.url) window.location.href = tool.url;
    },
});
