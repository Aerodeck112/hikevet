@extends('install.layout', ['step' => 1])
@section('title', 'Cod de acces')
@section('content')
<h1>Confirmă că ești operatorul</h1>
<p>Instalatorul a scris un cod în fișierul <code>storage/app/install-code.txt</code>. Deschide-l din cPanel (File Manager) și copiază codul aici. Astfel doar cine are acces la fișierele contului poate instala platforma.</p>
@if ($error)<p class="err" role="alert">{{ $error }}</p>@endif
<form method="post" action="{{ request()->root() }}/install/verificari">
    <label for="code">Cod de acces</label>
    <input id="code" type="text" name="code" required autocomplete="off" autocapitalize="characters" placeholder="XXXX-XXXX-XXXX">
    <button type="submit">Continuă</button>
</form>
@endsection
