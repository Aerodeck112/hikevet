<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<title>Fișă dosar {{ $r['cod_caz'] }}</title>
<style>
    h2 { font-size: 10pt; color: #0f766e; margin: 0 0 8pt; }
    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 2pt 0 4pt; vertical-align: top; font-size: 8.5pt; }
    table.kv td.k { width: 70pt; font-weight: bold; color: #475569; font-size: 8pt; }
    .top { width: 100%; border-collapse: collapse; }
    .top td { vertical-align: top; }
    .photo { width: 250pt; height: 180pt; border: 1px solid #cbd5e1; background: #f8fafc; text-align: center; vertical-align: middle; }
    .photo img { max-width: 238pt; max-height: 168pt; }
    .photo .miss { color: #991b1b; font-weight: bold; font-size: 10pt; }
    table.grid { width: 100%; border-collapse: separate; border-spacing: 0 6pt; }
    table.grid td { width: 50%; border: 1px solid #e2e8f0; background: #f8fafc; padding: 5pt 8pt; vertical-align: top; }
    table.grid td + td { border-left: 8pt solid #fff; }
    .gl { font-size: 7.5pt; font-weight: bold; color: #475569; }
    .gv { font-size: 8.5pt; margin-top: 2pt; }
    table.det td.k { width: 118pt; }
</style>
</head>
<body>
@include('pdf.campaign._brand', ['title' => 'FISA DOSAR PACIENT', 'subtitle' => 'Dosar '.$r['cod_caz'], 'footer' => 'Campania de Sterilizare HiKeVet · Fișă dosar pacient', 'side' => 28, 'pages' => false])
@php($v = fn ($value) => trim((string) $value) !== '' ? $value : '-')
<table class="top"><tr>
    <td style="padding-right:14pt">
        <h2>DATE PROPRIETAR</h2>
        <table class="kv">
            <tr><td class="k">Nume:</td><td>{{ $v($r['nume_proprietar']) }}</td></tr>
            <tr><td class="k">Telefon:</td><td>{{ $v($r['telefon']) }}</td></tr>
            <tr><td class="k">Email:</td><td>{{ $v($r['email']) }}</td></tr>
            <tr><td class="k">Adresa:</td><td>{{ $v(trim($r['adresa_ci'].' '.$r['localitate'])) }}</td></tr>
            <tr><td class="k">CI:</td><td>{{ $v($r['serie_numar_ci']) }}</td></tr>
        </table>
    </td>
    <td class="photo">@if($photo)<img src="{{ $photo }}" alt="">@else<span class="miss">POZA PACIENT LIPSA</span>@endif</td>
</tr></table>

<h2 style="margin-top:16pt">DATE PACIENT</h2>
@php($weight = ($r['greutate_reala'] !== '' && $r['greutate_reala'] !== null) ? $r['greutate_reala'] : $r['greutate_estimativa'])
<table class="grid">
    @foreach(array_chunk([['Nume', $r['nume_pacient']], ['Specie', $r['specie']], ['Sex', $r['sex']], ['Rasa', $r['rasa']], ['Varsta', $r['varsta_estimata']], ['Greutate', $weight], ['Microcip', $r['microcip']], ['Culoare / semnalmente', $r['culoare_semnalmente']]], 2) as $pair)
        <tr>@foreach($pair as [$label, $value])<td><div class="gl">{{ $label }}</div><div class="gv">{{ $v($value) }}</div></td>@endforeach</tr>
    @endforeach
</table>

<h2 style="margin-top:12pt">INTERVENTIE</h2>
<table class="kv det">
    <tr><td class="k">Data interventiei:</td><td>{{ $v($r['data_interventie']) }}</td></tr>
    <tr><td class="k">Procedura:</td><td>{{ $v($r['procedura_efectuata'] ?: $r['procedura_programata']) }}</td></tr>
    <tr><td class="k">Tip anestezie:</td><td>{{ $v($r['tip_anestezie']) }}</td></tr>
    <tr><td class="k">Substante folosite:</td><td>{{ $v($substances) }}</td></tr>
    <tr><td class="k">Medic operator:</td><td>{{ $v($r['medic_operator']) }}</td></tr>
    <tr><td class="k">Tehnician / asistent:</td><td>{{ $v($r['tehnician_asistent']) }}</td></tr>
    <tr><td class="k">Complicatii / mentiuni:</td><td>{!! nl2br(e($v($r['mentiuni_intra_postoperatorii'] ?: trim($r['complicatii'].' '.$r['mentiuni_medicale'])))) !!}</td></tr>
</table>
</body>
</html>
