<x-filament-panels::page>
    <div class="hv-client">
        <p class="hv-pill hv-pill--off" style="padding:.5rem 1rem">Dosar istoric, doar pentru citire. Valorile sunt cele din sistemul vechi, nemodificate. ID original {{ $case->source_id }} · importat la {{ $case->imported_at?->format('d.m.Y H:i') }} · amprentă rând {{ substr($case->row_sha256, 0, 16) }}…</p>
        @if ($case->missing_in_source)
            <p class="hv-pill hv-pill--warn" style="padding:.5rem 1rem">Lipsă în sursă: {{ implode(', ', $case->missing_in_source) }}</p>
        @endif

        <dl class="hv-stats">
            <div class="hv-stat"><dt>Pacient</dt><dd style="font-size:1.2rem">{{ $case->nume_pacient ?: 'Lipsă în sursă' }}</dd></div>
            <div class="hv-stat"><dt>Număr pacient</dt><dd>{{ $case->numar_pacient ?: '–' }}</dd></div>
            <div class="hv-stat"><dt>Intervenție</dt><dd style="font-size:1.2rem">{{ $case->data_interventie?->format('d.m.Y') ?? 'Lipsă în sursă' }}</dd></div>
            <div class="hv-stat"><dt>Stare</dt><dd style="font-size:1.1rem">{{ $case->status_interventie ?: ($case->status_caz ?: '–') }}</dd></div>
            <div class="hv-stat"><dt>Raportat în</dt><dd style="font-size:1.2rem">{{ $case->reported_month ?: '–' }}</dd></div>
        </dl>

        @if ($visit->isNotEmpty())
            <p class="hv-muted">Aceeași vizită (grup {{ $case->visit_group_id }}):
                @foreach ($visit as $other)<a style="color:#0b5c3b;font-weight:600" href="{{ \App\Filament\Resources\SterilizationCases\SterilizationCaseResource::getUrl('view', ['record' => $other->id]) }}">{{ $other->cod_caz }} {{ $other->nume_pacient }}</a>@if (! $loop->last), @endif @endforeach
            </p>
        @endif

        <div class="hv-grid-2">
            @foreach ($sections as $title => $fields)
                <section class="hv-card">
                    <h3>{{ $title }}</h3>
                    <table class="hv-table">
                        @foreach ($fields as $field => $value)
                            <tr><th style="width:40%;font-weight:500;white-space:normal">{{ str_replace('_', ' ', $field) }}</th>
                                <td @if (str_starts_with($value, 'Lipsă în sursă')) style="color:#b45309;font-weight:600" @endif>{{ $value }}</td></tr>
                        @endforeach
                    </table>
                </section>
            @endforeach

            <section class="hv-card">
                <h3>Documente și fotografii</h3>
                <table class="hv-table">
                    <thead><tr><th>Tip</th><th>Stare</th><th>Amprentă SHA-256</th></tr></thead>
                    @forelse ($documents as $document)
                        <tr><td>{{ str_replace('_', ' ', $document->kind) }}</td>
                            <td>@if ($document->is_missing)<span class="hv-pill hv-pill--warn">Lipsă în sursă</span>@else<span class="hv-pill hv-pill--ok">Păstrat</span>@endif</td>
                            <td style="font-size:.78rem;word-break:break-all">{{ $document->sha256 ?: '–' }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="hv-muted">Niciun document referit în dosar.</td></tr>
                    @endforelse
                </table>
            </section>

            @if ($appointments->isNotEmpty())
            <section class="hv-card">
                <h3>Programări istorice ale campaniei</h3>
                <table class="hv-table">
                    @foreach ($appointments as $a)
                        <tr><td>{{ $a->appointment_date?->format('d.m.Y') }} {{ $a->start_time }}</td><td>{{ $a->appointment_type }}</td><td>{{ $a->status }}</td></tr>
                    @endforeach
                </table>
            </section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
