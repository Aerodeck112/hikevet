@php
    $initialOwner = $ownerCase ? ['name' => $ownerCase->nume_proprietar, 'phone' => (string) $ownerCase->telefon, 'email' => (string) $ownerCase->email, 'case' => $ownerCase->id] : null;
@endphp
<section class="hv-card" x-data="{
    owners: @js($owners),
    query: '', results: [], owner: @js($initialOwner),
    name: @js($initialOwner['name'] ?? ''), phone: @js($initialOwner['phone'] ?? ''), email: @js($initialOwner['email'] ?? ''), ownerCase: @js($initialOwner['case'] ?? ''),
    date: @js($newDate), slots: @js($newAvailability['slots'] ?? []), summary: @js(['count' => $newAvailability['count'] ?? 0, 'free' => $newAvailability['free_count'] ?? 0, 'next' => $newAvailability['next_free'] ?? '']),
    patients: [{ name: '', type: '', start: @js($newTime), end: '', vaccinat: '' }],
    norm(v) { return String(v || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); },
    search() {
        const q = this.norm(this.query).trim();
        if (q.length < 2) { this.results = []; return; }
        const digits = q.replace(/\D+/g, '');
        this.results = this.owners.filter(o => this.norm(o.name + ' ' + o.email + ' ' + o.codes_text).includes(q) || (digits.length >= 2 && String(o.phone).replace(/\D+/g, '').includes(digits))).slice(0, 15);
    },
    pick(o) { this.owner = o; this.name = o.name; this.phone = o.phone; this.email = o.email; this.ownerCase = o.case || ''; this.query = o.name + (o.phone ? ' · ' + o.phone : ''); this.results = []; },
    clearOwner() { this.owner = null; this.ownerCase = ''; this.query = ''; this.name = ''; this.phone = ''; this.email = ''; },
    plus(t, m) { const [h, mm] = (t || '09:00').split(':').map(Number); const v = Math.min(h * 60 + mm + m, 23 * 60 + 59); return String(Math.floor(v / 60)).padStart(2, '0') + ':' + String(v % 60).padStart(2, '0'); },
    addPatient() { const last = this.patients[this.patients.length - 1]; const start = this.plus(last.start, 60); this.patients.push({ name: '', type: '', start: start, end: '', vaccinat: '' }); },
    loadDay() {
        fetch(@js(route('admin.campaign.appointments.availability')) + '?date=' + encodeURIComponent(this.date), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(r => r.json()).then(d => { this.slots = d.slots || []; this.summary = { count: d.count, free: d.free_count, next: d.next_free }; });
    }
}">
    <h3>Programare nouă</h3>
    <p class="hv-cp-note">Alege ziua și ora din disponibilitate, apoi proprietarul și pacienții. Pentru fiecare pacient se creează automat un dosar și o programare, iar proprietarul primește emailul de confirmare cu recomandările preoperatorii.</p>

    <form method="post" action="{{ route('admin.campaign.appointments.store') }}" class="hv-cp">
        @csrf
        <input type="hidden" name="appointment[owner_case_id]" :value="ownerCase">
        <div class="hv-cp-grid">
            <div class="hv-cp-field"><label>Data programării *</label><input type="date" name="appointment[appointment_date]" x-model="date" @change="loadDay()" required></div>
            <div class="hv-cp-field is-full" style="position:relative">
                <label>Caută proprietarul existent</label>
                <div style="display:flex;gap:.4rem"><input type="search" x-model="query" @input="search()" @keydown.enter.prevent="results[0] && pick(results[0])" @keydown.escape="results = []" placeholder="Scrie numele, telefonul, emailul sau codul dosarului (minimum 2 caractere)" autocomplete="off">
                    <button type="button" class="hv-cp-btn" @click="clearOwner()">×</button></div>
                <div x-show="results.length" x-cloak style="position:absolute;z-index:20;top:100%;left:0;right:0;background:#fff;border:1px solid #d4d4d8;border-radius:.5rem;max-height:18rem;overflow:auto;box-shadow:0 10px 30px rgba(0,0,0,.08)">
                    <template x-for="o in results" :key="o.phone + o.email + o.name"><button type="button" @click="pick(o)" style="display:block;width:100%;text-align:left;padding:.5rem .7rem;border-bottom:1px solid #f4f4f5">
                        <strong x-text="o.name"></strong> <span class="hv-muted" x-text="[o.phone, o.email].filter(Boolean).join(' · ')"></span><div class="hv-muted" style="font-size:.75rem" x-text="o.source + (o.codes_text ? ' · ' + o.codes_text : '')"></div></button></template>
                </div>
                <p class="hv-muted" style="font-size:.78rem" x-text="owner ? 'Proprietar selectat: ' + owner.name + (ownerCase ? ' – adresa, CI-ul și localitatea se preiau din dosarul lui.' : '') : 'Pentru un proprietar nou, completează direct câmpurile de mai jos.'"></p>
            </div>
            <div class="hv-cp-field"><label>Nume și prenume proprietar *</label><input type="text" name="appointment[owner_name]" x-model="name" required></div>
            <div class="hv-cp-field"><label>Telefon proprietar *</label><input type="tel" name="appointment[client_phone]" x-model="phone" required></div>
            <div class="hv-cp-field"><label>Email proprietar *</label><input type="email" name="appointment[client_email]" x-model="email" required></div>
        </div>

        <div>
            <span class="hv-cp-label">Disponibilitatea zilei: <span x-text="summary.count"></span> programări · <span x-text="summary.free"></span> intervale libere · prima oră liberă <strong x-text="summary.next || '—'"></strong></span>
            <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.4rem">
                <template x-for="s in slots" :key="s.time"><button type="button" class="hv-cp-btn" @click="patients[0].start = s.time; patients[0].end = s.end"
                    :style="'padding:.3rem .5rem;font-size:.75rem;' + (s.load === 0 ? 'background:#ecfdf5;border-color:#a7f3d0' : (s.load === 1 ? 'background:#fefce8;border-color:#fde68a' : 'background:#fef2f2;border-color:#fecaca'))"
                    :title="s.appointments.map(a => a.start + ' ' + a.patient).join('\n')" x-text="s.time + (s.load ? ' · ' + s.load : '')"></button></template>
            </div>
            <p class="hv-muted" style="font-size:.75rem">Ferestre de 60 de minute: verde = liber, galben = o programare suprapusă, roșu = două sau mai multe. Click pe oră o completează la primul pacient.</p>
        </div>

        <div class="hv-cp-actions" style="justify-content:space-between"><h3 style="margin:0">Pacienți programați</h3><button type="button" class="hv-cp-btn hv-cp-btn--primary" @click="addPatient()">+ Adaugă pacient</button></div>
        <template x-for="(p, i) in patients" :key="i">
            <div class="hv-cp-grid" style="border:1px solid #e4e4e7;border-radius:.6rem;padding:.75rem">
                <div class="hv-cp-field"><label>Nume pacient *</label><input type="text" :name="'patients[' + i + '][patient_name]'" x-model="p.name" required></div>
                <div class="hv-cp-field"><label>Tip programare *</label><select :name="'patients[' + i + '][appointment_type]'" x-model="p.type" required><option value="">Selectează</option>@foreach ($types as $t)<option>{{ $t }}</option>@endforeach</select></div>
                <div class="hv-cp-field"><label>Ora început *</label><input type="time" :name="'patients[' + i + '][start_time]'" x-model="p.start" required></div>
                <div class="hv-cp-field"><label>Ora sfârșit</label><input type="time" :name="'patients[' + i + '][end_time]'" x-model="p.end"></div>
                <div class="hv-cp-field"><span class="hv-cp-label">Vaccinat</span><div class="hv-cp-radio"><label><input type="radio" :name="'patients[' + i + '][vaccinat]'" value="Da" x-model="p.vaccinat"> Da</label><label><input type="radio" :name="'patients[' + i + '][vaccinat]'" value="Nu" x-model="p.vaccinat"> Nu</label></div></div>
                <div class="hv-cp-field" x-show="patients.length > 1"><span class="hv-cp-label">&nbsp;</span><button type="button" class="hv-cp-btn hv-cp-btn--danger" @click="patients.splice(i, 1)">Elimină pacientul</button></div>
            </div>
        </template>
        <div class="hv-cp-actions"><button class="hv-cp-btn hv-cp-btn--primary" type="submit">Salvează toate programările și creează dosarele</button><a class="hv-cp-btn" href="?week={{ $start->toDateString() }}">Renunță</a></div>
    </form>
</section>
