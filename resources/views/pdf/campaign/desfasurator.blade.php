<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<title>Desfășurător HiKeVet - {{ $monthLabel }}</title>
<style>
    .kpis { width: 100%; border-collapse: separate; border-spacing: 6pt 0; margin: 0 -6pt 10pt; }
    .kpis td { border: 1px solid #cbd5e1; background: #f8fafc; padding: 6pt 8pt; width: {{ $compact ? '20%' : '25%' }}; }
    .kpis .l { font-size: 7.5pt; font-weight: bold; color: #475569; }
    .kpis .v { font-size: 14pt; font-weight: bold; color: #0f766e; }
    table.rows { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.rows thead { display: table-header-group; }
    table.rows tr { page-break-inside: avoid; }
    table.rows th { background: #0f766e; color: #fff; border: 1px solid #0b5f59; font-size: 7pt; text-align: left; padding: 4pt 3pt; }
    table.rows td { border: 1px solid #d6dee3; font-size: {{ $compact ? '7pt' : '6.8pt' }}; line-height: 1.3; vertical-align: top; padding: 3pt; word-wrap: break-word; }
    table.rows tbody tr:nth-child(even) td { background: #f8fafc; }
    .b { font-weight: bold; }
    .empty { color: #64748b; font-size: 10pt; padding: 10pt 0; }
    .sign { width: 100%; margin-top: 22pt; }
    .sign td { width: 50%; text-align: center; font-size: 8pt; padding: 22pt 30pt 0; }
    .sign td div { border-top: 1px solid #475569; padding-top: 4pt; }
</style>
</head>
<body>
@include('pdf.campaign._brand', ['title' => 'DESFASURATOR INTERVENTII STERILIZARE', 'subtitle' => 'HiKeVet | '.$monthLabel, 'footer' => 'Generat automat de HiKeVet la '.$generatedAt])

<table class="kpis"><tr>
    <td><div class="l">Total</div><div class="v">{{ $summary['total'] }}</div></td>
    <td><div class="l">Caini</div><div class="v">{{ $summary['dogs'] }}</div></td>
    <td><div class="l">Pisici</div><div class="v">{{ $summary['cats'] }}</div></td>
    @if($compact)
        <td><div class="l">Femele</div><div class="v">{{ $summary['female'] }}</div></td>
        <td><div class="l">Masculi</div><div class="v">{{ $summary['male'] }}</div></td>
    @else
        <td><div class="l">Femele / Masculi</div><div class="v">{{ $summary['female'] }} / {{ $summary['male'] }}</div></td>
    @endif
</tr></table>

@if($compact)
<table class="rows">
    <colgroup><col style="width:4%"><col style="width:8%"><col style="width:10%"><col style="width:16%"><col style="width:12%"><col style="width:9%"><col style="width:23%"><col style="width:18%"></colgroup>
    <thead><tr><th>Nr.</th><th>Data</th><th>Cod</th><th>Proprietar / telefon</th><th>Pacient / rasa</th><th>Specie / sex</th><th>Procedura</th><th>Medic</th></tr></thead>
    <tbody>
    @forelse($rows as $r)
        <tr>
            <td>{{ $r['NrCrt'] }}</td><td>{{ $r['DataInterventiei'] }}</td><td class="b">{{ $r['CodCaz'] }}</td>
            <td>{{ $r['NumeProprietar'] }}<br>{{ $r['Telefon'] }}</td><td>{{ $r['NumePacient'] }}<br>{{ $r['Rasa'] }}</td>
            <td>{{ $r['Specie'] }}<br>{{ $r['Sex'] }}</td><td>{{ $r['Procedura'] }}</td><td>{{ $r['MedicOperator'] }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="empty">Nu exista cazuri pentru filtrul selectat.</td></tr>
    @endforelse
    </tbody>
</table>
@else
<table class="rows">
    <colgroup><col style="width:3%"><col style="width:8%"><col style="width:6%"><col style="width:14%"><col style="width:11%"><col style="width:7%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:11%"><col style="width:7%"><col style="width:9%"></colgroup>
    <thead><tr><th>Nr.</th><th>Data / Cod</th><th>Nr. pacient</th><th>Proprietar / Telefon</th><th>Pacient</th><th>Specie / Sex</th><th>Rasă</th><th>Vârstă / Greutate</th><th>Microcip</th><th>Procedură</th><th>Anestezie</th><th>Medic operator</th></tr></thead>
    <tbody>
    @forelse($rows as $r)
        <tr>
            <td>{{ $r['NrCrt'] }}</td>
            <td><span class="b">{{ $r['DataInterventiei'] }}</span><br>{{ $r['CodCaz'] }}</td>
            <td>{{ $r['NumarPacient'] ?: '-' }}</td>
            <td><span class="b">{{ $r['NumeProprietar'] }}</span><br>{{ $r['Telefon'] }}<br>{{ $r['AdresaCI'] }}</td>
            <td><span class="b">{{ $r['NumePacient'] }}</span><br>{{ $r['CuloareSemnalmente'] }}</td>
            <td>{{ $r['Specie'] }}<br>{{ $r['Sex'] }}</td>
            <td>{{ $r['Rasa'] }}</td>
            <td>{{ $r['Varsta'] }}<br>{{ $r['GreutateKg'] }}@if($r['GreutateKg'] !== '') kg @endif</td>
            <td>{{ $r['Microcip'] ?: '-' }}</td>
            <td>{{ $r['Procedura'] }}</td>
            <td>{{ $r['TipAnestezie'] }}</td>
            <td>{{ $r['MedicOperator'] }}</td>
        </tr>
    @empty
        <tr><td colspan="12" class="empty">Nu există cazuri pentru filtrul selectat.</td></tr>
    @endforelse
    </tbody>
</table>
<table class="sign"><tr><td><div>Întocmit de</div></td><td><div>Reprezentant / Semnătură</div></td></tr></table>
@endif
</body>
</html>
