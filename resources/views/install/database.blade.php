@extends('install.layout', ['step' => 4])
@section('title', 'Tabele')
@section('content')
<h1>Crearea tabelelor</h1>
@if ($error)<p class="err" role="alert">{{ $error }}</p><p>Verifică drepturile utilizatorului bazei de date, apoi încearcă din nou.</p>
@else<p>Conexiunea la baza de date funcționează și setările au fost salvate în <code>.env</code>. Următorul pas creează tabelele și datele de bază: roluri, lista de prețuri, conținutul site-ului.</p>@endif
<form method="post" action="{{ request()->root() }}/install/tabele">
    <input type="hidden" name="code" value="{{ $code }}">
    <button type="submit">Creează tabelele</button>
</form>
@endsection
