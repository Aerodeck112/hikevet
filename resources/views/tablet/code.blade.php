@extends('layouts.bare')

@section('content')
<section class="section--tight">
    <div class="wrap" style="max-width:560px">
        @if (session('signed'))
            <div class="notice notice--ok" role="status"><strong>Documentul a fost semnat și salvat.</strong><br>Mulțumim. Poți înapoia tableta la recepție.</div>
        @endif
        <div class="card">
            <h1 class="display h-md">Consimțământ informat</h1>
            <p style="margin-top:1rem">Introdu codul de 6 cifre primit de la recepție pentru a deschide documentul.</p>
            <form method="post" action="{{ site_url('/consimtamant-tableta/cod') }}" novalidate>
                @csrf
                <div class="field">
                    <label for="t-code">Cod de 6 cifre</label>
                    <input id="t-code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required autofocus class="code-input" @error('code') aria-invalid="true" aria-describedby="t-code-e" @enderror>
                    @error('code')<span class="error" id="t-code-e">{{ $message }}</span>@enderror
                </div>
                <button class="btn btn-dark" type="submit" style="width:100%">Deschide documentul</button>
            </form>
        </div>
    </div>
</section>
@endsection
