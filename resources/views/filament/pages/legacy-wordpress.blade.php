<x-filament-panels::page>
    <div class="hv-client">
        <section class="hv-card">
            <h3>Ce este aici</h3>
            <p class="hv-muted">Copia completă a bazei de date a site-ului WordPress, tabel cu tabel, așa cum era la data copierii. Nimic nu se poate modifica.
                Datele au fost importate și în ecranele noi (clienți, contracte, Wallet, consimțăminte, sterilizări); aici rămâne originalul, inclusiv ce nu are ecran propriu.</p>
            @if ($tables->isEmpty())
                <p style="margin-top:.75rem"><strong>Arhiva este goală.</strong> Se completează la import: <code>php artisan hikevet:import-wordpress --execute --only=legacy</code></p>
            @else
                <dl class="hv-stats" style="margin-top:.75rem">
                    <div class="hv-stat"><dt>Tabele</dt><dd>{{ $tables->count() }}</dd></div>
                    <div class="hv-stat"><dt>Rânduri</dt><dd>{{ number_format($totalRows, 0, ',', '.') }}</dd></div>
                    <div class="hv-stat"><dt>Copiat la</dt><dd style="font-size:1.1rem">{{ $tables->max('snapshotted_at')?->format('d.m.Y H:i') }}</dd></div>
                </dl>
            @endif
        </section>

        @if ($files->isNotEmpty())
            <section class="hv-card">
                <h3>Fișiere originale</h3>
                <table class="hv-table">
                    @foreach ($files as $file)
                        <tr>
                            <td>{{ $file->label }}</td>
                            <td>{{ number_format($file->bytes / 1048576, 1, ',', '.') }} MB</td>
                            <td class="hv-muted" style="word-break:break-all;font-size:.75rem">SHA-256 {{ $file->sha256 }}</td>
                            <td>@if ($canExport)<x-filament::button size="sm" color="gray" wire:click="downloadFile({{ $file->id }})">Descarcă</x-filament::button>@endif</td>
                        </tr>
                    @endforeach
                </table>
            </section>
        @endif

        @if ($tables->isNotEmpty())
            <section class="hv-card">
                <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end">
                    <label style="flex:1;min-width:16rem">
                        <span class="hv-muted" style="display:block;font-size:.8rem">Tabel</span>
                        <select wire:model.live="table" class="fi-select-input" style="width:100%;padding:.5rem;border:1px solid #d4d4d8;border-radius:.5rem">
                            <option value="">Alege un tabel…</option>
                            @foreach ($tables as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} ({{ number_format($t->row_count, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($current)
                        <form wire:submit="runSearch(0)" style="display:flex;gap:.5rem;flex:1;min-width:16rem">
                            <input type="search" wire:model="term" placeholder="Caută text în acest tabel" style="flex:1;padding:.5rem;border:1px solid #d4d4d8;border-radius:.5rem">
                            <x-filament::button type="submit">Caută</x-filament::button>
                        </form>
                        @if ($canExport)<x-filament::button color="gray" wire:click="exportCsv">Export CSV</x-filament::button>@endif
                    @endif
                </div>
            </section>
        @endif

        @if ($current)
            <section class="hv-card">
                <h3>{{ $current->name }}</h3>
                <p class="hv-muted" style="font-size:.8rem">{{ count($current->columns) }} coloane · {{ number_format($current->row_count, 0, ',', '.') }} rânduri · amprentă {{ substr((string) $current->sha256, 0, 16) }}…</p>
                @if ($search)
                    <p style="margin:.5rem 0">Rezultate pentru „{{ $search['term'] }}”: {{ count($search['matches']) }} (verificate {{ $search['scanned'] }} rânduri de la rândul {{ $search['from'] + 1 }})
                        @if ($search['next']) · <a href="#" wire:click.prevent="runSearch({{ $search['next'] }})">continuă căutarea</a>@endif
                        · <a href="#" wire:click.prevent="clearSearch">înapoi la listă</a></p>
                @endif
                <div style="overflow-x:auto">
                    <table class="hv-table" style="font-size:.8rem">
                        <thead><tr><th>#</th>@foreach (array_slice($current->columns, 0, 8) as $column)<th>{{ $column }}</th>@endforeach<th></th></tr></thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>{{ $row['ordinal'] }}</td>
                                    @foreach (array_slice($current->columns, 0, 8) as $column)
                                        @php($v = $row['values'][$column] ?? null)
                                        <td style="max-width:16rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ \Illuminate\Support\Str::limit(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), 80) }}</td>
                                    @endforeach
                                    <td>
                                        <details>
                                            <summary style="cursor:pointer">toate</summary>
                                            <table class="hv-table" style="font-size:.75rem;margin-top:.25rem">
                                                @foreach ($current->columns as $column)
                                                    @php($v = $row['values'][$column] ?? null)
                                                    <tr><th style="white-space:nowrap">{{ $column }}</th><td style="white-space:pre-wrap;word-break:break-word">{{ is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}</td></tr>
                                                @endforeach
                                            </table>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="hv-muted">Niciun rând.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if (! $search && $pages > 1)
                    <div style="display:flex;gap:.5rem;align-items:center;margin-top:.75rem">
                        <x-filament::button size="sm" color="gray" wire:click="$set('page', {{ max(1, $page - 1) }})" :disabled="$page <= 1">Înapoi</x-filament::button>
                        <span class="hv-muted">Pagina {{ $page }} din {{ $pages }}</span>
                        <x-filament::button size="sm" color="gray" wire:click="$set('page', {{ min($pages, $page + 1) }})" :disabled="$page >= $pages">Înainte</x-filament::button>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
