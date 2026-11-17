<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<title>Consimțământ {{ $r['cod_caz'] }}</title>
<style>
    .h { background: #ecfdf5; border: 1px solid #a7f3d0; color: #0f766e; font-weight: bold; font-size: 10pt; padding: 4pt 8pt; margin: 6pt 0 8pt; page-break-after: avoid; }
    p { margin: 0 0 6pt; font-size: 9pt; line-height: 1.33; }
    p.kv { margin: 0 0 2pt; font-size: 8.5pt; }
    .b { font-weight: bold; }
    .note { color: #0f766e; font-weight: bold; }
    table.sig { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 4pt; page-break-inside: avoid; }
    table.sig td { width: 250pt; height: 92pt; border: 1px solid #cbd5e1; background: #f8fafc; vertical-align: top; padding: 6pt 8pt; }
    table.sig td.gap { width: 20pt; border: 0; background: none; }
    .sl { font-size: 8pt; font-weight: bold; color: #475569; }
    .sig img { max-width: 234pt; max-height: 60pt; margin-top: 6pt; }
    .auto { font-size: 7pt; color: #64748b; }
    .refusal { margin-top: 12pt; border: 1px solid #fdba74; background: #fff7ed; padding: 6pt 8pt; page-break-inside: avoid; }
    .refusal .sl { color: #9a3412; }
    .refusal img { max-width: 500pt; max-height: 62pt; margin-top: 6pt; }
</style>
</head>
<body>
@include('pdf.campaign._brand', ['title' => 'CONSIMTAMANT PROCEDURI MEDICALE', 'subtitle' => 'Dosar '.$r['cod_caz'], 'footer' => 'Consimtamant semnat digital | ID '.$consent->id, 'side' => 28])
@php
    $v = fn ($value) => trim((string) $value) !== '' ? $value : '-';
    $ana = ['sedare_usoara_moderata' => 'sedare usoara / moderata', 'anestezie_generala_injectabila' => 'anestezie generala injectabila',
        'anestezie_inhalatorie' => 'anestezie inhalatorie', 'anestezie_locala_regionala' => 'anestezie locala / regionala'];
    $inv = ['consult_preevaluare' => 'consult si evaluare preanestezica', 'analize_hematologice_biochimice' => 'analize hematologice si biochimice',
        'ionograma_gaze_coagulare' => 'ionograma / gaze / coagulare', 'ecografie' => 'ecografie', 'radiografie' => 'radiografie',
        'cardiologie_ecg' => 'cardiologie / ECG', 'internare_monitorizare' => 'internare si monitorizare', 'transfuzie' => 'transfuzie'];
    $labels = function ($selected, $map) {
        $out = [];
        foreach ((array) $selected as $key) { if (isset($map[$key])) { $out[] = $map[$key]; } }
        return $out ? implode(', ', $out) : 'Nicio optiune selectata';
    };
@endphp

<div class="h">DATE PROPRIETAR SI PACIENT</div>
@foreach(['Proprietar' => 'owner_name', 'Telefon' => 'owner_phone', 'Domiciliu' => 'owner_address', 'CI / BI' => 'owner_ci', 'CNP' => 'owner_cnp', 'E-mail' => 'owner_email',
    'Pacient' => 'pet_name', 'Specie / rasa' => 'pet_species_breed', 'Varsta / sex' => 'pet_age_sex', 'Microcip' => 'pet_microchip', 'Greutate' => 'pet_weight', 'Data' => 'consent_date'] as $label => $key)
    <p class="kv @if(in_array($label, ['Pacient', 'Proprietar'], true)) b @endif">{{ $label }}: {{ $v($p[$key] ?? '') }}</p>
@endforeach

@if(!empty($p['visit_group_mode']) && !empty($p['visit_patients']) && is_array($p['visit_patients']))
    <div class="h">PACIENTI INCLUSI IN ACEASTA VIZITA</div>
    @foreach(array_values($p['visit_patients']) as $i => $pt)
        @continue(!is_array($pt))
        <p class="kv b">{{ $i + 1 }}. {{ $pt['pet_name'] ?? '-' }} | {{ trim(($pt['species'] ?? '').' '.($pt['sex'] ?? '')) }}@if(!empty($pt['breed'])) | rasa: {{ $pt['breed'] }}@endif @if(($pt['weight'] ?? '') !== '') | {{ $pt['weight'] }} kg @endif @if(!empty($pt['procedure'])) | {{ $pt['procedure'] }}@endif</p>
    @endforeach
    <p class="note" style="margin-top:4pt">Acest consimtamant este comun pacientilor inregistrati in aceeasi vizita a proprietarului.</p>
@endif

<div class="h">1. DECLARATIA PROPRIETARULUI</div>
<p>Subsemnatul/a, in calitate de proprietar sau reprezentant legal al pacientului mentionat, solicit si accept evaluarea, investigatiile si procedura medicala sau chirurgicala recomandata de medicul veterinar.</p>
<p class="b">Procedura propusa: {{ $v($p['procedure'] ?? '') }}</p>
<p>Medic veterinar curant / chirurg: {{ $v($p['vet_name'] ?? '') }}</p>

<div class="h">2. INFORMARE MEDICALA SI CONSIMTAMANT INFORMAT</div>
<p>Mi-au fost explicate natura si scopul procedurii, beneficiile asteptate, alternativele rezonabile, riscurile efectuarii si riscurile neefectuarii. Inteleg ca medicina veterinara nu poate garanta rezultatul final sau absenta complicatiilor.</p>
<p>Accept manoperele medicale necesare, inclusiv recoltari, cateter, perfuzii, medicamente, pansamente si investigatii. In situatii neprevazute accept manoperele urgente necesare protejarii vietii pacientului, iar clinica va incerca sa ma contacteze cand situatia permite.</p>

<div class="h">3. ANESTEZIE, SEDARE SI RISCURI</div>
<p>Tipuri acceptate: {{ $labels($p['anesthesia'] ?? [], $ana) }}@if(!empty($p['anesthesia_other'])); alta: {{ $p['anesthesia_other'] }}@endif</p>
<p>Inteleg ca sedarea si anestezia pot implica varsaturi, aspiratie, hipotensiune, reactii adverse, tulburari cardiace, stop cardio-respirator sau deces, chiar daca riscul este redus prin evaluare si monitorizare.</p>
<p>Status hrana / apa: {{ $v($p['food_status'] ?? '') }} {{ $p['food_detail'] ?? '' }}</p>

<div class="h">4. INVESTIGATII PREOPERATORII</div>
<p>Investigatii acceptate: {{ $labels($p['investigations'] ?? [], $inv) }}@if(!empty($p['investigations_other'])); altele: {{ $p['investigations_other'] }}@endif</p>
<p>Inteleg ca investigatiile reduc riscul, dar nu garanteaza reusita procedurii.</p>

<div class="h">5. COSTURI SI RESPONSABILITATI POSTOPERATORII</div>
<p>Pret estimativ: {{ $v($p['cost_from'] ?? '') }} - {{ $v($p['cost_to'] ?? '') }} lei. Pot aparea costuri suplimentare daca starea pacientului se modifica sau apar complicatii.</p>
<p>Ma oblig sa respect tratamentul, restrictiile de miscare, controalele, pansamentele, dieta si semnele de alarma comunicate la externare.</p>

<div class="h">6. SERVICII REFUZATE</div>
<p>Servicii refuzate: {{ $labels($p['refused_services'] ?? [], $inv) }}@if(!empty($p['refused_other'])); altele: {{ $p['refused_other'] }}@endif</p>
<p>Daca refuz investigatii sau servicii recomandate, imi asum riscurile medicale care deriva din acest refuz.</p>

<div class="h">7. FOTOGRAFII SI UTILIZAREA IMAGINILOR</div>
@foreach(['Documentare medicala' => 'media_medical', 'Utilizare educationala' => 'media_education', 'Publicare website / social media' => 'media_publication', 'Mentionarea publica a datelor animalului' => 'media_data_public'] as $label => $key)
    <p class="kv b">{{ $label }}: {{ mb_strtoupper($v($p[$key] ?? '')) }}</p>
@endforeach

<div class="h" style="margin-top:8pt">8. PRELUCRAREA DATELOR - GDPR</div>
<p>Operator: FEST4PETS SRL - Clinica Veterinara HiKeVet, CUI RO46349603, Str. Baneasa nr. 23, Targu Mures. Datele sunt prelucrate pentru servicii medical-veterinare, fisa pacientului, comunicare, documente fiscale si obligatii legale.</p>
<p class="b">Proprietarul are drepturile prevazute de legislatia privind protectia datelor. Acord GDPR: {{ $gdpr }}</p>

<div class="h">9. CONFIRMARE FINALA</div>
<p>Confirm ca am citit sau mi-a fost citit documentul, am inteles explicatiile, am putut pune intrebari si accept riscurile medicale inerente. Declar ca sunt lucid/a si informat/a la momentul semnarii.</p>
<p class="b">Data si ora semnarii: {{ $v($signedAt) }}</p>

<div style="page-break-inside: avoid">
<p class="b" style="color:#0f766e;font-size:10pt;margin-top:8pt">SEMNATURI</p>
<table class="sig"><tr>
    <td><div class="sl">Proprietar / reprezentant legal</div>@if($owner)<img src="{{ $owner }}" alt="">@else<div style="margin-top:26pt;color:#475569">Semnatura digitala salvata</div>@endif</td>
    <td class="gap"></td>
    <td><div class="sl">Medic veterinar</div>
        @if($doctor)<img src="{{ $doctor }}" alt="">
        @elseif($doctorName)<div style="margin:12pt 0 0 14pt"><div class="b" style="font-size:11pt">{{ $doctorName }}</div><div style="font-size:8pt;color:#475569;margin-top:4pt">Medic veterinar</div><div class="auto" style="margin-top:3pt">Identificare generata automat</div></div>
        @else<div style="margin-top:26pt;color:#991b1b">Medic responsabil necompletat</div>@endif
    </td>
</tr></table>
@if($refusal)
    <div class="refusal"><div class="sl">Semnatura separata pentru refuz informat</div><img src="{{ $refusal }}" alt=""></div>
@endif
</div>
</body>
</html>
