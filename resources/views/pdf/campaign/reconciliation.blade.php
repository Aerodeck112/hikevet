<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<title>Reconciliere {{ $data['label'] }}</title>
<style>
    .kpis { width: 100%; border-collapse: separate; border-spacing: 8pt 0; margin: 0 -8pt 6pt; }
    .kpis td { border: 1px solid #cbd5e1; background: #f8fafc; padding: 6pt 8pt; width: 25%; }
    .kpis .l { font-size: 7.5pt; font-weight: bold; color: #475569; }
    .kpis .v { font-size: 14pt; font-weight: bold; color: #0f766e; }
    .intro { font-size: 7.5pt; color: #64748b; margin: 0 0 8pt; }
    table.rows { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.rows thead { display: table-header-group; }
    table.rows tr { page-break-inside: avoid; }
    table.rows th { background: #0f766e; color: #fff; border: 1px solid #0b5f59; font-size: 7pt; text-align: left; padding: 4pt 3pt; }
    table.rows td { border: 1px solid #d6dee3; font-size: 7pt; vertical-align: top; padding: 3pt; word-wrap: break-word; }
    table.rows tbody tr:nth-child(even) td { background: #f8fafc; }
    .b { font-weight: bold; }
    .bad { color: #991b1b; }
</style>
</head>
<body>
@include('pdf.campaign._brand', ['title' => 'LISTA HIKEVET PENTRU RECONCILIERE', 'subtitle' => mb_strtoupper(\Illuminate\Support\Str::ascii($data['label'])).' | ordonata dupa numarul confirmat din fotografie', 'footer' => 'Generat din registrul HiKeVet la '.$generatedAt])
<table class="kpis"><tr>
    <td><div class="l">Poze verificate</div><div class="v">{{ count($lines) }}</div></td>
    <td><div class="l">Nr. duplicate</div><div class="v">{{ $duplicates }}</div></td>
    <td><div class="l">De regularizat</div><div class="v">{{ count($data['to_regularize']) }}</div></td>
    <td><div class="l">Conflict scos din uz</div><div class="v">{{ count($data['void_conflicts']) }}</div></td>
</tr></table>
<p class="intro">Document intern pentru comparatie cu PDF-ul deja transmis Primariei. Nu modifica datele si nu muta automat cazurile intre luni.</p>
<table class="rows">
    <colgroup><col style="width:5%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:8%"><col style="width:10%"><col style="width:15%"><col style="width:11%"><col style="width:10%"><col style="width:20%"></colgroup>
    <thead><tr><th>Nr. crt.</th><th>Nr. poza</th><th>Nr. intern</th><th>Nr. raport</th><th>Data</th><th>Pacient</th><th>Proprietar</th><th>Cod dosar</th><th>Status interventie</th><th>Observatie</th></tr></thead>
    <tbody>
    @forelse($lines as $i => $l)
        <tr><td>{{ $i + 1 }}</td><td class="b">{{ $l['photo'] }}</td><td>{{ $l['internal'] }}</td><td>{{ $l['report'] }}</td><td>{{ $l['date'] }}</td><td>{{ $l['patient'] }}</td>
            <td>{{ $l['owner'] }}</td><td class="b">{{ $l['code'] }}</td><td>{{ $l['status'] }}</td><td class="{{ $l['obs'] !== 'OK' ? 'bad' : '' }}">{{ $l['obs'] }}</td></tr>
    @empty
        <tr><td colspan="10">Nu exista fotografii verificate pentru aceasta luna.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
