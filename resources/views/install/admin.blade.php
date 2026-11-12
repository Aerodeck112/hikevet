@extends('install.layout', ['step' => 5])
@section('title', 'Administrator')
@section('content')
<h1>Primul administrator</h1>
<p>Tabelele au fost create. Creează contul de Super Admin; restul personalului se adaugă apoi din panou.</p>
@foreach ($errors as $error)<p class="err" role="alert">{{ $error }}</p>@endforeach
<form method="post" action="{{ request()->root() }}/install/administrator" autocomplete="off">
    <input type="hidden" name="code" value="{{ $code }}">
    <label for="name">Nume și prenume</label><input id="name" type="text" name="name" value="{{ $values['name'] ?? '' }}" required>
    <label for="email">Email</label><input id="email" type="email" name="email" value="{{ $values['email'] ?? '' }}" required>
    <div class="row">
        <div><label for="password">Parolă</label><input id="password" type="password" name="password" required autocomplete="new-password"></div>
        <div><label for="password_confirmation">Repetă parola</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
    </div>
    <p class="hint">Minimum 12 caractere, cu litere mari și mici, cifre și un simbol.</p>
    <button type="submit">Creează contul și încheie instalarea</button>
</form>
@endsection
