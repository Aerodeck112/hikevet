// Chenar de semnatura: mouse, deget sau stylus. Trimite un PNG ca data URL.
export function signaturePad() {
    return {
        hasInk: false,
        get locked() { const f = this.$el.closest('fieldset'); return !!(f && f.disabled); },
        drawing: false,
        last: null,
        ctx: null,
        init() {
            const canvas = this.$refs.canvas;
            this.ctx = canvas.getContext('2d');
            this.clear();
            const pos = (e) => {
                const r = canvas.getBoundingClientRect();
                return { x: (e.clientX - r.left) * (canvas.width / r.width), y: (e.clientY - r.top) * (canvas.height / r.height) };
            };
            canvas.addEventListener('pointerdown', (e) => {
                if (this.locked) return;
                canvas.setPointerCapture(e.pointerId);
                this.drawing = true;
                this.last = pos(e);
                e.preventDefault();
            });
            canvas.addEventListener('pointermove', (e) => {
                if (!this.drawing || this.locked) return;
                const p = pos(e);
                this.ctx.beginPath();
                this.ctx.moveTo(this.last.x, this.last.y);
                this.ctx.lineTo(p.x, p.y);
                this.ctx.stroke();
                this.last = p;
                if (!this.hasInk) { this.hasInk = true; this.$dispatch('signed', true); }
                e.preventDefault();
            });
            const stop = () => { this.drawing = false; this.last = null; };
            canvas.addEventListener('pointerup', stop);
            canvas.addEventListener('pointercancel', stop);
        },
        clear() {
            if (this.locked) return;
            const c = this.$refs.canvas;
            this.ctx.fillStyle = '#fff';
            this.ctx.fillRect(0, 0, c.width, c.height);
            this.ctx.lineWidth = 2.4;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.strokeStyle = '#0f172a';
            this.hasInk = false;
            this.$dispatch('signed', false);
        },
        data() {
            return this.hasInk ? this.$refs.canvas.toDataURL('image/png') : '';
        },
    };
}
