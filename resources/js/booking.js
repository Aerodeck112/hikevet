// Formularul de cerere de programare: zile ca butoane, intervale, ore exacte doar la medicii cu orar,
// rezumat live si personajul (Hiko sau Raza, dupa specie) care comenteaza ce completezi.
import { t, intl } from './i18n';
const fmt = (date, options) => new Intl.DateTimeFormat(intl(), options).format(date);
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
                out.push({ value: iso(d), top: i === 0 ? t('Azi') : i === 1 ? t('Mâine') : fmt(d, { weekday: 'short' }).replace('.', ''), num: d.getDate(), month: fmt(d, { month: 'short' }).replace('.', '') });
            }
            return out;
        },
        get serviceName() {
            return this.services.find((s) => String(s.id) === this.service)?.name || t('Consultație');
        },
        get doctorName() {
            return this.doctors.find((d) => String(d.id) === this.doctor)?.name || t('Oricare medic disponibil');
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
            if (this.time) return t('ora :time', { time: this.time });
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
            if (name) this.say(this.species === 'Pisică' ? t('Miau, salut :name!', { name }) : t('Salut, :name! Ham!', { name }));
        },
        speciesChanged() {
            const name = this.pet.trim();
            this.$nextTick(() => {
                if (this.species === 'Câine') this.say(name ? t(':name e de-al meu! Ham-ham!', { name }) : t('Un coleg de-al meu! Ham!'));
                else if (this.species === 'Pisică') this.say(name ? t(':name, o colegă! Am grijă de ea.', { name }) : t('Miau! O pisică, ce bine.'));
                else this.say(t('Oh, ceva nou! Abia aștept să-l cunosc.'));
            });
        },
        serviceChanged() {
            this.say(this.service ? t('Notat: :service.', { service: this.serviceName.toLowerCase() }) : t('Nicio grijă, medicul vede la consult.'));
            this.loadSlots();
        },
        dateChanged() {
            this.time = '';
            if (this.period && this.periods.some((p) => p.key === this.period && this.periodPassed(p))) this.period = '';
            this.say(t(':day. Îmi notez în calendar!', { day: this.dayLabel }));
            this.loadSlots();
        },
        periodChanged(p) {
            this.time = '';
            const lines = { dimineata: t('Dimineața? Eu sunt treaz de la 6.'), pranz: t('La prânz, după masa mea. Perfect.'), 'dupa-amiaza': t('După-amiaza e cea mai bună tură.'), seara: t('Seara e liniște în clinică.') };
            this.say(lines[p.key] || t('Perfect!'));
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
