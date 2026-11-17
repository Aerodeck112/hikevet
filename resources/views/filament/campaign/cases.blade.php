<x-filament-panels::page>
<div class="hv-cp hv-client">
    @include('filament.campaign._stopped')
    @if (session('campaign_message'))<div class="hv-cp-flash hv-cp-flash--ok">{{ session('campaign_message') }}</div>@endif
    <div class="hv-cp-actions">
        @if ($canEdit)
            <a class="hv-cp-btn hv-cp-btn--primary" href="{{ url('/admin/campanie/programari') }}?new=1">+ Programare nouă</a>
            <a class="hv-cp-btn" href="{{ url('/admin/campanie/dosar') }}">Caz nou (fără calendar)</a>
        @endif
        <span class="hv-muted">{{ $page->total() }} din {{ $total }} dosare</span>
    </div>

    <form method="get" class="hv-card">
        <div class="hv-cp-filters">
            <div class="hv-cp-field" style="grid-column:span 2"><label>Căutare</label><input type="search" name="q" value="{{ $filters['q'] }}" placeholder="cod, nr. pacient, proprietar, telefon, email, pacient, microcip, rasă"></div>
            <div class="hv-cp-field"><label>Status dosar</label><select name="status"><option value="">Toate</option><option value="__primarie_validat__" @selected($filters['status'] === '__primarie_validat__')>Validate de Primărie</option><option value="__primarie_nevalidat__" @selected($filters['status'] === '__primarie_nevalidat__')>Nevalidate de Primărie</option>@foreach ($choices['status_caz'] as $s)<option @selected($filters['status'] === $s)>{{ $s }}</option>@endforeach</select></div>
            <div class="hv-cp-field"><label>Perioadă după</label><select name="date_field">@foreach (['data_programare' => 'Data programării', 'data_prezentare' => 'Data prezentării', 'data_interventie' => 'Data intervenției', 'data_externare' => 'Data externării', 'live_created_at' => 'Creat în platformă', 'live_updated_at' => 'Ultima actualizare'] as $k => $l)<option value="{{ $k }}" @selected($filters['date_field'] === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="hv-cp-field"><label>De la</label><input type="date" name="date_from" value="{{ $filters['date_from'] }}"></div>
            <div class="hv-cp-field"><label>Până la</label><input type="date" name="date_to" value="{{ $filters['date_to'] }}"></div>
            <div class="hv-cp-field"><label>Specie</label><select name="species"><option value="">Toate</option>@foreach ($choices['specie'] as $v)<option @selected($filters['species'] === $v)>{{ $v }}</option>@endforeach</select></div>
            <div class="hv-cp-field"><label>Sex</label><select name="sex"><option value="">Toate</option>@foreach ($choices['sex'] as $v)<option @selected($filters['sex'] === $v)>{{ $v }}</option>@endforeach</select></div>
            <div class="hv-cp-field"><label>Procedură</label><input type="text" name="procedure" value="{{ $filters['procedure'] }}"></div>
            <div class="hv-cp-field"><label>Medic</label><select name="doctor"><option value="">Toți</option>@foreach ($doctors as $v)<option @selected($filters['doctor'] === $v)>{{ $v }}</option>@endforeach</select></div>
            <div class="hv-cp-field"><label>Intervenție</label><select name="intervention"><option value="">Toate</option><option value="done" @selected($filters['intervention'] === 'done')>Finalizată</option><option value="pending" @selected($filters['intervention'] === 'pending')>Nefinalizată</option></select></div>
            <div class="hv-cp-field"><label>Fotografie</label><select name="photo"><option value="">Oricum</option><option value="yes" @selected($filters['photo'] === 'yes')>Cu fotografie</option><option value="no" @selected($filters['photo'] === 'no')>Fără fotografie</option></select></div>
            <div class="hv-cp-field"><label>CI scanat</label><select name="ci"><option value="">Oricum</option><option value="yes" @selected($filters['ci'] === 'yes')>Cu CI</option><option value="no" @selected($filters['ci'] === 'no')>Fără CI</option></select></div>
            <div class="hv-cp-field"><label>Consimțământ</label><select name="consent"><option value="">Oricum</option><option value="signed" @selected($filters['consent'] === 'signed')>Semnat</option><option value="unsigned" @selected($filters['consent'] === 'unsigned')>Nesemnat</option></select></div>
            <div class="hv-cp-field"><label>Email final</label><select name="postop_email"><option value="">Oricum</option><option value="sent" @selected($filters['postop_email'] === 'sent')>Trimis</option><option value="not_sent" @selected($filters['postop_email'] === 'not_sent')>Netrimis</option></select></div>
            <div class="hv-cp-field"><label>Completitudine</label><select name="completeness"><option value="">Oricum</option><option value="complete" @selected($filters['completeness'] === 'complete')>Complet</option><option value="incomplete" @selected($filters['completeness'] === 'incomplete')>Incomplet</option></select></div>
            <div class="hv-cp-field"><label>Vizită</label><select name="multi_visit"><option value="">Oricum</option><option value="yes" @selected($filters['multi_visit'] === 'yes')>Mai mulți pacienți</option><option value="no" @selected($filters['multi_visit'] === 'no')>Un pacient</option></select></div>
            <div class="hv-cp-field"><label>Ordonare</label><select name="sort">@foreach (['updated_desc' => 'Ultima actualizare', 'appointment_desc' => 'Programare (recente)', 'appointment_asc' => 'Programare (vechi)', 'intervention_desc' => 'Intervenție (recente)', 'owner_asc' => 'Proprietar A–Z', 'patient_asc' => 'Pacient A–Z', 'code_desc' => 'Cod (recente)', 'code_asc' => 'Cod (vechi)'] as $k => $l)<option value="{{ $k }}" @selected($filters['sort'] === $k)>{{ $l }}</option>@endforeach</select></div>
        </div>
        <div class="hv-cp-actions" style="margin-top:.75rem">
            <button class="hv-cp-btn hv-cp-btn--primary" type="submit">Filtrează</button>
            <a class="hv-cp-btn" href="?">Resetează</a>
            <a class="hv-cp-btn" href="?date_field=data_programare&date_from={{ now()->toDateString() }}&date_to={{ now()->toDateString() }}">Astăzi</a>
            <a class="hv-cp-btn" href="?date_field=data_programare&date_from={{ now()->subDay()->toDateString() }}&date_to={{ now()->subDay()->toDateString() }}">Ieri</a>
            <a class="hv-cp-btn" href="?date_field=data_programare&date_from={{ now()->subDays(6)->toDateString() }}&date_to={{ now()->toDateString() }}">Ultimele 7 zile</a>
            <a class="hv-cp-btn" href="?date_field=data_programare&date_from={{ now()->startOfMonth()->toDateString() }}&date_to={{ now()->endOfMonth()->toDateString() }}">Luna aceasta</a>
        </div>
    </form>

    <section class="hv-card" style="overflow-x:auto">
        <table class="hv-table" style="font-size:.85rem">
            <thead><tr><th>Cod / nr.</th><th>Proprietar</th><th>Pacient</th><th>Vizită</th><th>Programare / intervenție</th><th>Documente</th><th>Completitudine</th><th>Primărie</th><th>Actualizat</th></tr></thead>
            <tbody>
            @forelse ($rows as $item)
                @php $r = $item['row']; $c = $item['checklist']; @endphp
                <tr>
                    <td><a href="{{ url('/admin/campanie/dosar') }}?case={{ $r['id'] }}"><strong>{{ $r['cod_caz'] }}</strong></a><div class="hv-muted">nr. {{ $r['numar_pacient'] ?: '—' }}</div></td>
                    <td>{{ $r['nume_proprietar'] ?: '-' }}<div class="hv-muted">{{ $r['telefon'] }}</div></td>
                    <td>{{ $r['nume_pacient'] ?: '-' }}<div class="hv-muted">{{ trim($r['specie'].' / '.$r['sex'], ' /') }}</div></td>
                    <td>@if ($item['visit_size'] > 1)<span class="hv-pill">{{ $item['visit_size'] }} pacienți</span>@else<span class="hv-muted">1</span>@endif</td>
                    <td>{{ $r['data_programare'] ? \Illuminate\Support\Carbon::parse($r['data_programare'])->format('d.m.Y') : '-' }} {{ $r['ora_programare'] }}
                        <div class="hv-muted">{{ $r['status_caz'] }}{{ in_array($r['status_interventie'], ['Finalizat', 'Operat'], true) ? ' · operat '.($r['data_interventie'] ? \Illuminate\Support\Carbon::parse($r['data_interventie'])->format('d.m.Y') : '') : '' }}</div></td>
                    <td><span class="hv-pill {{ $item['consent_signed'] ? 'hv-pill--ok' : 'hv-pill--warn' }}">{{ $item['consent_signed'] ? 'Semnat' : 'Nesemnat' }}</span></td>
                    <td style="min-width:11rem">
                        @if ($c['ready'])
                            <span class="hv-pill hv-pill--ok">✓ Dosar complet</span>
                        @else
                            <div class="hv-cp-progress"><span style="width:{{ $c['percent'] }}%"></span></div>
                            <details><summary style="cursor:pointer">{{ $c['done'] }}/{{ $c['total'] }} · <strong>{{ $c['missing'] }} lipsă</strong></summary>
                                <ul style="margin:.3rem 0 0 1rem;list-style:disc">@foreach ($c['checks'] as $check)@unless ($check['ok'])<li><a href="{{ url('/admin/campanie/dosar') }}?case={{ $r['id'] }}&tab={{ $check['tab'] }}&focus={{ $check['focus'] }}">{{ $check['label'] }} →</a></li>@endunless @endforeach</ul>
                            </details>
                        @endif
                    </td>
                    <td>@if ($r['validat_pentru_primarie'] === 'Da' || $r['status_caz'] === 'Validat primarie')<span class="hv-pill hv-pill--ok">Validat</span>@else<span class="hv-pill hv-pill--off">Nevalidat</span>@endif</td>
                    <td class="hv-muted" style="white-space:nowrap">{{ $item['model']->live_updated_at?->format('d.m.Y H:i') ?? ($item['model']->imported_at ? 'importat' : '-') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="hv-muted">Niciun dosar pentru filtrele alese.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($page->lastPage() > 1)
            <div class="hv-cp-actions" style="margin-top:.75rem">
                @if ($page->currentPage() > 1)<a class="hv-cp-btn" href="?{{ http_build_query($filters + ['p' => $page->currentPage() - 1]) }}">← Înapoi</a>@endif
                <span class="hv-muted">Pagina {{ $page->currentPage() }} din {{ $page->lastPage() }}</span>
                @if ($page->hasMorePages())<a class="hv-cp-btn" href="?{{ http_build_query($filters + ['p' => $page->currentPage() + 1]) }}">Înainte →</a>@endif
            </div>
        @endif
    </section>
</div>
</x-filament-panels::page>
