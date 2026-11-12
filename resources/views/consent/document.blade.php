{{-- Textul documentului este cel din hikevet-consent-tablet 1.4.1 (static_document_html), nemodificat.
     $mode: "tablet" (campuri de completat de proprietar) sau "print" (document final). --}}
@php
    $ana = \App\Services\Consents\ConsentService::ANESTHESIA;
    $inv = \App\Services\Consents\ConsentService::INVESTIGATIONS;
    $group = ! empty($p['visit_group_mode']) && ! empty($p['visit_patients']);
    $box = fn (bool $on, string $label) => '<p class="cd-check"><span class="cd-box">'.($on ? '&#10003;' : '').'</span> '.e($label).'</p>';
    $agree = fn (?string $v) => $v === 'da' ? 'SUNT DE ACORD' : ($v === 'nu' ? 'NU sunt de acord' : '-');
@endphp
<div class="cd">
    <div class="cd-head">
        <div class="cd-clinic">CLINICA VETERINARA HIKEVET | FEST4PETS SRL</div>
        <div>CUI: RO46349603 | Str. Baneasa nr. 23, Targu Mures, jud. Mures</div>
        <div>Tel. 0771 280 345 | contact@hikevet.com | www.hikevet.com</div>
        <h1>CONSIMTAMANT PENTRU ACCEPTAREA PROCEDURILOR MEDICALE / OPERATORII</h1>
    </div>

    <table class="cd-table">
        <tr><th>Proprietar / reprezentant legal</th><td>{{ $p['owner_name'] }}</td><th>Telefon</th><td>{{ $p['owner_phone'] }}</td></tr>
        <tr><th>Domiciliu</th><td>{{ $p['owner_address'] }}</td><th>CI/BI seria/nr.</th><td>{{ $p['owner_ci'] }}</td></tr>
        <tr><th>CNP</th><td>{{ $p['owner_cnp'] }}</td><th>E-mail</th><td>{{ $p['owner_email'] }}</td></tr>
        @if (! $group)
            <tr><th>Nume pacient</th><td>{{ $p['pet_name'] }}</td><th>Specie / rasa</th><td>{{ $p['pet_species_breed'] }}</td></tr>
            <tr><th>Varsta / sex</th><td>{{ $p['pet_age_sex'] }}</td><th>Microcip / pasaport</th><td>{{ $p['pet_microchip'] }}</td></tr>
            <tr><th>Greutate</th><td>{{ $p['pet_weight'] }}</td><th>Data</th><td>{{ \Illuminate\Support\Carbon::parse($p['consent_date'])->format('d.m.Y') }}</td></tr>
        @else
            <tr><th>Tip consimtamant</th><td>Comun pentru vizita</td><th>Numar pacienti</th><td>{{ count($p['visit_patients']) }}</td></tr>
            <tr><th>Data</th><td>{{ \Illuminate\Support\Carbon::parse($p['consent_date'])->format('d.m.Y') }}</td><th>Grup vizita</th><td>{{ $p['visit_group_id'] ?: '-' }}</td></tr>
        @endif
    </table>

    @if ($group)
        <div class="cd-group">
            <h2>Pacientii inclusi in aceasta vizita</h2>
            <p>Acest consimtamant este comun tuturor pacientilor enumerati mai jos, prezentati de acelasi proprietar in cadrul aceleiasi vizite.</p>
            <table class="cd-table">
                <thead><tr><th>Pacient</th><th>Specie / sex</th><th>Rasa</th><th>Greutate</th><th>Procedura</th></tr></thead>
                <tbody>
                @foreach ($p['visit_patients'] as $patient)
                    <tr><td>{{ $patient['pet_name'] ?: '-' }}</td><td>{{ trim(($patient['species'] ?? '').' / '.($patient['sex'] ?? '')) }}</td><td>{{ $patient['breed'] ?: '-' }}</td>
                        <td>{{ ($patient['weight'] ?? '') !== '' ? $patient['weight'].' kg' : '-' }}</td><td>{{ $patient['procedure'] ?: '-' }}</td></tr>
                @endforeach
                </tbody>
            </table>
            @if (! empty($p['visit_patients_updated_at']))
                <p class="cd-small">Lista administrativa a pacientilor vizitei a fost actualizata la: {{ $p['visit_patients_updated_at'] }}. Semnatura originala si data semnarii raman neschimbate.</p>
            @endif
        </div>
    @endif

    <h2>1. Declaratia proprietarului / reprezentantului legal</h2>
    <p>{{ $group
        ? 'Subsemnatul/a, in calitate de proprietar sau reprezentant legal al pacientilor mentionati mai sus, declar ca am solicitat si accept evaluarea, investigatiile si/sau procedurile medicale/chirurgicale recomandate de medicul veterinar curant pentru pacientii acestei vizite.'
        : 'Subsemnatul/a, in calitate de proprietar sau reprezentant legal al pacientului mentionat mai sus, declar ca am solicitat si accept evaluarea, investigatiile si/sau procedura medicala/chirurgicala recomandata de medicul veterinar curant.' }}</p>
    <p><strong>Procedura / interventia propusa:</strong> {{ $p['procedure'] }}<br>
       <strong>Medicul veterinar curant / chirurg:</strong> {{ $p['vet_name'] }}</p>

    <h2>2. Informarea medicala si consimtamantul informat</h2>
    <p>Mi-au fost explicate, pe intelesul meu, natura si scopul procedurii, beneficiile asteptate, alternativele rezonabile, riscurile efectuarii si riscurile neefectuarii procedurii.</p>
    <p>Inteleg ca medicina veterinara nu poate garanta rezultatul final, vindecarea completa sau absenta complicatiilor, chiar daca procedura este efectuata corect si cu respectarea standardelor profesionale.</p>
    <p>Accept efectuarea manoperelor medicale necesare pacientului, inclusiv recoltari, montare cateter, perfuzii, administrare medicamente, pansamente, investigatii imagistice sau de laborator, conform recomandarii medicului veterinar.</p>
    <p>In cazul aparitiei unor situatii neprevazute in timpul procedurii sau anesteziei, accept ca medicul veterinar sa efectueze manopere suplimentare necesare pentru protejarea vietii si starii de sanatate a pacientului, inclusiv resuscitare, transfuzie, drenaj, extinderea interventiei sau alte proceduri urgente, atunci cand amanarea ar creste riscul pentru pacient.</p>
    <p>Inteleg ca, ori de cate ori situatia permite, clinica va incerca sa ma contacteze pentru acord suplimentar inaintea procedurilor majore neplanificate.</p>

    <h2>3. Anestezie, sedare si riscuri specifice</h2>
    <p>Consimt la administrarea tipului de sedare/anestezie indicat de medicul veterinar, dupa evaluarea pacientului:</p>
    @foreach ($ana as $key => $label){!! $box(in_array($key, $p['anesthesia'] ?? [], true), $label) !!}@endforeach
    @if (! empty($p['anesthesia_other'])){!! $box(true, 'alta: '.$p['anesthesia_other']) !!}@endif
    <p>Riscurile actului anestezic mi-au fost explicate. Inteleg ca orice sedare/anestezie poate implica riscuri, inclusiv varsaturi, aspiratie, hipotensiune, tulburari de ritm cardiac, reactii adverse la medicamente, agravarea unei boli preexistente, stop cardio-respirator sau deces, chiar daca riscul este redus prin evaluare si monitorizare.</p>
    <p><strong>Declar pe propria raspundere ca pacientul:</strong></p>
    @if ($mode === 'tablet')
        <fieldset class="cd-field">
            <legend class="sr-only">Declaratie privind alimentatia</legend>
            <label class="cd-option"><input type="radio" name="food_status" value="nu_hrana" required @checked(old('food_status', $p['food_status'] ?? '') === 'nu_hrana')> nu a consumat hrana solida de la ora:</label>
            <input type="text" name="food_detail" value="{{ old('food_detail', $p['food_detail'] ?? '') }}" placeholder="Ora / detalii" aria-label="Ora sau detalii despre alimentatie" maxlength="120">
            <label class="cd-option"><input type="radio" name="food_status" value="a_consumat_sau_nu_stiu" required @checked(old('food_status', $p['food_status'] ?? '') === 'a_consumat_sau_nu_stiu')> a consumat hrana / apa sau nu cunosc exact</label>
        </fieldset>
    @else
        {!! $box(($p['food_status'] ?? '') === 'nu_hrana', 'nu a consumat hrana solida de la ora: '.($p['food_detail'] ?? '')) !!}
        {!! $box(($p['food_status'] ?? '') === 'a_consumat_sau_nu_stiu', 'a consumat hrana / apa sau nu cunosc exact: '.($p['food_detail'] ?? '')) !!}
    @endif

    <h2>4. Investigatii preoperatorii / complementare</h2>
    <p>Mi s-a explicat ca analizele si investigatiile nu elimina complet riscurile, dar pot reduce riscul anestezic si pot identifica afectiuni care influenteaza planul medical. Sunt de acord, conform recomandarii medicului veterinar, cu:</p>
    @foreach ($inv as $key => $label){!! $box(in_array($key, $p['investigations'] ?? [], true), $label) !!}@endforeach
    @if (! empty($p['investigations_other'])){!! $box(true, 'alte investigatii/proceduri: '.$p['investigations_other']) !!}@endif
    <p>Inteleg faptul ca efectuarea investigatiilor acceptate mai sus nu garanteaza obligatoriu reusita manoperelor medicale/chirurgicale, ele doar reduc pe cat posibil eventualele riscuri si complicatii.</p>

    <h2>5. Costuri, tratament postoperator si responsabilitati</h2>
    <p>Declar ca am fost informat/a asupra costurilor estimate pentru procedura, investigatii, anestezie, tratament, internare si monitorizare, acolo unde acestea pot fi anticipate. Inteleg ca pot aparea costuri suplimentare daca starea pacientului se modifica sau apar complicatii.</p>
    <p><strong>Pret estimativ:</strong> {{ $p['cost_from'] }} lei si {{ $p['cost_to'] }} lei.</p>
    <p>Ma oblig sa respect recomandarile primite la externare: administrarea tratamentului, purtarea gulerului/hamului medical, restrictii de miscare, controale, schimbari de pansament, dieta si monitorizarea semnelor de alarma.</p>
    <p>Inteleg ca nerespectarea recomandarilor medicale poate creste riscul de complicatii, recidive sau esec terapeutic.</p>

    <h2>6. Servicii recomandate pe care proprietarul le refuza</h2>
    <p>Declar ca am fost informat/a asupra importantei acestor servicii, dar NU sunt de acord cu urmatoarele servicii recomandate:</p>
    @foreach ($inv as $key => $label){!! $box(in_array($key, $p['refused_services'] ?? [], true), $label) !!}@endforeach
    @if (! empty($p['refused_other'])){!! $box(true, 'alte investigatii/proceduri: '.$p['refused_other']) !!}@endif
    <p>In situatia refuzului acestor investigatii imi asum toate riscurile ce deriva din acest refuz, respectiva nereusita procedurii medicale/operatorii si/sau decesul animalului sau eventualele complicatii postoperatorii.</p>

    <h2>7. Fotografii, filmari si utilizarea imaginilor pacientului</h2>
    <p>Inteleg diferenta dintre documentarea medicala si utilizarea imaginilor in scop public/marketing:</p>
    <p><strong>A. Documentare medicala interna:</strong> fotografiile/filmarile pacientului, ale leziunilor, procedurilor, investigatiilor sau evolutiei pot fi realizate pentru fisa medicala, comunicare medicala, monitorizarea evolutiei si arhivare profesionala. Aceste imagini nu vor fi publicate fara acord separat, cu exceptia situatiilor prevazute de lege sau a comunicarii strict necesare actului medical.</p>
    <p><strong>B. Utilizare educationala anonimizata:</strong> clinica poate folosi imagini in scop profesional/educational, fara date de identificare ale proprietarului si fara a prezenta informatii personale, doar daca proprietarul isi exprima acordul mai jos.</p>
    <p><strong>C. Publicare pe website/social media:</strong> publicarea fotografiilor sau filmarilor cu animalul pe pagina clinicii, website, materiale promotionale sau retele sociale se face doar cu acord explicit. Proprietarul poate refuza fara ca acest lucru sa afecteze calitatea actului medical.</p>
    <div class="cd-optional">
        <h3>Acorduri optionale</h3>
        @if ($mode === 'tablet')
            @foreach ([
                'media_medical' => ['Documentare medicala interna', 'SUNT DE ACORD cu realizarea si pastrarea fotografiilor/filmarilor strict pentru fisa medicala si documentarea cazului.', 'NU sunt de acord cu fotografii/filmari, cu exceptia celor absolut necesare medical/legal.'],
                'media_education' => ['Utilizare educationala anonimizata', 'SUNT DE ACORD cu folosirea imaginilor pacientului in scop educational, anonimizat.', 'NU sunt de acord cu folosirea imaginilor in scop educational.'],
                'media_publication' => ['Publicare website / social media', 'SUNT DE ACORD cu publicarea imaginilor pacientului pe website/retele sociale/materiale ale clinicii.', 'NU sunt de acord cu publicarea imaginilor pacientului pe website/retele sociale/materiale ale clinicii.'],
                'media_data_public' => ['Mentionare publica date animal', 'SUNT DE ACORD ca datele animalului (nume, varsta, rasa etc.) sa poata fi mentionate public.', 'NU sunt de acord ca datele animalului (nume, varsta, rasa etc.) sa poata fi mentionate public.'],
            ] as $key => [$legend, $yes, $no])
                <fieldset class="cd-field">
                    <legend>{{ $legend }}</legend>
                    <label class="cd-option"><input type="radio" name="{{ $key }}" value="da" required @checked(old($key, $p[$key] ?? '') === 'da')> {{ $yes }}</label>
                    <label class="cd-option"><input type="radio" name="{{ $key }}" value="nu" required @checked(old($key, $p[$key] ?? '') === 'nu')> {{ $no }}</label>
                </fieldset>
            @endforeach
        @else
            <p>Documentare medicala interna: <strong>{{ $agree($p['media_medical'] ?? null) }}</strong></p>
            <p>Utilizare educationala anonimizata: <strong>{{ $agree($p['media_education'] ?? null) }}</strong></p>
            <p>Publicare website/social media: <strong>{{ $agree($p['media_publication'] ?? null) }}</strong></p>
            <p>Mentionare publica date animal: <strong>{{ $agree($p['media_data_public'] ?? null) }}</strong></p>
        @endif
    </div>
    <p>Acordul pentru marketing/social media poate fi retras pentru utilizarile viitoare printr-o solicitare transmisa la contact@hikevet.com. Retragerea nu afecteaza legalitatea utilizarilor realizate anterior retragerii.</p>
    <p>In incinta clinicii, proprietarul nu are voie sa fotografieze/filmeze personalul medical, alti proprietari, alti pacienti, documente, ecrane sau proceduri medicale fara acordul conducerii clinicii si al persoanelor vizate.</p>

    <h2>8. Prelucrarea datelor cu caracter personal - informare GDPR</h2>
    <p><strong>Operator de date:</strong> FEST4PETS SRL - Clinica Veterinara HiKeVet, CUI RO46349603, punct de lucru Str. Baneasa nr. 23, Targu Mures, jud. Mures, tel. 0771 280 345, e-mail contact@hikevet.com, website www.hikevet.com.</p>
    <p><strong>Date prelucrate:</strong> date de identificare si contact ale proprietarului/reprezentantului legal, date privind pacientul animal, date medicale veterinare, date financiare/fiscale, comunicari, programari si consimtaminte.</p>
    <p><strong>Scopuri:</strong> furnizarea serviciilor medical-veterinare, intocmirea si pastrarea fiselor pacientilor, comunicarea cu proprietarul, emiterea documentelor fiscale, respectarea obligatiilor legale/profesionale, apararea drepturilor clinicii si, separat, marketing/publicare imagini doar pe baza acordului explicit.</p>
    <p><strong>Temeiuri juridice posibile:</strong> executarea relatiei contractuale, obligatii legale, interes legitim profesional/administrativ si consimtamant pentru prelucrarile optionale, inclusiv marketing/social media.</p>
    <p>Datele sunt pastrate pe durata necesara scopurilor de mai sus si conform obligatiilor legale, fiscale, profesionale si procedurilor interne aplicabile. Accesul este limitat la personalul autorizat si la furnizorii/autoritatile care au un temei legal sau contractual.</p>
    <p>Persoana vizata are drept de acces, rectificare, stergere, restrictionare, opozitie, portabilitate, retragerea consimtamantului pentru prelucrarile bazate pe consimtamant si dreptul de a depune plangere la ANSPDCP.</p>
    @if ($mode === 'print')
        <p><strong>Acord GDPR al proprietarului:</strong> {{ ! empty($p['owner_final_gdpr']) ? 'SUNT DE ACORD cu prelucrarea datelor personale în scopurile prezentate mai sus.' : 'Neconfirmat.' }}</p>
    @endif

    <h2>9. Confirmare finala</h2>
    <p>Certific faptul ca am citit sau mi-a fost citit acest document, am inteles explicatiile primite, am avut posibilitatea sa pun intrebari, iar raspunsurile primite au fost clare. Datorita acordului si a consimtamantului dat de a efectua procedura chirurgicala/medicala nu voi avea pretentii de natura materiala sau penala in cazul decesului animalului meu de companie.</p>
    <p>Consimtamantul nu limiteaza drepturile legale ale proprietarului si nu exonereaza personalul medical de raspunderea profesionala in cazurile prevazute de lege. El confirma intelegerea si acceptarea riscurilor medicale inerente explicate anterior procedurii.</p>
    <p>Declar pe propria raspundere ca la momentul semnarii acestui act, nu am consumat alcool, substante halucinogene etc. si sunt pe deplin lucid si informat.</p>
</div>
