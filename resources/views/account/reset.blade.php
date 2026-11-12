@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Setează parola'])
<section style="padding-bottom: var(--section)">
    <div class="wrap" style="max-width:560px;margin-left:max(1.25rem, calc((100% - 1240px) / 2))">
        <form class="card" method="post" action="{{ site_url('/acces-cont/parola-noua') }}" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field">
                <label for="p-email">Email</label>
                <input id="p-email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" required>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="p-password">Parolă nouă</label>
                <input id="p-password" type="password" name="password" autocomplete="new-password" required aria-describedby="p-password-h">
                <span class="hint" id="p-password-h">Minimum 10 caractere, cu litere și cifre.</span>
                @error('password')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="p-password2">Repetă parola</label>
                <input id="p-password2" type="password" name="password_confirmation" autocomplete="new-password" required>
            </div>
            <button class="btn btn-dark" type="submit">Salvează parola</button>
        </form>
    </div>
</section>
@endsection
