@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Programare online', 'lead' => 'Alege serviciul, medicul și ora. Primești confirmarea pe email.'])
<section style="padding-bottom: var(--section)">
    <div class="wrap" style="max-width:880px;margin-left:max(1.25rem, calc((100% - 1240px) / 2))">
        @if ($preview)<p class="notice notice--warn">Previzualizare pentru personal: programările publice se fac momentan în Digitail. Pagina devine publică după comutarea din Setări.</p>@endif
        @if (session('error'))<p class="notice notice--error" role="alert">{{ session('error') }}</p>@endif
        @if ($doctors->isEmpty() || $services->isEmpty())
            <p class="notice">Programarea online nu este încă disponibilă. Sună-ne la <a href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}">{{ $clinic['phone'] }}</a>.</p>
        @else
        <form class="card" method="post" action="{{ site_url('/programare') }}" novalidate
              x-data="{ doctor: '{{ old('team_member_id') }}', service: '{{ old('service_id') }}', date: '{{ old('date') }}', time: '{{ old('time') }}', slots: [], loading: false, asked: false,
                async load() { this.time = ''; if (!this.doctor || !this.date) return; this.loading = true;
                    try { const r = await fetch('{{ site_url('/programare/intervale') }}?doctor=' + this.doctor + '&date=' + this.date + '&service=' + this.service, { headers: { Accept: 'application/json' } });
                          this.slots = r.ok ? (await r.json()).slots : []; } finally { this.loading = false; this.asked = true; } } }">
            @csrf
            <div class="hp" aria-hidden="true"><label>Nu completa <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="grid-2">
                <div class="field">
                    <label for="b-service">Serviciu</label>
                    <select id="b-service" name="service_id" x-model="service" @change="load()" required>
                        <option value="">Alege serviciul</option>
                        @foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->duration_minutes }} min)</option>@endforeach
                    </select>
                    @error('service_id')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="b-doctor">Medic</label>
                    <select id="b-doctor" name="team_member_id" x-model="doctor" @change="load()" required>
                        <option value="">Alege medicul</option>
                        @foreach ($doctors as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                    @error('team_member_id')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="b-date">Data</label>
                    <input id="b-date" type="date" name="date" x-model="date" @change="load()" min="{{ now()->toDateString() }}" max="{{ now()->addDays(60)->toDateString() }}" required>
                    @error('date')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>
            <fieldset class="cd-field" aria-live="polite">
                <legend class="label">Ora</legend>
                <p x-show="loading">Se încarcă orele libere…</p>
                <p class="notice" x-show="asked && !loading && slots.length === 0" x-cloak>Nu sunt ore libere în ziua aleasă. Alege altă zi sau sună-ne.</p>
                <div class="slot-grid">
                    <template x-for="slot in slots" :key="slot">
                        <label class="slot" :class="{ on: time === slot }"><input type="radio" name="time" :value="slot" x-model="time" required><span x-text="slot"></span></label>
                    </template>
                </div>
                @error('time')<span class="error">{{ $message }}</span>@enderror
            </fieldset>
            <hr class="cfg-hr">
            <div class="grid-2">
                <div class="field"><label for="b-name">Nume și prenume</label><input id="b-name" name="client_name" value="{{ old('client_name', auth()->user()?->name) }}" autocomplete="name" required>@error('client_name')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="b-phone">Telefon</label><input id="b-phone" type="tel" name="client_phone" value="{{ old('client_phone', auth()->user()?->phone) }}" autocomplete="tel" required>@error('client_phone')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="b-email">Email</label><input id="b-email" type="email" name="client_email" value="{{ old('client_email', auth()->user()?->email) }}" autocomplete="email" required>@error('client_email')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="b-pet">Numele animalului</label><input id="b-pet" name="pet_name" value="{{ old('pet_name') }}" required>@error('pet_name')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="b-species">Specie</label><select id="b-species" name="pet_species" required>@foreach (['Câine', 'Pisică', 'Altă specie'] as $sp)<option @selected(old('pet_species') === $sp)>{{ $sp }}</option>@endforeach</select></div>
            </div>
            <div class="field"><label for="b-notes">Ce s-a întâmplat? (opțional)</label><textarea id="b-notes" name="notes" maxlength="1000">{{ old('notes') }}</textarea></div>
            <label class="check"><input type="checkbox" name="consent" value="1" required><span>Sunt de acord ca HiKeVet să folosească aceste date pentru programare. Detalii în <a href="{{ site_url('/politica-de-confidentialitate/') }}">politica de confidențialitate</a>.</span></label>
            @error('consent')<p class="form-error">{{ $message }}</p>@enderror
            <p style="margin-top:1.25rem"><button class="btn btn-primary" type="submit" data-track="booking_complete">Trimite cererea de programare</button></p>
        </form>
        @endif
    </div>
</section>
@endsection
