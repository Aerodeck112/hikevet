@extends('layouts.site')

@section('content')
@include('site._head', ['title' => __('Cont client'), 'lead' => __('Animalele, contractele, punctele și Wallet-ul tău, într-un singur loc.')])
<section style="padding-bottom: var(--section)">
    <div class="wrap">
        @if (session('status'))<p class="notice notice--ok" role="status">{{ session('status') }}</p>@endif
        <div class="split" style="align-items:start">
            <div class="card">
                <h2 class="h-sm" style="margin-bottom:1.25rem">{{ __('Am deja cont') }}</h2>
                <form method="post" action="{{ site_url('/acces-cont/autentificare') }}" novalidate>
                    @csrf
                    <div class="field">
                        <label for="l-email">{{ __('Email') }}</label>
                        <input id="l-email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required @error('email') aria-invalid="true" aria-describedby="l-email-e" @enderror>
                        @error('email')<span class="error" id="l-email-e">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="l-password">{{ __('Parolă') }}</label>
                        <input id="l-password" type="password" name="password" autocomplete="current-password" required>
                        @error('password')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <label class="check"><input type="checkbox" name="remember" value="1"><span>{{ __('Ține-mă minte pe acest dispozitiv') }}</span></label>
                    <p style="margin-top:1.25rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
                        <button class="btn btn-dark" type="submit">{{ __('Intră în cont') }}</button>
                        <a href="{{ site_url('/acces-cont/parola-uitata/') }}">{{ __('Am uitat parola') }}</a>
                    </p>
                </form>
            </div>
            <div class="card">
                <h2 class="h-sm" style="margin-bottom:.5rem">{{ __('Cont nou') }}</h2>
                <p>{{ __('Dacă ai semnat deja un contract de abonament, contul există: folosește „Am uitat parola” cu emailul din contract.') }}</p>
                <form method="post" action="{{ site_url('/acces-cont/inregistrare') }}" novalidate>
                    @csrf
                    <div class="hp" aria-hidden="true"><label>{{ __('Nu completa') }} <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <div class="field">
                        <label for="r-name">{{ __('Nume și prenume') }}</label>
                        <input id="r-name" name="name" value="{{ old('name') }}" autocomplete="name" required>
                        @error('name')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label for="r-email">{{ __('Email') }}</label>
                            <input id="r-email" type="email" name="reg_email" value="{{ old('reg_email') }}" autocomplete="email" required>
                            @error('reg_email')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="r-phone">{{ __('Telefon (opțional)') }}</label>
                            <input id="r-phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                        </div>
                        <div class="field">
                            <label for="r-password">{{ __('Parolă') }}</label>
                            <input id="r-password" type="password" name="reg_password" autocomplete="new-password" required aria-describedby="r-password-h">
                            <span class="hint" id="r-password-h">{{ __('Minimum 10 caractere, cu litere și cifre.') }}</span>
                            @error('reg_password')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="r-password2">{{ __('Repetă parola') }}</label>
                            <input id="r-password2" type="password" name="reg_password_confirmation" autocomplete="new-password" required>
                        </div>
                    </div>
                    <label class="check"><input type="checkbox" name="terms" value="1" required><span>{!! __('Sunt de acord cu <a href=":terms">Termenii și condițiile</a> și cu <a href=":privacy">Politica de confidențialitate</a>.', ['terms' => e(site_url('/termeni-si-conditii-abonamente/')), 'privacy' => e(site_url('/politica-de-confidentialitate/'))]) !!}</span></label>
                    @error('terms')<p class="form-error">{{ $message }}</p>@enderror
                    <p style="margin-top:1.25rem"><button class="btn btn-primary" type="submit">{{ __('Creează contul') }}</button></p>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
