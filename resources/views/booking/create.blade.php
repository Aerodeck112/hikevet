@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Cerere de programare', 'lead' => 'Spune-ne cine vine, pentru ce și când îți convine. Te sunăm sau îți scriem ca să confirmăm ora exactă.'])
@php
    $doctorList = $doctors->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'exact' => $d->schedules_count > 0])->values();
    $serviceList = $services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values();
    $periodList = collect($periods)->map(fn ($p, $k) => ['key' => $k, 'label' => $p[0], 'from' => $p[1], 'to' => $p[2]])->values();
@endphp
<section style="padding-bottom: var(--section)">
    <div class="wrap">
        @if ($preview)<p class="notice notice--warn">Previzualizare pentru personal: în Setări, programările sunt trimise momentan la Digitail.</p>@endif
        @if (session('error'))<p class="notice notice--error" role="alert">{{ session('error') }}</p>@endif

        <form class="bk" method="post" action="{{ site_url('/programare') }}" novalidate @submit="sending = true"
              x-data="bookingForm({ services: @js($serviceList), doctors: @js($doctorList), periods: @js($periodList), slotsUrl: @js(site_url('/programare/intervale')),
                                    old: @js(['service' => old('service_id', $preselect), 'doctor' => old('team_member_id'), 'date' => old('date'), 'time' => old('time'), 'period' => old('preferred_period'), 'pet' => old('pet_name'), 'species' => old('pet_species')]) })">
            @csrf
            <div class="hp" aria-hidden="true"><label>Nu completa <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <div class="bk-steps">
                {{-- 1. Pacientul --}}
                <fieldset class="bk-step">
                    <legend><span class="bk-no">1</span> Cine vine la noi?</legend>
                    <div class="grid-2">
                        <div class="field">
                            <label for="b-pet">Numele animalului</label>
                            <input id="b-pet" name="pet_name" x-model="pet" @change="greetPet()" autocomplete="off" required @error('pet_name') aria-invalid="true" @enderror>
                            @error('pet_name')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <span class="label" id="b-species-l">Specie</span>
                            <div class="bk-chips" role="radiogroup" aria-labelledby="b-species-l">
                                @foreach (['Câine' => 'dog', 'Pisică' => 'cat', 'Altă specie' => 'other'] as $sp => $icon)
                                    <label class="bk-chip" :class="{ on: species === @js($sp) }"><input type="radio" name="pet_species" value="{{ $sp }}" x-model="species" @change="speciesChanged()" required><span>{{ $sp }}</span></label>
                                @endforeach
                            </div>
                            @error('pet_species')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </fieldset>

                {{-- 2. Motivul --}}
                <fieldset class="bk-step">
                    <legend><span class="bk-no">2</span> Pentru ce?</legend>
                    <div class="field">
                        <label for="b-service">Serviciu</label>
                        <select id="b-service" name="service_id" x-model="service" @change="serviceChanged()">
                            <option value="">Consultație / nu știu încă</option>
                            @foreach ($services->groupBy(fn ($s) => $s->category?->name ?? 'Alte servicii') as $group => $items)
                                <optgroup label="{{ $group }}">
                                    @foreach ($items as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('service_id')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="b-notes">Ce s-a întâmplat? (opțional)</label>
                        <textarea id="b-notes" name="notes" maxlength="1000" rows="3" placeholder="De exemplu: nu mănâncă de ieri, vaccinul anual, control după operație…">{{ old('notes') }}</textarea>
                    </div>
                    <p class="bk-urgent">Urgență? Nu aștepta confirmarea: sună-ne la <a href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}" data-track="phone_click">{{ $clinic['phone'] }}</a>.</p>
                </fieldset>

                {{-- 3. Cand --}}
                <fieldset class="bk-step">
                    <legend><span class="bk-no">3</span> Când îți convine?</legend>
                    <span class="label" id="b-day-l">Ziua</span>
                    <div class="bk-days" role="radiogroup" aria-labelledby="b-day-l">
                        <template x-for="d in days" :key="d.value">
                            <label class="bk-day" :class="{ on: date === d.value }">
                                <input type="radio" name="date_pick" :value="d.value" x-model="date" @change="dateChanged()">
                                <small x-text="d.top"></small><strong x-text="d.num"></strong><small x-text="d.month"></small>
                            </label>
                        </template>
                        <label class="bk-day bk-day--other" :class="{ on: date && !days.some(d => d.value === date) }">
                            <small>Altă zi</small>
                            <strong x-text="date && !days.some(d => d.value === date) ? dayShort : '📅'"></strong>
                            <input type="date" x-model="date" @change="dateChanged()" @click="$event.target.showPicker?.()" min="{{ now()->toDateString() }}" max="{{ now()->addDays(60)->toDateString() }}" aria-label="Alege altă zi">
                        </label>
                    </div>
                    <input type="hidden" name="date" :value="date">
                    @error('date')<span class="error">{{ $message }}</span>@enderror

                    <span class="label" id="b-period-l" style="margin-top:1.25rem">Intervalul</span>
                    <div class="bk-periods" role="radiogroup" aria-labelledby="b-period-l">
                        <template x-for="p in periods" :key="p.key">
                            <label class="bk-period" :class="{ on: period === p.key, off: periodPassed(p) }">
                                <input type="radio" name="preferred_period" :value="p.key" x-model="period" @change="periodChanged(p)" :disabled="periodPassed(p)">
                                <strong x-text="p.label"></strong><small x-text="p.from + '–' + p.to"></small>
                            </label>
                        </template>
                    </div>
                    @error('preferred_period')<span class="error">{{ $message }}</span>@enderror

                    <div class="field" style="margin-top:1.25rem">
                        <label for="b-doctor">Medic</label>
                        <select id="b-doctor" name="team_member_id" x-model="doctor" @change="loadSlots()">
                            <option value="">Oricare medic disponibil</option>
                            @foreach ($doctors as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                        </select>
                    </div>
                    <div x-show="slots.length" x-cloak aria-live="polite">
                        <span class="label">Sau alege direct o oră liberă la medicul ales</span>
                        <div class="slot-grid">
                            <template x-for="slot in slots" :key="slot">
                                <label class="slot" :class="{ on: time === slot }"><input type="radio" name="time" :value="slot" x-model="time" @change="period = ''"><span x-text="slot"></span></label>
                            </template>
                        </div>
                    </div>
                    @error('time')<span class="error">{{ $message }}</span>@enderror
                </fieldset>

                {{-- 4. Contact --}}
                <fieldset class="bk-step">
                    <legend><span class="bk-no">4</span> Cum te găsim?</legend>
                    <div class="grid-2">
                        <div class="field"><label for="b-name">Nume și prenume</label><input id="b-name" name="client_name" value="{{ old('client_name', auth()->user()?->name) }}" autocomplete="name" required>@error('client_name')<span class="error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="b-phone">Telefon</label><input id="b-phone" type="tel" name="client_phone" value="{{ old('client_phone', auth()->user()?->phone) }}" autocomplete="tel" required>@error('client_phone')<span class="error">{{ $message }}</span>@enderror</div>
                    </div>
                    <div class="field"><label for="b-email">Email</label><input id="b-email" type="email" name="client_email" value="{{ old('client_email', auth()->user()?->email) }}" autocomplete="email" required>@error('client_email')<span class="error">{{ $message }}</span>@enderror</div>
                    <label class="check"><input type="checkbox" name="consent" value="1" required><span>Sunt de acord ca HiKeVet să folosească aceste date pentru programare. Detalii în <a href="{{ site_url('/politica-de-confidentialitate/') }}">politica de confidențialitate</a>.</span></label>
                    @error('consent')<p class="form-error">{{ $message }}</p>@enderror
                </fieldset>
            </div>

            {{-- Rezumatul cererii, cu personajul care urmareste ce completezi --}}
            <aside class="bk-summary" aria-label="Rezumatul cererii">
                <div class="bk-buddy">
                    <div x-show="species !== 'Pisică'"><x-buddy kind="dog" pose="peek" x-ref="dog" :say="['Ham! Hai să facem programarea împreună.', 'Completează pe rând, eu păzesc formularul.']" /></div>
                    <div x-show="species === 'Pisică'" x-cloak><x-buddy kind="cat" x-ref="cat" :greet="false" :say="['Miau! O colegă de-a mea, ce bine.', 'Eu țin locul cald până veniți.']" /></div>
                </div>
                <div class="bk-card">
                    <h2>Cererea ta</h2>
                    <dl>
                        <div><dt>Pacient</dt><dd x-text="(pet || '–') + (species ? ', ' + species.toLowerCase() : '')"></dd></div>
                        <div><dt>Pentru</dt><dd x-text="serviceName"></dd></div>
                        <div><dt>Ziua</dt><dd x-text="dayLabel"></dd></div>
                        <div><dt>Ora</dt><dd x-text="timeLabel"></dd></div>
                        <div><dt>Medic</dt><dd x-text="doctorName"></dd></div>
                    </dl>
                    <button class="btn btn-primary bk-send" type="submit" data-track="booking_complete" :disabled="sending">Trimite cererea</button>
                    <p class="bk-fine">Nu este încă o programare confirmată. Te contactăm în programul clinicii ca să stabilim ora exactă.</p>
                </div>
            </aside>
        </form>
    </div>
</section>
@endsection
