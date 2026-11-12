// Configuratorul de abonamente in 6 pasi (portare a assets/hv-form.js din simulatorul 2.7.0).
// Diferenta fata de original: oferta si preturile vin exclusiv de la server.
const DOG_BREEDS = ['Metis', 'Bichon', 'Bulldog Francez', 'Bulldog Englez', 'Cocker Spaniel', 'Ciobănesc German', 'Golden Retriever', 'Labrador Retriever', 'Pug', 'Teckel', 'Yorkshire Terrier', 'Shih Tzu', 'Husky', 'Rottweiler', 'Beagle', 'Chihuahua'];
const CAT_BREEDS = ['Europeană', 'Metis', 'British Shorthair', 'Scottish Fold', 'Maine Coon', 'Ragdoll', 'Siamese', 'Persană', 'Bengaleză', 'Sphynx', 'Norvegiană de pădure'];
const STORAGE_KEY = 'hv-configurator-v1';

export function configurator(config) {
    return {
        step: 1,
        error: '',
        offer: null,
        loading: false,
        sent: false,
        result: null,
        locked: false,
        signed: false,
        previewOpen: false,
        token: config.token,
        form: {
            pet_name: '', agree: false, species: '', sex: '', dob: '', breed: '', breed_other: '', weight: '',
            sterilized: '', chronic: '', chronic_text: '', emergency: '', routine: [],
            owner_name: config.prefill.name || '', owner_email: config.prefill.email || '', owner_phone: config.prefill.phone || '',
            owner_addr: config.prefill.address || '', owner_cnp: '', owner_ci_serie: '', owner_ci_nr: '', pet_microchip: '',
            legal: { contract: false, annex: false, terms: false, guide: false, privacy: false, not_insurance: false },
            payment_period_months: 1,
        },
        init() {
            // Salvare automata doar a datelor despre animal. CNP, CI si semnatura nu se pastreaza in browser.
            try {
                const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || 'null');
                if (saved) ['pet_name', 'species', 'sex', 'dob', 'breed', 'breed_other', 'weight', 'sterilized', 'chronic', 'chronic_text', 'emergency', 'routine']
                    .forEach((k) => { if (saved[k] !== undefined) this.form[k] = saved[k]; });
            } catch (e) { /* ignorat */ }
            this.$watch('form', () => this.autosave());
        },
        autosave() {
            const { pet_name, species, sex, dob, breed, breed_other, weight, sterilized, chronic, chronic_text, emergency, routine } = this.form;
            try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ pet_name, species, sex, dob, breed, breed_other, weight, sterilized, chronic, chronic_text, emergency, routine })); } catch (e) { /* ignorat */ }
        },
        get breeds() { return this.form.species === 'cat' ? CAT_BREEDS : DOG_BREEDS; },
        get breedLabel() { return this.form.breed === '__other__' ? this.form.breed_other : this.form.breed; },
        get allLegal() { return Object.values(this.form.legal).every(Boolean); },
        get periodPrice() {
            if (!this.offer) return 0;
            return { 1: this.offer.final.monthly, 6: this.offer.final.m6, 12: this.offer.final.m12 }[this.form.payment_period_months];
        },
        pick(field, value) {
            if (this.locked) return;
            this.form[field] = value;
            if (field === 'species') { this.form.breed = ''; this.form.breed_other = ''; }
        },
        validate(n) {
            const f = this.form;
            if (n === 1 && (!f.pet_name.trim() || !f.agree)) return 'Completează numele animăluțului și bifează acordul.';
            if (n === 2 && (!f.species || !f.sex)) return 'Alege specia și sexul.';
            if (n === 3) {
                const ts = f.dob ? Date.parse(f.dob + 'T00:00:00') : NaN;
                if (!f.dob || Number.isNaN(ts) || ts > Date.now() || !f.breed || (f.breed === '__other__' && !f.breed_other.trim())) return 'Selectează o dată de naștere validă și rasa (sau scrie rasa).';
            }
            if (n === 4) {
                const w = Number(f.weight);
                const [min, max] = f.species === 'dog' ? [1.25, 60] : [0.5, 70];
                if (!(w >= min && w <= max)) return f.species === 'dog' ? 'Greutatea câinelui trebuie să fie între 1,25 și 60 kg.' : 'Greutatea pisicii trebuie să fie între 0,5 și 70 kg.';
                if (!f.sterilized || !f.chronic || !f.emergency) return 'Completează toate câmpurile medicale marcate cu *.';
                if (f.chronic === 'da' && !f.chronic_text.trim()) return 'Scrie afecțiunea cronică.';
                if (!f.owner_name.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.owner_email.trim()) || !f.owner_phone.trim() || !f.owner_addr.trim()) return 'Completează corect numele, emailul, telefonul și adresa.';
                if (!/^[0-9]{13}$/.test(f.owner_cnp.trim())) return 'CNP-ul trebuie să aibă exact 13 cifre.';
                if (!f.owner_ci_serie.trim() || !f.owner_ci_nr.trim()) return 'Completează seria și numărul cărții de identitate. Microcipul este opțional.';
            }
            return '';
        },
        async next() {
            this.error = this.validate(this.step);
            if (this.error) return this.focusError();
            if (this.step === 4) return this.calculate();
            this.go(this.step + 1);
        },
        back() { this.error = ''; this.go(Math.max(1, this.step - 1)); },
        go(n) {
            this.step = n;
            this.$nextTick(() => {
                const heading = this.$root.querySelector('[data-step="' + n + '"] h3');
                if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus({ preventScroll: true }); }
                this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },
        focusError() { this.$nextTick(() => this.$refs.error && this.$refs.error.focus()); },
        async request(url, body) {
            const response = await fetch(url, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body),
            });
            let json = null;
            try { json = await response.json(); } catch (e) { /* raspuns invalid */ }
            if (!response.ok) {
                if (response.status === 419) throw new Error('Sesiunea formularului a expirat. Reîncarcă pagina și încearcă din nou.');
                if (response.status === 429) throw new Error('Prea multe încercări. Așteaptă un minut și încearcă din nou.');
                const first = json && json.errors ? Object.values(json.errors)[0][0] : null;
                throw new Error(first || (json && json.message) || 'A apărut o eroare. Încearcă din nou.');
            }
            return json;
        },
        async calculate() {
            for (let s = 1; s <= 4; s++) { const e = this.validate(s); if (e) { this.error = e; this.go(s); return; } }
            this.loading = true; this.error = '';
            try {
                this.offer = await this.request(config.offerUrl, { species: this.form.species, weight: Number(this.form.weight) });
                this.go(5);
                if (window.hvTrack) window.hvTrack('subscription_offer', { tier: this.offer.tier_label });
            } catch (e) { this.error = e.message; this.focusError(); } finally { this.loading = false; }
        },
        openPreview() {
            this.error = '';
            if (!this.offer) { this.error = 'Calculează oferta înainte de a continua.'; return this.focusError(); }
            if (!this.allLegal || !this.signed) { this.error = 'Bifează toate confirmările și semnează în chenar.'; return this.focusError(); }
            this.previewOpen = true;
            this.$nextTick(() => this.$refs.confirm && this.$refs.confirm.focus());
        },
        confirmPreview() { this.previewOpen = false; this.locked = true; this.go(6); },
        edit() { this.locked = false; this.error = ''; this.go(4); },
        async send() {
            if (!this.locked || this.loading || this.sent) return;
            this.loading = true; this.error = '';
            try {
                const signature = window.Alpine.$data(this.$refs.pad.firstElementChild).data();
                this.result = await this.request(config.submitUrl, { ...this.form, weight: Number(this.form.weight), signature, submission_token: this.token });
                this.sent = true;
                try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignorat */ }
                if (window.hvTrack) window.hvTrack('subscription_confirmed', { period: this.form.payment_period_months });
            } catch (e) { this.error = e.message; this.focusError(); } finally { this.loading = false; }
        },
        reset() {
            try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignorat */ }
            window.location.reload();
        },
    };
}
