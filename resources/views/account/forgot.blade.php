@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Parolă uitată', 'lead' => 'Scrie emailul contului și îți trimitem un link pentru a seta o parolă nouă.'])
<section style="padding-bottom: var(--section)">
    <div class="wrap" style="max-width:560px;margin-left:max(1.25rem, calc((100% - 1240px) / 2))">
        @if (session('status'))<p class="notice notice--ok" role="status">{{ session('status') }}</p>@endif
        <form class="card" method="post" action="{{ site_url('/acces-cont/parola-uitata') }}" novalidate>
            @csrf
            <div class="field">
                <label for="f-email">Email</label>
                <input id="f-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </div>
            <button class="btn btn-dark" type="submit">Trimite linkul</button>
        </form>
    </div>
</section>
@endsection
