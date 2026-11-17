<x-filament-panels::page>
<div class="hv-cp hv-client">
    @include('filament.campaign._stopped')
    @if (session('campaign_message'))<div class="hv-cp-flash hv-cp-flash--ok">{{ session('campaign_message') }}</div>@endif
    @if (session('campaign_warning'))<div class="hv-cp-flash hv-cp-flash--warn">{{ session('campaign_warning') }}</div>@endif
    @if ($errors->any())<div class="hv-cp-flash hv-cp-flash--bad">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="hv-cp-actions">
        <a class="hv-cp-btn" href="?week={{ $start->copy()->subWeek()->toDateString() }}">← Săptămâna anterioară</a>
        <a class="hv-cp-btn" href="?week={{ now()->toDateString() }}">Săptămâna curentă</a>
        <a class="hv-cp-btn" href="?week={{ $start->copy()->addWeek()->toDateString() }}">Săptămâna următoare →</a>
        <strong>{{ $start->format('d.m') }} – {{ $start->copy()->addDays(6)->format('d.m.Y') }}</strong>
        @if ($canEdit)<a class="hv-cp-btn hv-cp-btn--primary" href="?week={{ $start->toDateString() }}&new=1">+ Programare nouă</a>@endif
    </div>

    @if ($showNew && $canEdit)
        @include('filament.campaign.booking-form')
    @endif

    @if ($editing)
        @include('filament.campaign.appointment-editor', ['a' => $editing])
    @endif

    <section class="hv-card" style="overflow-x:auto">
        <table class="hv-table" style="font-size:.78rem;table-layout:fixed;min-width:56rem">
            <thead><tr><th style="width:3.5rem"></th>
                @foreach ($days as $day)
                    @php $info = $dayInfo[$day->format('Y-m-d')]; @endphp
                    <th style="{{ $day->isToday() ? 'background:#ecfdf5' : '' }}">{{ ucfirst($day->locale('ro')->translatedFormat('D d.m')) }}
                        <div class="hv-muted" style="font-weight:400">{{ $info['count'] }} programări · liber de la {{ $info['next_free'] ?: '—' }}</div></th>
                @endforeach
            </tr></thead>
            <tbody>
            @foreach ($times as $time)
                <tr>
                    <th class="hv-muted">{{ $time }}</th>
                    @foreach ($days as $day)
                        @php
                            $cellItems = ($byDay[$day->format('Y-m-d')] ?? collect())->filter(function ($a) use ($time) { $s = \App\Services\Campaign\CaseFields::minutes($a->start_time); $t = \App\Services\Campaign\CaseFields::minutes($time); return $s >= $t && $s < $t + 30; });
                        @endphp
                        <td style="vertical-align:top;padding:.2rem">
                            @foreach ($cellItems as $a)
                                <a href="?week={{ $start->toDateString() }}&appointment={{ $a->id }}" style="display:block;margin-bottom:.2rem;padding:.25rem .35rem;border-radius:.35rem;text-decoration:none;color:inherit;
                                    background:{{ ['Anulat' => '#f4f4f5', 'Neprezentat' => '#fef2f2', 'Finalizat' => '#ecfdf5', 'Prezentat' => '#eff6ff', 'Confirmat' => '#f0fdf4'][$a->status] ?? '#fff7ed' }};
                                    {{ $a->status === 'Anulat' ? 'text-decoration:line-through;opacity:.6' : '' }}">
                                    <strong>{{ $a->start_time }}</strong> {{ \Illuminate\Support\Str::limit($a->label(), 34) }}
                                    <div class="hv-muted">{{ $a->appointment_type }} · {{ $a->status }}</div>
                                </a>
                            @endforeach
                            @if ($canEdit)<a href="?week={{ $start->toDateString() }}&date={{ $day->format('Y-m-d') }}&time={{ $time }}" class="hv-muted" title="Programare la {{ $time }}" style="text-decoration:none">+</a>@endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="hv-card">
        <h3>Agenda săptămânii</h3>
        @forelse ($byDay as $dayKey => $list)
            <h4 style="margin-top:.75rem">{{ ucfirst(\Illuminate\Support\Carbon::parse($dayKey)->locale('ro')->translatedFormat('l, d F')) }}</h4>
            <table class="hv-table" style="font-size:.85rem">
                @foreach ($list as $a)
                    <tr>
                        <td style="white-space:nowrap">{{ $a->start_time }}–{{ $a->endTime() }}</td>
                        <td>{{ $a->label() }}<div class="hv-muted">{{ $a->client_phone }}</div></td>
                        <td>{{ $a->appointment_type }}</td>
                        <td><span class="hv-pill {{ in_array($a->status, ['Anulat', 'Neprezentat'], true) ? 'hv-pill--bad' : ($a->status === 'Finalizat' ? 'hv-pill--ok' : '') }}">{{ $a->status }}</span></td>
                        <td style="text-align:right" class="hv-cp-actions">
                            @if ($a->sterilization_case_id)<a class="hv-cp-btn" href="{{ url('/admin/campanie/dosar') }}?case={{ $a->sterilization_case_id }}">Dosar</a>@endif
                            @if ($canEdit && $a->sterilization_case_id && ! in_array($a->status, ['Anulat', 'Finalizat'], true))
                                <form method="post" action="{{ route('admin.campaign.appointments.consultation', $a) }}">@csrf<button class="hv-cp-btn hv-cp-btn--primary" type="submit">Începe consultația</button></form>
                            @endif
                            <a class="hv-cp-btn" href="?week={{ $start->toDateString() }}&appointment={{ $a->id }}">Editează / anulează</a>
                        </td>
                    </tr>
                @endforeach
            </table>
        @empty
            <p class="hv-muted">Nicio programare în această săptămână.</p>
        @endforelse
    </section>
</div>
</x-filament-panels::page>
