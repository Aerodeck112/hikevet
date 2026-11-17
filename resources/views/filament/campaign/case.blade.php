<x-filament-panels::page>
<div class="hv-cp hv-client">
    {{-- Mesaje dupa salvare --}}
    @if (session('campaign_saved'))<div class="hv-cp-flash hv-cp-flash--ok">Modificările au fost salvate.</div>@endif
    @if (session('campaign_message'))<div class="hv-cp-flash hv-cp-flash--ok">{{ session('campaign_message') }}</div>@endif
    @if (session('campaign_warning'))<div class="hv-cp-flash hv-cp-flash--warn">{{ session('campaign_warning') }}</div>@endif
    @if (session('campaign_validation_blocked'))<div class="hv-cp-flash hv-cp-flash--warn">Dosarul nu poate fi validat pentru Primărie până nu sunt complete toate verificările obligatorii.</div>@endif
    @if (session('campaign_booking_mail') === 'sent')<div class="hv-cp-flash hv-cp-flash--ok">Emailul cu programarea și recomandările preoperatorii a fost trimis proprietarului.</div>@endif
    @if (session('campaign_booking_mail') === 'failed')<div class="hv-cp-flash hv-cp-flash--warn">Programarea a fost salvată, dar emailul preoperator nu a putut fi trimis. Verifică adresa și retrimite din programare.</div>@endif
    @if (session('campaign_number_saved') !== null && session('campaign_number_saved') !== '')
        <div class="hv-cp-flash hv-cp-flash--ok"><strong>Numărul pacientului a fost salvat exact cum l-ai introdus: {{ session('campaign_number_saved') }}.</strong>
            @if (session('campaign_renumbered')) {{ session('campaign_renumbered') }} dosar(e) fără fotografie care aveau același număr au primit automat alt număr.@endif</div>
        @if (session('campaign_unresolved'))<div class="hv-cp-flash hv-cp-flash--warn"><strong>Atenție:</strong> există încă {{ session('campaign_unresolved') }} dosar(e) cu fotografie care folosesc același număr. Nu au fost renumerotate automat, deoarece numărul trebuie să rămână identic cu cel din fotografie; auditul Primăriei le marchează pentru verificare.</div>@endif
    @endif
    @if (session('campaign_consent_code'))
        <div class="hv-cp-flash hv-cp-flash--ok">Cod pentru tabletă (se afișează o singură dată, valabil {{ (int) \App\Support\Settings::get('consent.code_ttl_hours', 12) }} ore): <span class="hv-cp-code">{{ session('campaign_consent_code') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="hv-cp-flash hv-cp-flash--bad">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    @if ($focusField)<div class="hv-cp-flash hv-cp-flash--warn"><strong>De completat:</strong> câmpul evidențiat mai jos.</div>@endif

    @unless ($isNew)
        <nav class="hv-cp-tabs" aria-label="Secțiunile dosarului">
            @foreach ($tabs as $key => $label)
                <a href="?case={{ $model->id }}&tab={{ $key }}" class="{{ $tab === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>

        {{-- Vizita: toti pacientii aceluiasi proprietar --}}
        <section class="hv-card">
            <h3>Vizita acestui proprietar · {{ count($group) }} {{ count($group) === 1 ? 'pacient' : 'pacienți' }}</h3>
            <table class="hv-table">
                @foreach ($group as $g)
                    <tr>
                        <td><a href="?case={{ $g['id'] }}&tab={{ $tab }}"><strong>{{ $g['cod_caz'] }}</strong></a>@if ($g['id'] === $model->id) <span class="hv-pill hv-pill--ok">acest dosar</span>@endif</td>
                        <td>{{ $g['nume_pacient'] ?: '-' }}</td><td>{{ trim($g['specie'].' / '.$g['sex'], ' /') }}</td><td>{{ $g['status_caz'] }}</td>
                        <td style="text-align:right">
                            @if ($canEdit && count($group) > 1)
                                <form method="post" action="{{ route('admin.campaign.visit-pet.remove', $g['id']) }}" onsubmit="var r=prompt('Motivul scoaterii acestui pacient din vizită:'); if(!r){return false;} this.reason.value=r; return true;">
                                    @csrf <input type="hidden" name="reason"><button class="hv-cp-btn hv-cp-btn--danger" type="submit">Scoate din vizită</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
            @if ($canEdit)
                <details style="margin-top:.75rem"><summary class="hv-cp-btn" style="display:inline-flex">+ Adaugă alt pacient aceluiași proprietar (aceeași vizită)</summary>
                    <form method="post" action="{{ route('admin.campaign.visit-pet', $model->id) }}" style="margin-top:.75rem">
                        @csrf
                        <div class="hv-cp-grid">
                            <div class="hv-cp-field"><label>Nume pacient *</label><input type="text" name="visit_pet[patient_name]" required></div>
                            <div class="hv-cp-field"><label>Specie *</label><select name="visit_pet[species]" required><option value="">Selectează</option><option>Caine</option><option>Pisica</option></select></div>
                            <div class="hv-cp-field"><label>Sex *</label><select name="visit_pet[sex]" required><option value="">Selectează</option><option>Mascul</option><option>Femela</option></select></div>
                            <div class="hv-cp-field"><label>Rasă</label><input type="text" name="visit_pet[breed]"></div>
                            <div class="hv-cp-field"><label>Greutate reală (kg)</label><input type="text" name="visit_pet[weight]"></div>
                            <div class="hv-cp-field"><span class="hv-cp-label">Vaccinat</span><div class="hv-cp-radio"><label><input type="radio" name="visit_pet[vaccinat]" value="Da"> Da</label><label><input type="radio" name="visit_pet[vaccinat]" value="Nu"> Nu</label></div></div>
                        </div>
                        <p class="hv-cp-note">Nu se creează o programare nouă. Proprietarul, CI-ul și consimțământul comun sunt preluate automat.</p>
                        <button class="hv-cp-btn hv-cp-btn--primary" type="submit">Adaugă pacientul în vizită</button>
                    </form>
                </details>
            @endif
        </section>

        {{-- Consimtamantul legat de dosar --}}
        @if (! in_array($tab, ['rezumat', 'istoric'], true))
            <section class="hv-card" @if (in_array($focusField, ['consent', 'gdpr'], true)) id="hv-focus" style="outline:3px solid #ff5b2e" @endif>
                @if (! $consent)
                    <h3>Consimțământ + GDPR</h3>
                    <p class="hv-cp-note">Documentul se creează automat după consultația preoperatorie și după completarea la recepție a emailului, adresei și seriei/numărului CI. Semnarea pe tabletă actualizează automat statusul în acest dosar.</p>
                @else
                    <h3>Consimțământ digital legat de dosar · #{{ $consent->id }}</h3>
                    <p>
                        <span class="hv-pill {{ $consent->isSigned() ? 'hv-pill--ok' : 'hv-pill--warn' }}">{{ $consent->isSigned() ? 'Semnat de client'.($consent->signed_at ? ' · '.$consent->signed_at->format('d.m.Y H:i') : '') : ($consent->status->value === 'cancelled' ? 'Anulat' : 'În așteptare semnătură') }}</span>
                        @if ($consent->isSigned())<span class="hv-pill {{ in_array($gdprState, ['confirmed', 'signed_confirmation'], true) ? 'hv-pill--ok' : 'hv-pill--warn' }}">GDPR: {{ \App\Services\Campaign\CityChecks::gdprLabel($gdprState) }}</span>@endif
                        @if ($consent->supersedes_id)<span class="hv-pill">versiune corectată a #{{ $consent->supersedes_id }}</span>@endif
                    </p>
                    @if ($consent->isSigned() && ! $identity['ok'])
                        <p class="hv-cp-flash hv-cp-flash--warn">Numele din dosar nu coincid cu documentul semnat (proprietar: {{ $identity['consent_owner'] ?: '-' }}, pacient: {{ $identity['consent_patient'] ?: '-' }}). La salvarea dosarului se emite automat o versiune corectată, de semnat din nou; documentul semnat rămâne neschimbat.</p>
                    @endif
                    <div class="hv-cp-actions">
                        <a class="hv-cp-btn hv-cp-btn--primary" href="{{ $consentUrl }}">Deschide consimțământul</a>
                        <a class="hv-cp-btn" href="{{ $consentPrintUrl }}" target="_blank">Tipărește</a>
                        @if ($canEdit && ! $consent->isSigned() && $consent->status->value === 'pending')
                            <form method="post" action="{{ route('admin.campaign.consent-code', $model->id) }}">@csrf<button class="hv-cp-btn" type="submit">Generează cod pentru tabletă</button></form>
                        @endif
                    </div>
                    <p class="hv-cp-note" style="margin-top:.6rem">{{ $consent->isSigned() ? 'Datele consimțământului sunt blocate după semnare. Semnătura medicului se poate adăuga din documentul de consimțământ.' : 'La următoarele salvări ale dosarului, datele clinice sunt sincronizate automat în consimțământ până când clientul semnează.' }}</p>
                @endif
            </section>
        @endif
    @endunless

    @if ($tab === 'rezumat')
        @include('filament.campaign.summary')
    @elseif ($tab === 'istoric')
        @include('filament.campaign.history')
    @else
        <form method="post" action="{{ route('admin.campaign.save') }}" enctype="multipart/form-data" class="hv-cp">
            @csrf
            <input type="hidden" name="case_id" value="{{ $model?->id }}">
            <input type="hidden" name="section" value="{{ $tab }}">

            @if (! $isNew && $tab === 'receptie')
                <section class="hv-card">
                    <h3>Programare telefonică – date deja salvate</h3>
                    <div class="hv-cp-grid">
                        @foreach (['Proprietar' => $row['nume_proprietar'], 'Telefon' => $row['telefon'], 'Data și ora' => trim(($row['data_programare'] ?: '-').' · '.$row['ora_programare'], ' ·'),
                            'Pacient la apel' => trim(($row['specie'] ?: '-').' / '.($row['sex'] ?: '-')), 'Vaccinat' => $row['vaccinat'] ?: '-', 'Intervenție programată' => $row['procedura_programata'] ?: '-'] as $label => $value)
                            <div><span class="hv-cp-label">{{ $label }}</span><div>{{ $value ?: '-' }}</div></div>
                        @endforeach
                    </div>
                    <div class="hv-cp-actions" style="margin-top:.75rem">
                        @if ($row['initial_appointment_id'])<a class="hv-cp-btn" href="{{ url('/admin/campanie/programari') }}?appointment={{ $row['initial_appointment_id'] }}">Modifică programarea</a>@endif
                        <a class="hv-cp-btn hv-cp-btn--primary" href="?case={{ $model->id }}&tab=preoperator">Deschide consultația preoperatorie</a>
                        <a class="hv-cp-btn" href="{{ url('/admin/campanie/programari') }}?new=1&owner_case={{ $model->id }}">+ Programează mai mulți pacienți pentru acest proprietar</a>
                    </div>
                </section>
            @endif
            @if ($tab === 'preoperator')
                <section class="hv-card">
                    <h3>Date proprietar și pacient preluate</h3>
                    <div class="hv-cp-grid">
                        @foreach (['Proprietar' => $row['nume_proprietar'], 'Telefon' => $row['telefon'], 'Email' => $row['email'], 'Adresa' => $row['adresa_ci'], 'Animal cu stăpân' => $row['animal_cu_stapan'] ?: 'Cu stapan', 'Vaccinat' => $row['vaccinat']] as $label => $value)
                            <div><span class="hv-cp-label">{{ $label }}</span><div>{{ $value ?: '-' }}</div></div>
                        @endforeach
                    </div>
                </section>
            @endif
            @if ($tab === 'validare')
                <section class="hv-card">
                    <h3>Verificări pentru Primărie · {{ $checklist['done'] }}/{{ $checklist['total'] }}</h3>
                    <p class="hv-cp-flash {{ $ready ? 'hv-cp-flash--ok' : 'hv-cp-flash--warn' }}">{{ $ready ? 'Dosarul este complet și poate fi validat pentru raportare.' : 'Dosarul are câmpuri lipsă. Apasă pe elementul lipsă pentru a merge direct la secțiunea care trebuie completată.' }}</p>
                    <div class="hv-cp-progress" style="margin:.6rem 0"><span style="width:{{ $checklist['percent'] }}%"></span></div>
                    <div style="display:grid;gap:.3rem">
                        @foreach ($checklist['checks'] as $check)
                            @if ($check['ok'])
                                <div class="hv-cp-check is-ok"><span>✓</span><strong>{{ $check['label'] }}</strong><span style="margin-left:auto">Complet</span></div>
                            @else
                                <a class="hv-cp-check is-missing" href="?case={{ $model->id }}&tab={{ $check['tab'] }}&focus={{ $check['focus'] }}"><span>!</span><strong>{{ $check['label'] }}</strong><span style="margin-left:auto">Lipsește · Rezolvă →</span></a>
                            @endif
                        @endforeach
                    </div>
                    <p class="hv-cp-note" style="margin-top:.75rem">Elementele obligatorii pentru Primărie blochează validarea. GDPR rămâne afișat separat, pentru verificarea conformității.</p>
                </section>
            @endif
            @if ($tab === 'corectare' && $reportingDate)
                <div class="hv-cp-flash hv-cp-flash--ok"><strong>Luna raportului Primărie:</strong> {{ \Illuminate\Support\Carbon::parse($reportingDate)->locale('ro')->translatedFormat('F Y') }} · data fixată: {{ \Illuminate\Support\Carbon::parse($reportingDate)->format('d.m.Y') }}. Corectarea numelui, numărului pacientului, CI-ului sau fotografiei nu schimbă această lună.</div>
            @endif

            @foreach ($cards as $card)
                <section class="hv-card">
                    <h3>{{ $card['title'] }}</h3>
                    @if (! empty($card['note']))<p class="hv-cp-note">{{ $card['note'] }}</p>@endif
                    <div class="hv-cp-grid">
                        @foreach ($card['fields'] as $field)
                            @if ($field['name'] === 'recomandari_postoperatorii_text')
                                @php $row['recomandari_postoperatorii_text'] = $recommendations; @endphp
                            @endif
                            @if ($field['name'] === 'procedura_efectuata' && $tab === 'interventie' && $row['procedura_efectuata'] === '')
                                @php $row['procedura_efectuata'] = \App\Services\Campaign\CaseFields::medicalProcedure($row); @endphp
                            @endif
                            @if ($field['name'] === 'data_interventie' && $tab === 'interventie' && $row['data_interventie'] === '')
                                @php $row['data_interventie'] = $row['data_programare'] ?: \App\Services\Campaign\CaseFields::today(); @endphp
                            @endif
                            @include('filament.campaign.field', ['field' => $field])
                            @if ($field['name'] === 'numar_pacient' && ! $isNew && $reportNumber !== '')
                                <div class="hv-cp-field"><span class="hv-cp-label">Număr folosit în raportul Primărie</span><div><strong style="font-size:1.3rem">{{ $reportNumber }}</strong><div class="hv-muted" style="font-size:.78rem">Înghețat pentru raport; o corectare manuală a câmpului „Număr pacient” îl actualizează explicit.</div></div></div>
                            @endif
                        @endforeach
                    </div>
                    @if ($tab === 'externare')
                        <p class="hv-cp-flash hv-cp-flash--ok" style="margin-top:.75rem"><strong>Rezumat pacient:</strong> se trimite automat imediat după salvare, la emailul din dosar. <strong>Follow-up postoperator:</strong> se trimite automat la 48 de ore după externare, estimativ la {{ $postopDue ?: '-' }}.</p>
                    @endif
                </section>
            @endforeach

            @if ($tab === 'interventie' && ! $isNew && $qr)
                <section class="hv-card hv-cp-qr">
                    <img src="{{ $qr }}" alt="Cod QR pentru încărcarea fotografiei de pe telefon">
                    <div>
                        <h3 style="margin-top:0">Încarcă poza de pe telefon</h3>
                        <p>Scanează codul QR cu telefonul. Se deschide o pagină pentru acest dosar, unde poți face poza direct cu camera sau poți alege una din galerie. Poza apare aici automat.</p>
                        <div class="hv-cp-actions"><a class="hv-cp-btn" href="{{ $phoneUrl }}" target="_blank">Deschide pagina de încărcare foto</a></div>
                        <p class="hv-muted" style="font-size:.78rem;margin-top:.5rem">Codul QR este generat local și conține doar un token aleatoriu, fără nume sau alte date ale proprietarului.</p>
                    </div>
                </section>
            @endif

            @if ($canEdit && ($tab !== 'validare' || $canValidate))
                <div class="hv-cp-actions">
                    <button class="hv-cp-btn hv-cp-btn--primary" type="submit">Salvează secțiunea</button>
                    @unless ($isNew)<a class="hv-cp-btn" href="?case={{ $model->id }}&tab=rezumat">Vezi rezumatul pacientului</a>@endunless
                </div>
            @elseif ($tab === 'validare' && ! $canValidate)
                <p class="hv-cp-note">Validarea pentru Primărie o pot face doar medicii și administratorii.</p>
            @endif
        </form>

        @if (! $isNew && in_array($tab, ['externare', 'corectare'], true) && $canEdit)
            @if (! empty($row['final_summary_email_sent_at']))
                <section class="hv-card" style="border-color:#fdba74;background:#fffaf2">
                    <h3 style="color:#9a3412">Retrimite datele finale corectate</h3>
                    <p>Emailul postoperator a fost trimis anterior la <strong>{{ $row['final_summary_email_sent_at'] }}</strong>.
                        @if ($row['timestamp_actualizat'] > $row['final_summary_email_sent_at'])<strong>Dosarul a fost modificat după trimitere.</strong>@endif
                        Butonul generează din nou emailul din datele actuale ale dosarului, cu subiectul „Rezumat postoperator ACTUALIZAT”.</p>
                    <form method="post" action="{{ route('admin.campaign.resend-final', $model->id) }}" onsubmit="return confirm('Retrimiți proprietarului emailul final cu datele actualizate din dosar?')">@csrf<button class="hv-cp-btn hv-cp-btn--primary" type="submit">Retrimite emailul final actualizat</button></form>
                </section>
            @endif
        @endif
        @if (! $isNew && in_array($tab, ['rezumat', 'validare', 'corectare'], true) && $canExport)
            <section class="hv-card">
                <h3>Dosarul pentru Primărie</h3>
                <p class="hv-cp-note">Arhivă cu fișa pacientului (PDF), fotografia, consimțământul semnat (PDF), actul de identitate și manifestul cu situația dosarului. Documentele lipsă apar ca fișiere LIPSA / EROARE.</p>
                <form method="post" action="{{ route('admin.campaign.report.case-zip', $model->id) }}">@csrf<button class="hv-cp-btn hv-cp-btn--primary" type="submit">Descarcă ZIP dosar</button></form>
            </section>
        @endif
        @if (! $isNew && $tab === 'corectare' && $canEdit)
            <section class="hv-card" style="border-color:#fecaca;background:#fff7f7">
                <h3 style="color:#991b1b">Ștergere dosar</h3>
                <p>Dosarul este marcat șters și dispare din liste și rapoarte. Datele, fișierele și istoricul rămân păstrate. Programările lui sunt anulate.</p>
                <form method="post" action="{{ route('admin.campaign.delete', $model->id) }}" onsubmit="var r=prompt('Motivul ștergerii dosarului {{ $row['cod_caz'] }}:'); if(!r){return false;} this.reason.value=r; return true;">
                    @csrf <input type="hidden" name="reason"><button class="hv-cp-btn hv-cp-btn--danger" type="submit">Șterge dosarul</button>
                </form>
            </section>
        @endif
    @endif
</div>

@if (! $isNew && $tab === 'interventie')
<script>
(function () {
    var current = @json($photo?->id);
    var url = @json(route('admin.campaign.photo-status', $model->id));
    function check() {
        fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); }).then(function (d) {
            if (d && d.document_id && d.document_id !== current) { window.location.reload(); }
        }).catch(function () {}).finally(function () { setTimeout(check, 7000); });
    }
    setTimeout(check, 7000);
})();
</script>
@endif
@if ($focusField)
<script>document.addEventListener('DOMContentLoaded', function () { var el = document.getElementById('hv-focus'); if (el) { el.scrollIntoView({behavior: 'smooth', block: 'center'}); var i = el.querySelector('input,select,textarea'); if (i) { setTimeout(function () { i.focus({preventScroll: true}); }, 500); } } });</script>
@endif
</x-filament-panels::page>
