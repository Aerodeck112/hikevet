@extends('install.layout', ['step' => 2])
@section('title', 'Verificări')
@section('content')
<h1>Verificarea serverului</h1>
<table>
@foreach ($checks as $check)
    <tr><td>{{ $check['label'] }}</td>
        <td class="{{ $check['ok'] ? 'ok' : ($check['blocking'] ? 'bad' : '') }}">{{ $check['ok'] ? 'în regulă' : ($check['blocking'] ? 'trebuie rezolvat' : 'atenție') }}</td>
        <td>@if (! $check['ok'] || $check['detail'] !== 'ok'){{ $check['detail'] }}@endif</td></tr>
@endforeach
</table>
@if ($blocked)
    <p class="err" role="alert" style="margin-top:1.25rem">Rezolvă punctele marcate „trebuie rezolvat”, apoi reîncarcă pagina. Instalarea nu poate continua așa.</p>
@endif
<form method="post" action="{{ request()->root() }}/install/configurare">
    <input type="hidden" name="code" value="{{ $code }}">
    <button type="submit" @disabled($blocked)>Continuă</button>
</form>
@endsection
