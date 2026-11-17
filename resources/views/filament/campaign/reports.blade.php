@use('App\Http\Controllers\Admin\CampaignController')
@use('App\Http\Controllers\Admin\CampaignReportController')
@use('App\Services\Campaign\CityChecks')
@use('App\Services\Campaign\CityReports')
<x-filament-panels::page>
@php
    $q = fn (array $extra = []) => http_build_query(array_filter(['month' => $month] + $extra, fn ($v) => $v !== null && $v !== ''));
    $hidden = function () use ($month, $keep) {
        $html = '<input type="hidden" name="month" value="'.e($month).'">';
        foreach ($keep as $k => $v) {
            $html .= '<input type="hidden" name="keep['.e($k).']" value="'.e($v).'">';
        }
        return new Illuminate\Support\HtmlString($html.csrf_field());
    };
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') : '—';
@endphp
<div class="hv-cp hv-client hv-rp">
    @include('filament.campaign._stopped')
    @if (session('campaign_message'))<div class="hv-cp-flash hv-cp-flash--ok">{!! nl2br(e(session('campaign_message'))) !!}</div>@endif
    @if (session('campaign_error'))<div class="hv-cp-flash hv-cp-flash--bad">{{ session('campaign_error') }}</div>@endif
    @if ($rowsError)<div class="hv-cp-flash hv-cp-flash--bad">{{ $rowsError }} Până atunci, mai jos apar toate intervențiile din octombrie, fără excluderi.</div>@endif

    {{-- Perioada --}}
    <section class="hv-card">
        <form method="get" class="hv-cp-filters" action="{{ CampaignReportController::pageUrl() }}">
            <label class="hv-cp-field"><span class="hv-cp-label">Luna raportării</span>
                <select name="month" onchange="this.form.submit()">
                    <option value="">Toată perioada ({{ array_sum($months) }})</option>
                    @foreach ($months as $m => $count)
                        @if (CityReports::validMonth($m))<option value="{{ $m }}" @selected($month === $m)>{{ CityReports::monthLabel($m) }} ({{ $count }})</option>@endif
                    @endforeach
                    @if ($month && ! isset($months[$month]))<option value="{{ $month }}" selected>{{ $monthLabel }} (0)</option>@endif
                </select></label>
            <label class="hv-cp-field"><span class="hv-cp-label">Altă lună</span><input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()"></label>
            <div class="hv-cp-actions"><a class="hv-cp-btn" href="{{ CampaignReportController::pageUrl() }}">Toată perioada</a></div>
        </form>
        @if ($month !== CityReports::OCTOBER)
            <p class="hv-cp-note"><strong>Perioada raportului este stabilă.</strong> Modificarea ulterioară a unui nume, număr pacient, fotografie, CI sau altă informație nu schimbă luna în care pacientul a fost raportat. Pentru dosarele vechi, luna este recuperată din data inițială a finalizării intervenției.</p>
        @endif
        @if (isset($months['fără dată']))<p class="hv-cp-note">{{ $months['fără dată'] }} intervenții finalizate nu au nicio dată (Lipsă în sursă); apar doar la „Toată perioada”.</p>@endif
        @if ($backfill['restored_history'])
            <div class="hv-cp-flash hv-cp-flash--ok"><strong>Numere recuperate automat din istoricul dosarelor: {{ count($backfill['restored_history']) }}.</strong> Numărul vechi a fost restaurat doar pentru raport, fără să modifice fotografia.
                @foreach ($backfill['restored_history'] as $r)<div>{{ ($r['patient'] ?: $r['code']).': raport '.$r['old'].' · număr operațional actual '.$r['current'] }}</div>@endforeach</div>
        @endif
    </section>

    {{-- Octombrie 2026: excluderea cazurilor raportate anterior --}}
    @if ($prior)
        <details class="hv-card" open>
            <summary><strong>Octombrie 2026 - intervenții din 01.10.2026 până în 31.10.2026</strong></summary>
            <p><strong>{{ count($prior['excluded']) }}</strong> dosare deja raportate, excluse dintre intervențiile din octombrie; <strong>{{ $prior['all'] - count($prior['excluded']) }}</strong> rămân înainte de verificarea documentelor.</p>
            <p class="hv-cp-note">Sunt selectate strict datele reale ale intervenției din octombrie, fără preluarea unei date din alt câmp. Aceeași selecție se aplică desfășurătorului, centralizatorului și exporturilor PDF, Excel, CSV și ZIP pentru octombrie. Dosarele rămân în aplicație.</p>
            @if ($prior['excluded'])
                <details><summary>Vezi dosarele excluse ({{ count($prior['excluded']) }})</summary>
                    <table class="hv-table"><thead><tr><th>Nr. pacient</th><th>Cod caz</th><th>Pacient</th><th>Raport anterior</th></tr></thead>
                        @foreach ($prior['excluded'] as $r)<tr><td>{{ CityChecks::reportNumber($r) }}</td><td>{{ $r['cod_caz'] }}</td><td>{{ $r['nume_pacient'] }}</td><td>{{ $prior['codes'][strtoupper(trim($r['cod_caz']))] ?? '' }}</td></tr>@endforeach
                    </table></details>
            @endif
            <details style="margin-top:.75rem"><summary>Lista cazurilor raportate până în septembrie ({{ count($prior['codes']) }} coduri)</summary>
                <p class="hv-cp-note">Lista inițială provine din rapoartele de iulie (100), august (122) și selecția finală de septembrie (142). Codurile care apar în mai multe rapoarte sunt numărate o singură dată. Modifică lista dacă raportările finale diferă.</p>
                @if ($canValidate)
                    <form method="post" action="{{ route('admin.campaign.report.prior') }}">@csrf
                        <input type="hidden" name="registry_hash" value="{{ $prior['hash'] }}">
                        <textarea name="codes" rows="12" style="width:100%;font-family:monospace" aria-label="Codurile cazurilor raportate anterior">{{ collect($prior['codes'])->map(fn ($p, $c) => $c.';'.$p)->implode("\n") }}</textarea>
                        <p class="hv-cp-note">Un cod unic pe linie, urmat de punct și virgulă și luna raportării. Eliminarea unui cod permite reincluderea lui în octombrie dacă îndeplinește criteriile raportului.</p>
                        <button class="hv-cp-btn hv-cp-btn--primary" type="submit">Salvează lista de excludere</button>
                    </form>
                @else
                    <pre style="max-height:16rem;overflow:auto">{{ collect($prior['codes'])->map(fn ($p, $c) => $c.';'.$p)->implode("\n") }}</pre>
                @endif
            </details>
        </details>
    @endif

    {{-- Septembrie 2026: selectia fixa de 145 --}}
    @if ($september)
        <details class="hv-card">
            <summary><strong>Septembrie 2026 - selecția verificată de 145 de cazuri</strong></summary>
            <p>137 dosare din lista de septembrie + 8 cazuri omise anterior. Selecția este fixată după codul dosarului. Datele și documentele sunt preluate din dosarele actuale.</p>
            @if ($september['error'])
                <div class="hv-cp-flash hv-cp-flash--warn">{{ $september['error'] }}</div>
            @else
                <p><strong>145 / 145 dosare identificate.</strong> ZIP-ul include dosarele, desfășurătorul Excel/PDF și centralizatorul general pentru aceeași selecție.</p>
                @if ($canExport)
                    <div class="hv-cp-actions">
                        <form method="post" action="{{ route('admin.campaign.report.zip') }}">@csrf<input type="hidden" name="month" value="2026-09"><input type="hidden" name="selection" value="{{ CityReports::SEPTEMBER_145 }}"><button class="hv-cp-btn hv-cp-btn--primary" type="submit">Descarcă ZIP - 145 dosare septembrie</button></form>
                        <a class="hv-cp-btn" href="{{ route('admin.campaign.report.centralizator', ['month' => '2026-09', 'selection' => CityReports::SEPTEMBER_145]) }}">Centralizator PDF - 145 cazuri</a>
                        <a class="hv-cp-btn" href="{{ route('admin.campaign.report.pdf', ['month' => '2026-09', 'selection' => CityReports::SEPTEMBER_145]) }}">Desfășurător PDF - 145 cazuri</a>
                        <a class="hv-cp-btn" href="{{ route('admin.campaign.report.xlsx', ['month' => '2026-09', 'selection' => CityReports::SEPTEMBER_145]) }}">Desfășurător Excel - 145 cazuri</a>
                    </div>
                @endif
            @endif
        </details>
    @endif

    {{-- Indicatori --}}
    <section class="hv-rp-kpis">
        @foreach ([[$summary['total_finalized'], 'Intervenții finalizate'], [$summary['zip_included'], 'Incluse în ZIP'], [$summary['ready_export'], 'Complete fără lipsuri'],
            [$summary['incomplete_export'], 'Incluse cu lipsuri'], [$summary['missing_photo'], 'Fără fotografie'], [$summary['missing_consent'], 'Fără consimțământ'],
            [$summary['missing_id'], 'Fără CI'], [$summary['gdpr_warnings'], 'GDPR de verificat'], [$summary['integrity_errors'], 'Erori integritate'], [$summary['with_id'], 'Cu act de identitate']] as [$value, $label])
            <div class="hv-rp-kpi"><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
        @endforeach
    </section>

    {{-- Exporturi oficiale --}}
    <details class="hv-card" open>
        <summary><strong>Desfășurător, centralizator și ZIP - {{ $monthLabel }}</strong></summary>
        <p class="hv-cp-note"><strong>Desfășurător oficial.</strong> Excelul și PDF-ul oficial includ toate intervențiile finalizate din perioada selectată și nu afișează statusuri interne precum „Nevalidat”, „Dosar incomplet”, „Lipsuri” sau rezultate de audit. Auditul rămâne vizibil doar intern și nu blochează exportul.</p>
        @if ($canExport)
            <div class="hv-cp-actions">
                <a class="hv-cp-btn hv-cp-btn--primary" href="{{ route('admin.campaign.report.xlsx', ['month' => $month]) }}">Excel desfășurător</a>
                <a class="hv-cp-btn" href="{{ route('admin.campaign.report.pdf', ['month' => $month]) }}">PDF desfășurător</a>
                <a class="hv-cp-btn" href="{{ route('admin.campaign.report.centralizator', ['month' => $month]) }}">Centralizator PDF</a>
                <a class="hv-cp-btn" href="{{ route('admin.campaign.report.csv', ['month' => $month]) }}" title="Toate coloanele, inclusiv validarea internă">CSV intern (cu validare)</a>
            </div>
        @else
            <p class="hv-cp-note">Exporturile conțin date personale și sunt disponibile doar rolurilor cu drept de export.</p>
        @endif

        <h3 class="hv-rp-h">Centralizator general</h3>
        <p class="hv-cp-note">Se generează automat din pacienții finalizați ai perioadei selectate, grupați exact în: Câine, Cățea, Motan, Pisică.</p>
        <div class="hv-rp-kpis hv-rp-kpis--small">
            @foreach ([['Câine', $animals['CAINE']], ['Cățea', $animals['CATEA']], ['Motan', $animals['MOTAN']], ['Pisică', $animals['PISICA']], ['TOTAL', $animals['total']]] as [$label, $value])
                <div class="hv-rp-kpi"><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
            @endforeach
        </div>
        @if ($animals['unclassified'])
            <div class="hv-cp-flash hv-cp-flash--warn"><strong>Atenție:</strong> {{ count($animals['unclassified']) }} pacienți nu au Specia + Sexul suficient de clar; centralizatorul nu se poate genera până nu sunt corectați:
                @foreach ($animals['unclassified'] as $u)<a href="{{ CampaignController::caseUrl($u['id'], 'preoperator', ['focus' => 'specie']) }}">{{ ($u['patient'] ?: $u['code']).' ('.($u['species'] ?: '-').' / '.($u['sex'] ?: '-').')' }}</a>@if (! $loop->last), @endif @endforeach</div>
        @endif

        <h3 class="hv-rp-h">Export ZIP - toate intervențiile finalizate</h3>
        <p class="hv-cp-note">Arhiva include toate intervențiile finalizate din perioada selectată. Dacă lipsește un document, folderul pacientului rămâne în ZIP și primește un fișier LIPSA/EROARE plus manifestul cu situația exactă. Exportul poate dura câteva minute dacă sunt multe fotografii și PDF-uri.</p>
        @if ($canExport)
            <form method="post" action="{{ route('admin.campaign.report.zip') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Se pregătește arhiva…';setTimeout(()=>{this.querySelector('button').disabled=false;this.querySelector('button').textContent='Descarcă ZIP cu toate intervențiile finalizate'},30000)">
                {{ $hidden() }}<button class="hv-cp-btn hv-cp-btn--primary" type="submit" @disabled(! $summary['zip_included'])>Descarcă ZIP cu toate intervențiile finalizate</button>
            </form>
        @endif
    </details>

    {{-- Lipsuri --}}
    @if ($summary['reason_counts'] || $summary['gdpr_warnings'])
        <details class="hv-card">
            <summary><strong>Lipsuri / neconcordanțe în {{ $summary['incomplete_export'] }} dosare incluse</strong></summary>
            <p class="hv-cp-note">Toate aceste dosare intră în ZIP. Mai jos vezi doar ce trebuie verificat/completat. Un dosar poate apărea la mai multe motive.</p>
            <div class="hv-rp-kpis hv-rp-kpis--small">@foreach ($summary['reason_counts'] as $reason => $count)<div class="hv-rp-kpi"><strong>{{ $count }}</strong><span>{{ $reason }}</span></div>@endforeach</div>
            @if ($summary['gdpr_warnings'])<p class="hv-cp-flash hv-cp-flash--warn"><strong>GDPR de verificat: {{ $summary['gdpr_warnings'] }} dosare.</strong> Nu sunt excluse din ZIP dacă au consimțământul digital semnat și restul criteriilor obligatorii sunt corecte; marcajul rămâne pentru verificare internă.</p>@endif
            <table class="hv-table">
                <thead><tr><th>Dosar</th><th>Lipsuri</th><th></th></tr></thead>
                @foreach ($scan as $item)
                    @continue($item['complete'])
                    <tr><td><strong>{{ (CityChecks::reportNumber($item['row']) ? 'Nr. '.CityChecks::reportNumber($item['row']) : $item['row']['cod_caz']).' · '.$item['row']['nume_pacient'] }}</strong><div class="hv-muted">{{ $item['row']['nume_proprietar'] }} · {{ $item['row']['cod_caz'] }}</div></td>
                        <td>{{ implode(' · ', $item['missing']) }}</td>
                        <td><a class="hv-cp-btn" href="{{ CampaignController::caseUrl($item['row']['id'], 'corectare') }}">Corectează dosarul</a></td></tr>
                @endforeach
            </table>
        </details>
    @endif

    {{-- Audit integritate --}}
    <details class="hv-card" @if ($audit['critical']) open @endif>
        <summary><strong>Audit integritate înainte de Primărie</strong> · <span class="hv-pill {{ $audit['critical'] ? 'hv-pill--bad' : 'hv-pill--ok' }}">{{ $audit['critical'] ? $audit['critical'].' probleme critice' : 'Audit curat ✓' }}</span></summary>
        <p class="hv-cp-note">Verifică automat tipurile de erori semnalate de Primărie: numere duplicate, fotografii reutilizate, CI lipsă/greșit, adresă suspectă și nume diferite între dosar și consimțământ.</p>
        <div class="hv-rp-split">
            @if ($canManage)
                <form method="post" action="{{ route('admin.campaign.report.repair') }}" onsubmit="return confirm('Verific și repar automat numerele lipsă/duplicate și sincronizez consimțămintele din datele actuale ale dosarelor? Consimțămintele deja semnate cu nume vechi nu vor fi modificate; se vor crea variante corectate pentru resemnare. Continui?')">
                    {{ $hidden() }}<strong>Reparare automată a perioadei {{ $month }}</strong><p class="hv-cp-note">Verifică toate intervențiile finalizate din perioada selectată, alocă numere unice (fără să schimbe numerele dosarelor cu fotografie) și sincronizează numele din consimțăminte.</p>
                    <button class="hv-cp-btn hv-cp-btn--primary" type="submit">Repară automat datele</button></form>
            @endif
            @if ($canValidate)
                <form method="post" action="{{ route('admin.campaign.report.bulk-validate') }}" onsubmit="return confirm('Validez pentru Primărie toate dosarele complete care nu sunt încă validate? Dosarele incomplete nu sunt atinse.')">
                    {{ $hidden() }}<strong>Validare în masă a dosarelor complete</strong><p class="hv-cp-note">Toate dosarele (din orice lună) care trec toate verificările primesc „Validat pentru Primărie”. Cele incomplete nu sunt modificate.</p>
                    <label class="hv-cp-field"><span class="hv-cp-label">Validat de</span><select name="validator">@foreach ($doctors as $d)<option @selected($d === CityReports::BULK_VALIDATOR)>{{ $d }}</option>@endforeach</select></label>
                    <button class="hv-cp-btn" type="submit">Validează dosarele complete</button></form>
            @endif
        </div>
        @if ($audit['critical'])
            <div class="hv-cp-flash hv-cp-flash--warn"><strong>Există probleme de integritate internă.</strong> Desfășurătorul, centralizatorul și ZIP-ul pot fi generate; problemele rămân marcate aici pentru verificare.</div>
            @foreach ($audit['items'] as $item)
                @php($critical = array_filter($item['issues'], fn ($i) => $i['severity'] === 'critical'))
                @continue(! $critical)
                <div class="hv-rp-case">
                    <div class="hv-rp-case-head"><div><strong>{{ (CityChecks::reportNumber($item['row']) ? 'Pacient nr. '.CityChecks::reportNumber($item['row']) : $item['row']['cod_caz']).' · '.$item['row']['nume_pacient'] }}</strong><div class="hv-muted">{{ $item['row']['nume_proprietar'] }} · {{ $item['row']['cod_caz'] }}</div></div>
                        <a class="hv-cp-btn" href="{{ CampaignController::caseUrl($item['row']['id'], 'corectare') }}">Deschide dosarul</a></div>
                    @foreach ($critical as $issue)
                        <a class="hv-rp-issue" href="{{ CampaignController::caseUrl($item['row']['id'], $issue['tab'], ['focus' => $issue['focus']]) }}"><strong>{{ $issue['label'] }}</strong><span>{{ $issue['detail'] }}</span><em>Rezolvă →</em></a>
                    @endforeach
                    @if ($canManage && collect($critical)->contains('reissue', true))
                        <form method="post" action="{{ route('admin.campaign.report.reissue-consent', $item['row']['id']) }}" onsubmit="return confirm('Se va păstra consimțământul semnat vechi pentru audit și se va crea un consimțământ CORECTAT, precompletat din datele actuale. Proprietarul trebuie să îl semneze din nou. Continui?')">
                            {{ $hidden() }}<button class="hv-cp-btn hv-cp-btn--primary" type="submit">Generează consimțământ corectat pentru resemnare</button></form>
                    @endif
                </div>
            @endforeach
        @else
            <p class="hv-cp-flash hv-cp-flash--ok"><strong>Verificarea de integritate este în regulă.</strong> Nu există numere duplicate, conflicte evidente între dosar/consimțământ sau documente reutilizate la persoane diferite în selecția curentă.</p>
        @endif
    </details>

    {{-- Control numere --}}
    <details class="hv-card" @if ($numberAudit['duplicates']) open @endif>
        <summary><strong>Control numere pacient înainte de Primărie</strong> · {{ $numberAudit['count'] }} numere unice · {{ count($numberAudit['duplicates']) }} dubluri · {{ count($numberAudit['gaps']) }} goluri</summary>
        <p class="hv-cp-note">Sunt verificate separat numerele oficiale folosite în raport. Acestea sunt înghețate și nu mai pot fi schimbate automat.</p>
        @if ($numberAudit['gaps'])
            <p><strong>Numere care nu apar în perioada selectată:</strong> {{ CityReports::compactRanges($numberAudit['gaps']) }}.</p>
            <p class="hv-cp-note">Golurile nu sunt completate cu pacienți inventați. Sunt afișate ca să verifici dacă numărul aparține unui dosar anulat, unei alte perioade sau unui dosar renumerotat istoric.</p>
        @endif
        @if ($numberAudit['duplicates'])
            <p class="hv-cp-flash hv-cp-flash--bad"><strong>Atenție:</strong> există numere de raport duplicate: {{ implode(', ', array_keys($numberAudit['duplicates'])) }}. Exportul oficial trebuie verificat înainte de predare.</p>
        @endif
    </details>

    {{-- Control dupa data + numar + fotografie --}}
    <details class="hv-card" @if ($hasFilters) open @endif id="control-foto">
        <summary><strong>Control dosare după dată + număr pacient + fotografie</strong> · {{ $verificationSummary['total'] }} dosare scanate</summary>
        <p class="hv-cp-note">Acest control scanează toate dosarele din perioada selectată, inclusiv cele care NU sunt Finalizat/Operat. Așa găsești pacienți care au număr și fotografie, dar nu au intrat în raport.</p>
        <div class="hv-rp-kpis hv-rp-kpis--small">
            @foreach ([['in_report', 'În raport'], ['not_report', 'NU sunt în raport'], ['with_photo', 'Au fotografie'], ['photo_not_report', 'Au poză, dar NU sunt în raport'],
                ['unverified_photo', 'Poză neverificată vizual'], ['working_report_mismatch', 'Nr. intern ≠ nr. raport'], ['missing_number', 'Fără nr. raport'], ['duplicates', 'Cu nr. duplicat']] as [$key, $label])
                <div class="hv-rp-kpi"><strong>{{ $verificationSummary[$key] }}</strong><span>{{ $label }}</span></div>
            @endforeach
        </div>
        @if ($verificationSummary['photo_not_report'] > 0)
            <div class="hv-cp-flash hv-cp-flash--bad"><strong>Posibili pacienți nedeclarați: {{ $verificationSummary['photo_not_report'] }}.</strong> Există dosare cu fotografie în perioada selectată care nu apar în desfășurătorul oficial. Folosește filtrul „Au poză, dar NU sunt în raport” și verifică-le unul câte unul.</div>
        @endif
        @if ($canManage)
            <form method="post" action="{{ route('admin.campaign.report.bulk-photo') }}" class="hv-rp-split" onsubmit="return confirm('Confirmi că ai verificat vizual fotografiile și că numărul din poză coincide cu numărul oficial pentru toate dosarele vizate?')">
                {{ $hidden() }}<div><strong>Ai verificat deja toate pozele?</strong><p class="hv-cp-note">Confirmă dintr-un singur click toate dosarele cu fotografie și număr oficial. Dacă ai filtre active, se confirmă doar rezultatele filtrate.</p></div>
                <div><button class="hv-cp-btn hv-cp-btn--primary" type="submit">✓ {{ $hasFilters ? 'Confirmă toate rezultatele filtrate' : 'Confirmă toate pozele din perioada selectată' }}</button></div>
            </form>
        @endif
        <form method="get" class="hv-cp-filters" action="{{ CampaignReportController::pageUrl() }}#control-foto">
            <input type="hidden" name="month" value="{{ $month }}">
            <label class="hv-cp-field"><span class="hv-cp-label">Data exactă</span><input type="date" name="verify_date" value="{{ $filters['date'] }}"></label>
            <label class="hv-cp-field"><span class="hv-cp-label">Număr pacient exact</span><input type="text" inputmode="numeric" name="verify_number" value="{{ $filters['number'] }}" placeholder="ex. 154"></label>
            <label class="hv-cp-field"><span class="hv-cp-label">Nr. de la</span><input type="number" min="1" name="verify_from" value="{{ $filters['from'] }}"></label>
            <label class="hv-cp-field"><span class="hv-cp-label">Nr. până la</span><input type="number" min="1" name="verify_to" value="{{ $filters['to'] }}"></label>
            <label class="hv-cp-field"><span class="hv-cp-label">Arată</span><select name="verify_status">@foreach (CityReports::VERIFY_STATUSES as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="hv-cp-actions"><button class="hv-cp-btn hv-cp-btn--primary" type="submit">Aplică filtrul</button><a class="hv-cp-btn" href="{{ CampaignReportController::pageUrl(['month' => $month]) }}#control-foto">Resetează controlul</a></div>
        </form>
        <p><strong>{{ count($verification) }} dosare afișate</strong> <span class="hv-muted">· ordonate după dată și apoi numeric după numărul oficial al pacientului</span></p>
        @php($lastDate = null)
        @foreach ($verification as $v)
            @if ($v['_verify_date'] !== $lastDate)
                @php($lastDate = $v['_verify_date'])
                <h3 class="hv-rp-h">{{ $v['_verify_date'] ? $date($v['_verify_date']) : 'Fără dată' }} <span class="hv-muted">· sursa datei: {{ $v['_verify_date_source'] }}</span></h3>
            @endif
            <div class="hv-rp-verify {{ ($v['_verify_has_photo'] && ! $v['_verify_in_report']) ? 'is-danger' : (($v['_verify_photo_mismatch'] || $v['_verify_duplicate']) ? 'is-warning' : (($v['_verify_in_report'] && $v['_verify_photo_checked']) ? 'is-ok' : '')) }}">
                <div class="hv-rp-thumb">@if ($v['_photo_doc'])<a href="{{ route('admin.campaign.document', $v['_photo_doc']) }}" target="_blank" title="Deschide fotografia mare"><img src="{{ route('admin.campaign.document', $v['_photo_doc']) }}" alt="" loading="lazy"></a>@else<span>Fără<br>poză</span>@endif</div>
                <div class="hv-rp-verify-main">
                    <div><strong>{{ $v['nume_pacient'] ?: 'Pacient fără nume' }}</strong> <span class="hv-muted">{{ $v['nume_proprietar'] ?: '—' }} · {{ $v['cod_caz'] ?: '—' }}</span></div>
                    <div class="hv-rp-numbers"><span><small>Nr. raport</small><strong>{{ $v['_verify_report_number'] ?: '—' }}</strong></span><span><small>Nr. intern</small><strong>{{ $v['_verify_working_number'] ?: '—' }}</strong></span><span><small>Confirmat din poză</small><strong>{{ $v['_verify_photo_number'] ?: '—' }}</strong></span></div>
                    <div class="hv-muted">Status intervenție: <strong>{{ $v['status_interventie'] ?: '—' }}</strong> · În raport: <strong>{{ $v['_verify_in_report'] ? 'DA' : 'NU' }}</strong>
                        @if ($v['_verify_duplicate']) · <span class="hv-pill hv-pill--warn">NUMĂR DUPLICAT</span>@endif
                        @if ($v['_verify_working_report_mismatch']) · <span class="hv-pill hv-pill--warn">NR. INTERN ≠ RAPORT</span>@endif
                        @if ($v['_verify_photo_mismatch']) · <span class="hv-pill hv-pill--warn">NR. POZĂ ≠ RAPORT</span>@endif
                        @if ($v['_verify_has_photo'] && ! $v['_verify_in_report']) · <span class="hv-pill hv-pill--bad">POZĂ EXISTĂ, DAR NU E ÎN RAPORT</span>@endif</div>
                </div>
                <div class="hv-cp-actions">
                    <a class="hv-cp-btn" href="{{ CampaignController::caseUrl($v['id'], 'corectare') }}">Deschide dosarul</a>
                    @if ($canManage && $v['_verify_has_photo'] && $v['_verify_report_number'] !== '')
                        <form method="post" action="{{ route('admin.campaign.report.mark-photo', $v['id']) }}">{{ $hidden() }}<button class="hv-cp-btn {{ $v['_verify_photo_checked'] && ! $v['_verify_photo_mismatch'] ? '' : 'hv-cp-btn--primary' }}" type="submit">{{ $v['_verify_photo_checked'] && ! $v['_verify_photo_mismatch'] ? 'Reconfirmă nr. din poză' : '✓ Nr. din poză = '.$v['_verify_report_number'] }}</button></form>
                    @endif
                </div>
            </div>
        @endforeach
    </details>

    {{-- Registru numere --}}
    <details class="hv-card">
        <summary><strong>Registru numere pacienți 1 → {{ $registry['max'] }}</strong> · {{ $registry['verified'] }} poze verificate</summary>
        <p class="hv-cp-note">Lista este construită numai din dosarele cu fotografie verificată. Numărul confirmat din fotografie este reperul principal; lângă el vezi numărul intern, numărul din raport și data dosarului.</p>
        <div class="hv-rp-kpis hv-rp-kpis--small">
            @foreach ([[$registry['max'], 'Ultimul nr. verificat'], [$registry['verified'], 'Dosare cu poză verificată'], [$registry['aligned'], 'Deja aliniate'], [$registry['mismatch'], 'De aliniat'],
                [count($registry['missing']), 'Numere lipsă reale'], [count($registry['void_numbers']), 'Scoase din uz'], [count($registry['duplicates']), 'Numere duplicate']] as [$value, $label])
                <div class="hv-rp-kpi"><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
            @endforeach
        </div>
        @if ($registry['missing'])<p class="hv-cp-flash hv-cp-flash--warn"><strong>Goluri reale în seria 1 → {{ $registry['max'] }}:</strong> {{ CityReports::compactRanges($registry['missing']) }}</p>@endif
        @if ($registry['void_numbers'])<p class="hv-cp-note"><strong>Scoase din uz și ignorate ca goluri:</strong> {{ CityReports::compactRanges($registry['void_numbers']) }}</p>@endif
        @if ($registry['voidConflicts'])<p class="hv-cp-flash hv-cp-flash--bad"><strong>Conflict:</strong> unele numere marcate scoase din uz au totuși fotografii verificate ({{ implode(', ', array_keys($registry['voidConflicts'])) }}). Nu sunt modificate automat.</p>@endif
        @if ($registry['duplicates'])
            <div class="hv-cp-flash hv-cp-flash--bad"><strong>Atenție:</strong> există numere confirmate din fotografie folosite la mai multe dosare. Aceste numere NU sunt modificate automat.
                @foreach ($registry['duplicates'] as $n => $items)<div><strong>Nr. {{ $n }}</strong> · {{ collect($items)->map(fn ($r) => ($r['nume_pacient'] ?: 'Pacient').' / '.($r['nume_proprietar'] ?: '—').' / '.($r['_registry_date'] ?: 'fără dată'))->implode(' | ') }}</div>@endforeach</div>
        @endif
        <div class="hv-cp-actions">
            @if ($canValidate)
                <form method="post" action="{{ route('admin.campaign.report.align') }}" onsubmit="return confirm('Confirmi alinierea numărului intern și a numărului de raport după NUMĂRUL CONFIRMAT DIN FOTOGRAFIE pentru toate dosarele verificate? Numerele duplicate vor fi sărite automat.')">{{ $hidden() }}<button class="hv-cp-btn hv-cp-btn--primary" type="submit">↔ Aliniază intern + raport după poza verificată</button></form>
            @endif
            @if ($canExport)<a class="hv-cp-btn" href="{{ route('admin.campaign.report.registry-csv') }}">Descarcă lista CSV</a>@endif
        </div>
        <div style="max-height:32rem;overflow:auto">
            <table class="hv-table">
                <thead><tr><th>Nr.</th><th>Nr. poză</th><th>Nr. intern</th><th>Nr. raport</th><th>Data</th><th>Pacient</th><th>Cod dosar</th><th>Status</th></tr></thead>
                @foreach ($registry['matrix'] as $entry)
                    @continue($entry['void'] && ! $entry['rows'])
                    @if ($entry['missing'])
                        <tr><td><strong>{{ $entry['number'] }}</strong></td><td colspan="7"><span class="hv-pill hv-pill--bad">NUMĂR LIPSĂ REAL</span> nu există dosar cu fotografie verificată pentru acest număr și nu este marcat scos din uz.</td></tr>
                        @continue
                    @endif
                    @foreach ($entry['rows'] as $r)
                        <tr><td><strong>{{ $entry['number'] }}</strong></td><td><strong>{{ $r['_registry_photo_number'] }}</strong></td><td>{{ $r['_registry_internal_number'] ?: '—' }}</td><td>{{ $r['_registry_report_number'] ?: '—' }}</td>
                            <td>{{ $date($r['_registry_date']) }} <div class="hv-muted">{{ $r['_registry_date_source'] }}</div></td>
                            <td><strong>{{ $r['nume_pacient'] ?: '—' }}</strong><div class="hv-muted">{{ $r['nume_proprietar'] }}</div></td>
                            <td><a href="{{ CampaignController::caseUrl($r['id'], 'corectare') }}">{{ $r['cod_caz'] ?: '—' }}</a></td>
                            <td>@if ($entry['void'])<span class="hv-pill hv-pill--bad">CONFLICT: SCOS DIN UZ</span>@elseif ($entry['duplicate'])<span class="hv-pill hv-pill--bad">DUPLICAT</span>@elseif ($r['_registry_aligned'])<span class="hv-pill hv-pill--ok">ALINIAT</span>@else<span class="hv-pill hv-pill--warn">DE ALINIAT</span>@endif</td></tr>
                    @endforeach
                @endforeach
            </table>
        </div>
    </details>

    {{-- Reconciliere --}}
    <details class="hv-card">
        <summary><strong>Reconciliere iulie · august · septembrie 2026</strong></summary>
        <p class="hv-cp-note">Numerele sărite nu se șterg și nicio intervenție nu se mută automat într-o lună falsă. Numerele confirmate ca inexistente sunt marcate „scoase din uz”, iar cazurile neraportate rămân legate de data lor reală și apar separat la „de regularizat”.</p>
        @if ($canExport)
            <div class="hv-cp-actions">
                @foreach (CityReports::RECONCILIATION_MONTHS as $m => $label)<a class="hv-cp-btn" href="{{ route('admin.campaign.report.reconciliation-pdf', $m) }}">PDF HiKeVet - {{ $label }}</a>@endforeach
                <a class="hv-cp-btn" href="{{ route('admin.campaign.report.reconciliation-csv') }}">Reconcilierea pe 3 luni CSV</a>
            </div>
            <p class="hv-cp-note">PDF-urile sunt ordonate crescător după numărul confirmat din fotografie și includ data, numărul intern și numărul de raport, pentru comparația cu rapoartele trimise efectiv Primăriei.</p>
        @endif
        @if ($canValidate)
            <form method="post" action="{{ route('admin.campaign.report.reconciliation') }}">{{ $hidden() }}
                <label class="hv-cp-field"><span class="hv-cp-label">Numere scoase din uz / sărite intenționat</span><textarea name="registry[void_numbers]" rows="2">{{ $reconciliationSettings['void_numbers'] }}</textarea></label>
                <p class="hv-cp-note">Aceste numere nu mai apar ca „lipsă”. Dacă ulterior apare o fotografie verificată pe unul dintre ele, este marcat conflict.</p>
                <div class="hv-cp-grid">
                    @foreach (CityReports::RECONCILIATION_MONTHS as $m => $label)
                        @php($key = 'submitted_'.str_replace('-', '_', $m))
                        <label class="hv-cp-field"><span class="hv-cp-label">Numere deja trimise Primăriei · {{ $label }}</span><textarea name="registry[{{ $key }}]" rows="3" placeholder="ex. 1-40,42,44-90">{{ $reconciliationSettings[$key] ?? '' }}</textarea></label>
                    @endforeach
                </div>
                <button class="hv-cp-btn hv-cp-btn--primary" type="submit">Salvează reconcilierea</button>
            </form>
        @endif
        <div class="hv-cp-grid" style="margin-top:1rem">
            @foreach ($reconciliation as $m => $data)
                <div class="hv-rp-case"><strong>{{ $data['label'] }}</strong>
                    <div class="hv-muted">{{ $data['verified'] }} poze verificate · {{ $data['submitted_count'] }} nr. din raportul trimis ·
                        @if ($data['has_submitted_data'])<strong>{{ count($data['to_regularize']) }}</strong> de regularizat @else raport încă neîncărcat @endif</div>
                    @if ($data['to_regularize'])
                        <div style="margin-top:.4rem"><strong>Cazuri care nu apar în lista declarată:</strong>
                            @foreach (array_slice($data['to_regularize'], 0, 30) as $r)<div>Nr. {{ $r['_registry_photo_number'] }} · {{ $r['nume_pacient'] ?: 'Pacient' }} · {{ $date($r['_registry_date']) }}</div>@endforeach
                            @if (count($data['to_regularize']) > 30)<div class="hv-muted">+ încă {{ count($data['to_regularize']) - 30 }} cazuri</div>@endif</div>
                    @endif
                    @if ($data['void_conflicts'])<div class="hv-cp-flash hv-cp-flash--bad">Conflict: {{ count($data['void_conflicts']) }} dosare cu fotografie pe numere marcate scoase din uz.</div>@endif
                </div>
            @endforeach
        </div>
        <p class="hv-cp-note"><strong>Regula de siguranță:</strong> un caz din iulie sau august care nu a fost raportat nu devine automat caz de septembrie. Rămâne cu data reală și este marcat „DE REGULARIZAT”.</p>
    </details>

    {{-- Toate interventiile finalizate --}}
    <details class="hv-card" open>
        <summary><strong>Toate intervențiile finalizate - {{ $monthLabel }} - {{ count($scan) }}</strong></summary>
        <div style="overflow-x:auto">
            <table class="hv-table" style="font-size:.8rem">
                <thead><tr><th>Nr.</th><th>Cod caz</th><th>Nr. pacient</th><th>Data</th><th>Proprietar</th><th>Pacient</th><th>Specie</th><th>Procedură</th><th>Medic</th><th>CI</th><th>Foto</th><th>Consimț.</th><th>Integritate</th><th>Validat</th><th></th></tr></thead>
                @forelse ($scan as $i => $item)
                    @php($r = $item['row'])
                    <tr>
                        <td>{{ $i + 1 }}</td><td><strong>{{ $r['cod_caz'] }}</strong></td><td><strong>{{ CityChecks::reportNumber($r) ?: '-' }}</strong></td><td>{{ $r['data_interventie'] ?: ($r['data_raportare_effective'] ?? '') }}</td>
                        <td>{{ $r['nume_proprietar'] }}</td><td><strong>{{ $r['nume_pacient'] }}</strong></td><td>{{ $r['specie'] }} {{ $r['sex'] }}</td><td>{{ $r['procedura_efectuata'] ?: $r['procedura_programata'] }}</td><td>{{ $r['medic_operator'] }}</td>
                        @foreach ([$item['id_ok'], $item['photo_ok'], $item['consent_ok'], $item['integrity_ok'], $r['validat_pentru_primarie'] === 'Da'] as $ok)
                            <td><span class="hv-pill {{ $ok ? 'hv-pill--ok' : 'hv-pill--bad' }}">{{ $ok ? 'Da' : 'Nu' }}</span></td>
                        @endforeach
                        <td class="hv-cp-actions" style="flex-wrap:nowrap">
                            <a class="hv-cp-btn" href="{{ CampaignController::caseUrl($r['id'], 'interventie') }}">Deschide</a>
                            @if ($canExport)<form method="post" action="{{ route('admin.campaign.report.case-zip', $r['id']) }}">@csrf<button class="hv-cp-btn" type="submit">ZIP</button></form>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="hv-muted">Nu există intervenții finalizate pentru filtrul selectat.</td></tr>
                @endforelse
            </table>
        </div>
    </details>
</div>
</x-filament-panels::page>
