<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<title>Contract {{ $contract->number }}</title>
<style>
    @page { margin: 78px 40px 56px 40px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #111; line-height: 1.45; }
    header { position: fixed; top: -78px; left: -40px; right: -40px; height: 46px; background: #1FA870; color: #fff; padding: 10px 50px 0; }
    header .t { font-size: 15px; font-weight: bold; }
    header .s { font-size: 9px; }
    footer { position: fixed; bottom: -44px; left: 0; right: 0; font-size: 8px; color: #555; border-top: 1px solid #ccd; padding-top: 5px; }
    h1 { font-size: 13px; margin: 0 0 10px; }
    h2 { font-size: 11px; margin: 14px 0 6px; }
    table.kv td { padding: 2px 0; vertical-align: top; }
    table.kv td.k { width: 130px; }
    .notice { background: #f7f9fc; border: 1px solid #dbe2ec; padding: 8px 10px; margin: 12px 0; font-size: 9px; }
    p { margin: 0 0 4px; }
    p.sec { font-size: 11px; font-weight: bold; margin: 10px 0 4px; }
    p.li { padding-left: 14px; }
    table.svc { width: 100%; border-collapse: collapse; font-size: 9px; }
    table.svc th { background: #dbfae5; text-align: left; }
    table.svc th, table.svc td { border: 1px solid #bfccd9; padding: 5px 6px; }
    table.svc tfoot td { background: #1FA870; color: #fff; font-weight: bold; }
    .sig img { width: 220px; height: 55px; }
    .audit { font-size: 8px; color: #444; margin-top: 10px; }
</style>
</head>
<body>
<header><div class="t">{{ $headerTitle }}</div><div class="s">{{ $headerSub }}</div></header>
<footer>Contract nr. {{ $contract->number }} | Șablon {{ $contract->template->version }} | Listă de prețuri {{ $contract->priceList?->version }}</footer>

<h1>CONTRACT ABONAMENT MEDICAL VETERINAR</h1>
<table class="kv">
    <tr><td class="k">Număr contract:</td><td>{{ $contract->number }}</td></tr>
    <tr><td class="k">Data semnarii online:</td><td>{{ $p['contract_date'] }}</td></tr>
    <tr><td class="k">Client:</td><td>{{ $p['owner_name'] }} | {{ $p['owner_email'] }} | {{ $p['owner_phone'] }}</td></tr>
    <tr><td class="k">CNP / CI:</td><td>{{ $p['owner_cnp'] }} | {{ $p['owner_ci_serie'] }} {{ $p['owner_ci_nr'] }}</td></tr>
    <tr><td class="k">Adresa:</td><td>{{ $p['owner_addr'] }}</td></tr>
    <tr><td class="k">Animal:</td><td>{{ $p['pet_name'] }} | {{ $contract->species === 'dog' ? 'Caine' : 'Pisica' }} | {{ $p['breed'] }} | {{ $p['sex'] === 'male' ? 'Mascul' : 'Femela' }}</td></tr>
    <tr><td class="k">Greutate / Microcip:</td><td>{{ $p['weight'] }} kg | {{ $p['pet_microchip'] ?? '' }}</td></tr>
    <tr><td class="k">Categorie:</td><td>{{ $contract->tier_label }}</td></tr>
    <tr><td class="k">Pret final:</td><td>{{ $contract->price_monthly }} lei/luna | 6 luni {{ $contract->price_6m }} | 12 luni {{ $contract->price_12m }}</td></tr>
    <tr><td class="k">Regula multi-animal:</td><td>{{ $contract->discount_label }}</td></tr>
    <tr><td class="k">Pret standard:</td><td>{{ $contract->standard_monthly }} lei/luna | 6 luni {{ $contract->standard_6m }} | 12 luni {{ $contract->standard_12m }}</td></tr>
    <tr><td class="k">Perioada aleasa:</td><td>{{ $contract->period_months === 1 ? 'lunar' : $contract->period_months.' luni' }} | de plata la clinica: {{ number_format((float) $contract->payment_amount, 0, ',', '.') }} lei</td></tr>
</table>

<div class="notice">Beneficiarul are la dispozitie {{ $validDays }} zile de la data semnarii online pentru a se prezenta in clinica in vederea semnarii olografe si efectuarii platii. In caz contrar, contractul si oferta se anuleaza automat.</div>

<h2>Text contract (conform model)</h2>
@foreach ($lines as $i => $line)
    @continue($i === 0)
    @if (preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X|XI|XII)\./', $line))
        <p class="sec">{{ $line }}</p>
    @elseif (str_starts_with($line, '- '))
        <p class="li">{{ $line }}</p>
    @else
        <p>{{ $line }}</p>
    @endif
@endforeach

<h2>ANEXA - Tabel servicii (pret normal vs pret abonament)</h2>
<table class="svc">
    <thead><tr><th>Serviciu</th><th style="width:100px">Pret normal</th><th style="width:110px">Pret abonament</th></tr></thead>
    <tbody>
    @foreach ($p['services'] as $row)
        <tr><td>{{ $row['name'] }}</td><td>{{ $row['normal'] }} lei</td><td>{{ $row['abon'] }} lei</td></tr>
    @endforeach
    </tbody>
    <tfoot><tr><td>Total</td><td>{{ $contract->total_normal }} lei</td><td>{{ $contract->total_subscription }} lei</td></tr></tfoot>
</table>
<p style="margin-top:6px">Deparazitarile interne si externe nu sunt incluse in abonament si se achita separat.</p>

<h2>Semnătura beneficiar</h2>
<div class="sig">
@if ($signature)
    <img src="{{ $signature }}" alt="Semnatura beneficiar">
@else
    <p>(Semnătura nu este disponibila.)</p>
@endif
</div>
<div class="audit">
    Semnătură electronică simplă, desenată pe ecran de beneficiar la {{ $contract->signed_at?->format('d.m.Y H:i:s') }} (ora României).
    Nu este o semnătură electronică calificată în sensul Regulamentului eIDAS.<br>
    Confirmări bifate: {{ collect($contract->legal_consents['accepted'] ?? [])->keys()->implode(', ') }}.
</div>
</body>
</html>
