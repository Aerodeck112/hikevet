<x-filament-panels::page>
    <div class="hv-client">
        <section class="hv-card">
            <h3>Intervenții finalizate pe lună</h3>
            <p class="hv-muted" style="margin-bottom:.75rem">Se numără dosarele cu starea Finalizat sau Operat, după data intervenției din sursă. Datele lipsă sau invalide nu sunt înlocuite cu alte date.</p>
            @if (empty($months))
                <p class="hv-muted">Arhiva este goală. Dosarele apar aici după importul exportului WordPress.</p>
            @else
                <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                    @foreach ($months as $key => $count)
                        @if (preg_match('/^\d{4}-\d{2}$/', $key))
                            <a href="?month={{ $key }}" class="hv-pill {{ $month === $key ? 'hv-pill--ok' : '' }}" style="padding:.45rem .9rem;text-decoration:none">{{ $key }} · {{ $count }}</a>
                        @else
                            <span class="hv-pill hv-pill--warn" style="padding:.45rem .9rem">{{ $key }} · {{ $count }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        @if ($report)
            <div class="hv-grid-2">
                <section class="hv-card">
                    <h3>Centralizator {{ $report['month'] }}</h3>
                    <table class="hv-table">
                        @foreach ($report['summary'] as $label => $count)
                            <tr><td>{{ $label }}</td><td style="text-align:right;font-weight:700">{{ $count }}</td></tr>
                        @endforeach
                        <tr><th>Total</th><th style="text-align:right">{{ $report['total'] }}</th></tr>
                    </table>
                    <p class="hv-muted" style="margin-top:.5rem">{{ $report['with_missing'] }} dosare au date lipsă în sursă. {{ $report['already_reported'] }} dosare figurează în lista de referință ca raportate în altă lună.</p>
                </section>
                <section class="hv-card">
                    <h3>Reconciliere cu exportul vechi</h3>
                    <p>Compară totalul de mai sus cu raportul trimis Primăriei pentru aceeași lună. Dacă diferă, cauza se caută în dosarele cu date lipsă și în lista de coduri raportate anterior; arhiva nu ajustează nimic automat.</p>
                    @if ($canExport)
                        <x-filament::button wire:click="exportCsv" icon="heroicon-o-arrow-down-tray" style="margin-top:.75rem">Desfășurător CSV</x-filament::button>
                    @endif
                </section>
            </div>
            <section class="hv-card">
                <h3>Desfășurător {{ $report['month'] }}</h3>
                <div class="hv-scroll"><table class="hv-table">
                    <thead><tr><th>Nr. pacient</th><th>Cod dosar</th><th>Data</th><th>Proprietar</th><th>Localitate</th><th>Pacient</th><th>Specie / sex</th><th>Procedură</th><th>Medic</th></tr></thead>
                    <tbody>
                    @foreach ($report['cases'] as $case)
                        <tr><td>{{ $case->numar_pacient ?: 'lipsă' }}</td>
                            <td><a style="color:#0b5c3b;font-weight:600" href="{{ \App\Filament\Resources\SterilizationCases\SterilizationCaseResource::getUrl('view', ['record' => $case->id]) }}">{{ $case->cod_caz }}</a></td>
                            <td>{{ $case->data_interventie?->format('d.m.Y') }}</td><td>{{ $case->nume_proprietar }}</td><td>{{ $case->localitate }}</td><td>{{ $case->nume_pacient }}</td>
                            <td>{{ $case->specie }} / {{ $case->sex }}</td><td>{{ $case->procedura_efectuata ?: 'lipsă' }}</td><td>{{ $case->medic_operator ?: 'lipsă' }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
