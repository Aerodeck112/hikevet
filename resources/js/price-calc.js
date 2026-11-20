// Calculator orientativ pentru primul animal. Pretul final se calculeaza mereu pe server.
// Animalul desenat creste odata cu greutatea; sumele se schimba animat (static daca miscarea e redusa).
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function priceCalc(tiers) {
    return {
        tiers: tiers.map((t) => ({ ...t, min: Number(t.min), max: Number(t.max) })),
        species: 'dog',
        weight: 12,
        shown: { monthly: 0, m6: 0, m12: 0 },
        hop: 0,
        init() {
            this.animateTo(this.tier, true);
            this.$watch('weight', () => this.animateTo(this.tier));
            this.$watch('species', () => this.animateTo(this.tier));
        },
        get bounds() {
            return this.species === 'dog' ? { min: 1.25, max: 60 } : { min: 0.5, max: 12 };
        },
        get weightLabel() {
            return String(this.weight).replace('.', ',');
        },
        get tier() {
            return this.tiers.find((t) => t.species === this.species && this.weight >= t.min && (t.inclusive ? this.weight <= t.max : this.weight < t.max)) || null;
        },
        get speciesTiers() {
            return this.tiers.filter((t) => t.species === this.species);
        },
        // Marimea desenului: de la un pui mic la un caine mare.
        get petScale() {
            const { min, max } = this.bounds;
            const p = Math.min(1, Math.max(0, (this.weight - min) / (max - min)));
            return (0.55 + Math.sqrt(p) * 0.6).toFixed(3);
        },
        get perMonth6() {
            return this.tier ? Math.round(this.tier.m6 / 6) : 0;
        },
        get perMonth12() {
            return this.tier ? Math.round(this.tier.m12 / 12) : 0;
        },
        get save6() {
            return this.tier ? this.tier.monthly * 6 - this.tier.m6 : 0;
        },
        get save12() {
            return this.tier ? this.tier.monthly * 12 - this.tier.m12 : 0;
        },
        money(value) {
            return Math.round(value).toLocaleString('ro-RO');
        },
        animateTo(tier, instant = false) {
            const target = tier ? { monthly: tier.monthly, m6: tier.m6, m12: tier.m12 } : { monthly: 0, m6: 0, m12: 0 };
            if (instant || reduced()) {
                this.shown = { ...target };
                return;
            }
            const from = { ...this.shown };
            const start = performance.now();
            const step = (now) => {
                const t = Math.min(1, (now - start) / 420);
                const e = 1 - Math.pow(1 - t, 3);
                this.shown = {
                    monthly: from.monthly + (target.monthly - from.monthly) * e,
                    m6: from.m6 + (target.m6 - from.m6) * e,
                    m12: from.m12 + (target.m12 - from.m12) * e,
                };
                if (t < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        },
        setSpecies(species) {
            if (this.species === species) return;
            this.species = species;
            this.weight = species === 'dog' ? 12 : 4;
            this.hop++;
        },
    };
}

// Simulator Wallet: aceleasi reguli ca la clinica (X principal + X bonus servicii + X/2 bonus produse;
// bonusul acopera cel mult 1/2 dintr-un serviciu si 1/3 dintr-un produs).
export function walletCalc() {
    return {
        amount: 300,
        service: 400,
        get principal() { return this.amount; },
        get serviceBonus() { return this.amount; },
        get productBonus() { return Math.round(this.amount / 2); },
        get total() { return this.principal + this.serviceBonus + this.productBonus; },
        get serviceSplit() {
            const bonus = Math.min(this.serviceBonus, this.service / 2);
            return { bonus: Math.round(bonus), principal: Math.round(this.service - bonus) };
        },
        bar(value) {
            return Math.max(4, Math.round((value / (this.amount * 2.5 || 1)) * 100));
        },
        money(value) {
            return Math.round(value).toLocaleString('ro-RO');
        },
    };
}
