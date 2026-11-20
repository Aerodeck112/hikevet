<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<title>Centralizator HiKeVet - {{ $monthLabel }}</title>
<style>
    @page { margin: 40pt 52pt 40pt 52pt; }
    body { font-family: "DejaVu Sans", sans-serif; color: #0f172a; font-size: 10pt; }
    .clinic { font-size: 13pt; font-weight: bold; color: #0f766e; }
    .sub { font-size: 9pt; color: #475569; margin-top: 3pt; }
    h1 { font-size: 15pt; margin: 44pt 0 6pt; }
    .month { font-size: 11pt; font-weight: bold; color: #475569; }
    table.c { width: 100%; border-collapse: collapse; margin-top: 46pt; }
    table.c th { background: #f1f5f9; border: 1px solid #64748b; font-size: 9pt; text-align: left; padding: 9pt 6pt; }
    table.c td { border: 1px solid #94a3b8; padding: 9pt 8pt; font-size: 10pt; }
    table.c tr.total td { background: #f8fafc; border-color: #64748b; font-weight: bold; }
    .n { font-weight: bold; }
    table.sig { width: 100%; margin-top: 70pt; }
    table.sig td { vertical-align: top; font-size: 10pt; font-weight: bold; }
    table.sig .l2 { font-size: 9pt; color: #475569; margin-top: 6pt; }
    table.sig .line { border-bottom: 1px solid #64748b; height: 34pt; }
    .gen { position: fixed; bottom: -16pt; left: 0; font-size: 7pt; color: #64748b; }
</style>
</head>
<body>
<div class="clinic">CLINICA VETERINARA HIKEVET</div>
<div class="sub">Targu Mures | {{ parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'hikevet.com' }}</div>
<h1>CENTRALIZATOR INTERVENTII DE STERILIZARE</h1>
<div class="month">HIKEVET - {{ mb_strtoupper(\Illuminate\Support\Str::ascii($monthLabel)) }}</div>

<table class="c">
    <colgroup><col style="width:12%"><col style="width:39%"><col style="width:17%"><col style="width:19%"><col style="width:13%"></colgroup>
    <thead><tr><th>NR CRT</th><th>CATEGORIE ANIMAL</th><th>PRET UNITAR</th><th>NUMAR ANIMALE</th><th>TOTAL</th></tr></thead>
    <tbody>
    @foreach([['CAINE','Caine'],['CATEA','Catea'],['MOTAN','Motan'],['PISICA','Pisica']] as $i => [$key, $label])
        <tr><td>{{ $i + 1 }}.</td><td>{{ $label }}</td><td></td><td class="n">{{ $summary[$key] }}</td><td></td></tr>
    @endforeach
    <tr class="total"><td>TOTAL</td><td></td><td></td><td>{{ $summary['total'] }}</td><td></td></tr>
    </tbody>
</table>

<table class="sig"><tr>
    <td style="width:40%">DATA<div class="line" style="width:150pt"></div></td>
    <td style="width:20%"></td>
    <td style="width:40%">MEDIC VETERINAR<div class="l2">NUME - PRENUME</div><div class="l2">SEMNATURA SI STAMPILA</div><div class="line"></div></td>
</tr></table>
<div class="gen">Centralizator generat automat din evidenta HiKeVet la {{ $generatedAt }}</div>
</body>
</html>
