// Calculator orientativ pentru primul animal. Pretul final se calculeaza mereu pe server.
export function priceCalc(tiers) {
    return {
        tiers,
        species: 'dog',
        weight: 12,
        get bounds() {
            return this.species === 'dog' ? { min: 1.25, max: 60 } : { min: 0.5, max: 12 };
        },
        get weightLabel() {
            return String(this.weight).replace('.', ',');
        },
        get tier() {
            return this.tiers.find((t) => t.species === this.species && this.weight >= t.min && (t.inclusive ? this.weight <= t.max : this.weight < t.max)) || null;
        },
        setSpecies(species) {
            this.species = species;
            this.weight = species === 'dog' ? 12 : 4;
        },
    };
}
