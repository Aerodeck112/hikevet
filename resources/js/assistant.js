// Fereastra de chat cu Hiko (asistentul AI). Conversatia sta doar in browser, pe durata vizitei.
export function assistantChat({ url, token, phone }) {
    const store = 'hv-ai';
    return {
        open: false,
        busy: false,
        draft: '',
        phone,
        messages: [],
        conversation: '',
        suggestions: ['Cât costă abonamentul pentru un câine de 20 kg?', 'Cum fac o programare?', 'Ce include abonamentul?', 'Cum funcționează Wallet-ul?'],

        init() {
            try {
                const saved = JSON.parse(sessionStorage.getItem(store) || 'null');
                if (saved) Object.assign(this, { messages: saved.messages || [], conversation: saved.conversation || '' });
            } catch { /* fara istoric */ }
            if (!this.conversation) this.conversation = 'c' + Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
        },
        toggle() {
            this.open = !this.open;
            if (this.open) this.$nextTick(() => { this.$refs.input.focus(); this.scroll(); });
        },
        save() {
            try { sessionStorage.setItem(store, JSON.stringify({ messages: this.messages.slice(-24), conversation: this.conversation })); } catch { /* plin */ }
        },
        scroll() {
            this.$nextTick(() => { this.$refs.log.scrollTop = this.$refs.log.scrollHeight; });
        },
        async send(text) {
            const content = (text ?? this.draft).trim();
            if (!content || this.busy) return;
            this.draft = '';
            this.messages.push({ role: 'user', content });
            this.busy = true;
            this.scroll();
            try {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ conversation: this.conversation, messages: this.messages.filter((m) => !m.local).map(({ role, content }) => ({ role, content })) }),
                });
                const data = await r.json().catch(() => ({}));
                if (r.ok) {
                    this.messages.push({ role: 'assistant', content: data.answer, html: data.html });
                } else {
                    const msg = r.status === 429 ? 'Prea multe întrebări într-un minut. Mai încearcă puțin mai târziu.' : (data.message || 'Nu am putut răspunde acum.');
                    this.messages.push({ role: 'assistant', content: msg, html: '<p>' + msg.replace(/[<>&]/g, '') + ' Telefon: ' + phone + '.</p>', local: true });
                }
            } catch {
                this.messages.push({ role: 'assistant', content: 'Conexiunea a căzut.', html: '<p>Conexiunea a căzut. Încearcă din nou sau sună la ' + phone + '.</p>', local: true });
            } finally {
                // Mesajele locale (erori) nu se trimit modelului la urmatoarea intrebare.
                this.messages = this.messages.filter((m, i, all) => !m.local || i === all.length - 1);
                this.busy = false;
                this.save();
                this.scroll();
                this.$nextTick(() => this.$refs.input.focus());
            }
        },
    };
}
