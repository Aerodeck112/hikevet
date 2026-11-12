@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Contact', 'lead' => 'Ne poți găsi la adresa de mai jos, la telefon sau prin formular.'])
<section style="padding-bottom: var(--section)">
    <div class="wrap split" style="align-items:start">
        <div>
            <ul class="ticks" style="margin-top:0">
                <li><x-hv-icon name="pin" /> <span>{{ $clinic['address'] }}, {{ $clinic['city'] }}, România</span></li>
                <li><x-hv-icon name="phone" /> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}" data-track="phone_click">{{ $clinic['phone'] }}</a></li>
                <li><x-hv-icon name="mail" /> <a href="mailto:{{ $clinic['email'] }}">{{ $clinic['email'] }}</a></li>
                @if ($clinic['instagram'])<li><x-hv-icon name="heart" /> <a href="{{ $clinic['instagram'] }}" rel="noopener">clinica_veterinara_hikevet pe Instagram</a></li>@endif
            </ul>
            <p><a class="btn btn-primary" href="{{ $clinic['booking_url'] }}" data-track="booking_click">Programează-te</a></p>
            <div class="split-media" style="margin-top:2rem;aspect-ratio:4/3">
                <iframe src="https://maps.google.com/maps?q={{ urlencode(\App\Support\Settings::get('clinic.maps_query')) }}&t=m&z=15&output=embed" title="Harta: {{ $clinic['address'] }}, {{ $clinic['city'] }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" style="width:100%;height:100%;border:0"></iframe>
            </div>
        </div>
        <div class="card" id="formular">
            <h2 class="h-sm" style="margin-bottom:1.25rem">Scrie-ne</h2>
            @if (session('contact_sent'))
                <p class="notice notice--ok" role="status">Mesajul a fost trimis. Îți răspundem cât mai curând.</p>
            @endif
            <form method="post" action="{{ site_url('/hikevet-contact/') }}" novalidate>
                @csrf
                <input type="hidden" name="ts" value="{{ time() }}">
                <div class="hp" aria-hidden="true"><label>Nu completa acest câmp <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="grid-2">
                    <div class="field">
                        <label for="c-name">Nume</label>
                        <input id="c-name" name="name" value="{{ old('name') }}" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="c-name-e" @enderror>
                        @error('name')<span class="error" id="c-name-e">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="c-phone">Telefon (opțional)</label>
                        <input id="c-phone" name="phone" value="{{ old('phone') }}" type="tel" autocomplete="tel">
                    </div>
                </div>
                <div class="field">
                    <label for="c-email">Email</label>
                    <input id="c-email" name="email" value="{{ old('email') }}" type="email" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="c-email-e" @enderror>
                    @error('email')<span class="error" id="c-email-e">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="c-message">Mesaj</label>
                    <textarea id="c-message" name="message" required @error('message') aria-invalid="true" aria-describedby="c-message-e" @enderror>{{ old('message') }}</textarea>
                    @error('message')<span class="error" id="c-message-e">{{ $message }}</span>@enderror
                </div>
                <label class="check">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                    <span>Sunt de acord ca HiKeVet să folosească aceste date pentru a-mi răspunde. Detalii în <a href="{{ site_url('/politica-de-confidentialitate/') }}">politica de confidențialitate</a>.</span>
                </label>
                @error('consent')<p class="form-error">{{ $message }}</p>@enderror
                <p style="margin-top:1.25rem"><button class="btn btn-dark" type="submit" data-track="contact_submit">Trimite mesajul</button></p>
            </form>
        </div>
    </div>
</section>
@include('partials.subscription-band')
@endsection
