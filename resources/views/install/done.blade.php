@extends('install.layout', ['step' => 6])
@section('title', 'Gata')
@section('content')
<h1>Instalarea s-a încheiat</h1>
<p>Instalatorul este acum blocat și adresa <code>/install</code> răspunde cu 404. Pentru a-l reactiva, operatorul trebuie să șteargă manual fișierul <code>storage/app/installed.lock</code>.</p>
<h2>Mai ai de făcut</h2>
<ol>
    <li>Adaugă în cPanel („Cron Jobs”) sarcina care rulează în fiecare minut. Comanda exactă este în <code>INSTALL_CPANEL.md</code>.</li>
    <li>Intră în panou și activează verificarea în doi pași din profil.</li>
    <li>Completează datele SMTP dacă le-ai sărit și trimite un mesaj de probă din formularul de contact.</li>
</ol>
<p><a href="{{ $adminUrl }}" style="display:inline-block;background:#1d2a23;color:#fff;border-radius:999px;padding:.9rem 1.7rem;text-decoration:none;font-weight:600">Intră în panoul de administrare</a></p>
@endsection
