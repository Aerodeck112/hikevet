@extends('install.layout', ['step' => 3])
@section('title', 'Bază de date și email')
@section('content')
<h1>Bază de date și email</h1>
<p>Creează întâi baza de date și utilizatorul din cPanel („MySQL Databases”), cu toate drepturile pe acea bază.</p>
@foreach ($errors as $error)<p class="err" role="alert">{{ $error }}</p>@endforeach
<form method="post" action="{{ request()->root() }}/install/configurare" autocomplete="off">
    <input type="hidden" name="code" value="{{ $code }}">
    <label for="app_url">Adresa site-ului</label>
    <input id="app_url" type="url" name="app_url" value="{{ $values['app_url'] ?? '' }}" required>
    <h2>Bază de date MySQL / MariaDB</h2>
    <div class="row">
        <div><label for="db_host">Server</label><input id="db_host" type="text" name="db_host" value="{{ $values['db_host'] ?? 'localhost' }}" required></div>
        <div><label for="db_port">Port</label><input id="db_port" type="number" name="db_port" value="{{ $values['db_port'] ?? 3306 }}" required></div>
        <div><label for="db_database">Numele bazei de date</label><input id="db_database" type="text" name="db_database" value="{{ $values['db_database'] ?? '' }}" required></div>
        <div><label for="db_username">Utilizator</label><input id="db_username" type="text" name="db_username" value="{{ $values['db_username'] ?? '' }}" required></div>
    </div>
    <label for="db_password">Parolă</label>
    <input id="db_password" type="password" name="db_password" autocomplete="new-password">
    <p class="hint"><label style="display:inline;font-weight:400"><input type="checkbox" name="allow_non_empty" value="1"> Baza de date conține deja tabele și vreau să instalez peste ea</label></p>
    <h2>Email (SMTP)</h2>
    <p class="hint">Poți lăsa gol acum; emailurile se vor scrie doar în jurnal până când completezi datele în <code>.env</code>.</p>
    <div class="row">
        <div><label for="mail_host">Server SMTP</label><input id="mail_host" type="text" name="mail_host" value="{{ $values['mail_host'] ?? '' }}"></div>
        <div><label for="mail_port">Port</label><input id="mail_port" type="number" name="mail_port" value="{{ $values['mail_port'] ?? 465 }}"></div>
        <div><label for="mail_username">Utilizator</label><input id="mail_username" type="text" name="mail_username" value="{{ $values['mail_username'] ?? '' }}"></div>
        <div><label for="mail_password">Parolă</label><input id="mail_password" type="password" name="mail_password" autocomplete="new-password"></div>
    </div>
    <label for="mail_from">Adresa expeditorului</label>
    <input id="mail_from" type="email" name="mail_from" value="{{ $values['mail_from'] ?? 'contact@hikevet.com' }}">
    <button type="submit">Verifică și salvează</button>
</form>
@endsection
