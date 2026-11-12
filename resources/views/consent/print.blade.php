<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Consimțământ #{{ $consent->id }} – {{ $consent->owner_name }}</title>
    @vite(['resources/css/site.css'])
    <style>body{background:#fff;padding:1.5rem} .sheet{max-width:860px;margin:0 auto} .sigs{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:1.5rem} .sig{border:1px solid #cbd5e1;border-radius:10px;padding:.75rem} .sig img{max-height:110px;width:auto} @media print{body{padding:0}.sigs{break-inside:avoid}}</style>
</head>
<body>
<div class="sheet">
    <p class="no-print" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
        <button class="btn btn-dark btn-sm" type="button" onclick="window.print()">Tipărește / salvează PDF</button>
        <span class="badge {{ $consent->isSigned() ? 'badge--ok' : 'badge--warn' }}">{{ $consent->status->label() }}</span>
        @if ($intact === true)<span class="badge badge--ok">Amprenta documentului corespunde celei de la semnare</span>@endif
        @if ($intact === false)<span class="badge" style="background:#fde4e1;color:#7a1a14">Atenție: documentul nu mai corespunde amprentei de la semnare</span>@endif
    </p>
    @include('consent.document', ['p' => $p, 'mode' => 'print'])
    <div class="sigs">
        <div class="sig">
            <strong>Semnatura proprietarului / reprezentantului legal</strong><br>
            @if ($consent->owner_signature)<img src="{{ $consent->owner_signature }}" alt="Semnatura proprietarului">@else <em>Nesemnat</em> @endif
            <br><small>{{ $consent->signed_at ? 'Semnat la '.$consent->signed_at->format('d.m.Y H:i:s') : '' }}</small>
        </div>
        <div class="sig">
            <strong>Medic veterinar</strong><br>
            @if ($consent->doctor_signature)
                <img src="{{ $consent->doctor_signature }}" alt="Semnatura medicului">
                <br><small>Semnat la {{ $consent->doctor_signed_at?->format('d.m.Y H:i') }}</small>
            @else
                <p style="margin:.5rem 0 0">{{ $p['vet_name'] ?: '–' }}<br>Medic veterinar</p>
                <small>Identificare generată automat. Nu este o semnătură olografă.</small>
            @endif
        </div>
        @if ($consent->refusal_signature)
            <div class="sig"><strong>Semnatura pentru refuz informat</strong><br><img src="{{ $consent->refusal_signature }}" alt="Semnatura pentru refuz informat"></div>
        @endif
    </div>
    <p style="font-size:.8rem;color:#475569;margin-top:1rem">
        Semnătură electronică simplă, desenată pe tabletă; nu este o semnătură electronică calificată (eIDAS).
        @if ($consent->document_sha256) Amprentă SHA-256 la semnare: {{ $consent->document_sha256 }}. @endif
        Șablon: {{ $consent->template_version }}.
    </p>
</div>
</body>
</html>
