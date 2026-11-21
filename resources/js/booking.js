// Formularul de cerere de programare: zile ca butoane, intervale, ore exacte doar la medicii cu orar,
// rezumat live si personajul (Hiko sau Raza, dupa specie) care comenteaza ce completezi.
const fmt = (date, options) => new Intl.DateTimeFormat('ro-RO', options).format(date);
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

export function bookingForm({ services, doctors, periods, slotsUrl, old }) {
    return {
        services, doctors, periods,
        service: old.service ? String(old.service) : '',
        doctor: old.doctor ? String(old.doctor) : '',
        date: old.date || '',
        time: old.time || '',
        period: old.period || '',
        pet: old.pet || '',
        species: old.species || '',
        slots: [],
        sending: false,

        init() {
            if (this.doctor && this.date) this.loadSlots();
        },
        get days() {
            const out = [];
            const today = new Date();
            for (let i = 0; i < 8; i++) {
                const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() + i);
                out.push({ value: iso(d), top: i === 0 ? 'Azi' : i === 1 ? 'Mâine' : fmt(d, { weekday: 'short' }).replace('.', ''), num: d.getDate(), month: fmt(d, { month: 'short' }).replace('.', '') });
            }
            return out;
        },
        get serviceName() {
            return this.services.find((s) => String(s.id) === this.service)?.name || 'Consultație';
        },
        get doctorName() {
            return this.doctors.find((d) => String(d.id) === this.doctor)?.name || 'Oricare medic disponibil';
        },
        get dayLabel() {
            if (!this.date) return '–';
            const [y, m, d] = this.date.split('-').map(Number);
            const label = fmt(new Date(y, m - 1, d), { weekday: 'long', day: 'numeric', month: 'long' });
            return label.charAt(0).toUpperCase() + label.slice(1);
        },
        get dayShort() {
            if (!this.date) return '';
            const [y, m, d] = this.date.split('-').map(Number);
            return fmt(new Date(y, m - 1, d), { day: 'numeric', month: 'short' }).replace('.', '');
        },
        get timeLabel() {
            if (this.time) return 'ora ' + this.time;
            const p = this.periods.find((x) => x.key === this.period);
            return p ? `${p.label}, ${p.from}–${p.to}` : '–';
        },
        periodPassed(p) {
            if (this.date !== iso(new Date())) return false;
            const [h, m] = p.to.split(':').map(Number);
            const end = new Date();
            end.setHours(h, m, 0, 0);
            return end.getTime() - Date.now() < 30 * 60 * 1000;
        },
        say(text) {
            const ref = this.species === 'Pisică' ? this.$refs.cat : this.$refs.dog;
            ref?.dispatchEvent(new CustomEvent('buddy:say', { detail: text }));
        },
        greetPet() {
            const name = this.pet.trim();
            if (name) this.say(this.species === 'Pisică' ? `Miau, salut ${name}!` : `Salut, ${name}! Ham!`);
        },
        speciesChanged() {
            const name = this.pet.trim();
            this.$nextTick(() => {
                if (this.species === 'Câine') this.say(name ? `${name} e de-al meu! Ham-ham!` : 'Un coleg de-al meu! Ham!');
                else if (this.species === 'Pisică') this.say(name ? `${name}, o colegă! Am grijă de ea.` : 'Miau! O pisică, ce bine.');
                else this.say('Oh, ceva nou! Abia aștept să-l cunosc.');
            });
        },
        serviceChanged() {
            this.say(this.service ? `Notat: ${this.serviceName.toLowerCase()}.` : 'Nicio grijă, medicul vede la consult.');
            this.loadSlots();
        },
        dateChanged() {
            this.time = '';
            if (this.period && this.periods.some((p) => p.key === this.period && this.periodPassed(p))) this.period = '';
            this.say(`${this.dayLabel}. Îmi notez în calendar!`);
            this.loadSlots();
        },
        periodChanged(p) {
            this.time = '';
            const lines = { dimineata: 'Dimineața? Eu sunt treaz de la 6.', pranz: 'La prânz, după masa mea. Perfect.', 'dupa-amiaza': 'După-amiaza e cea mai bună tură.', seara: 'Seara e liniște în clinică.' };
            this.say(lines[p.key] || 'Perfect!');
        },
        async loadSlots() {
            this.slots = [];
            const doc = this.doctors.find((d) => String(d.id) === this.doctor);
            if (!doc || !doc.exact || !this.date) {
                this.time = '';
                return;
            }
            try {
                const r = await fetch(`${slotsUrl}?doctor=${this.doctor}&date=${this.date}&service=${this.service}`, { headers: { Accept: 'application/json' } });
                this.slots = r.ok ? (await r.json()).slots : [];
            } catch {
                this.slots = [];
            }
            if (!this.slots.includes(this.time)) this.time = '';
        },
    };
}
