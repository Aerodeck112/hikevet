<x-filament-panels::page>
    <div class="hv-client">
        <section class="hv-card">
            <h3>Stare</h3>
            @if (! $connected)
                <p><span class="hv-pill hv-pill--warn">Așteptare export WordPress</span></p>
                <p style="margin-top:.6rem">Copia bazei de date WordPress nu este conectată. După ce exportul SQL este restaurat într-o bază separată, completează în <code>.env</code> valorile <code>WP_DB_HOST</code>, <code>WP_DB_DATABASE</code>, <code>WP_DB_USERNAME</code> și <code>WP_DB_PASSWORD</code>. Pașii compleți sunt în <code>docs/IMPORT_RUNBOOK.md</code>.</p>
            @else
                <p><span class="hv-pill hv-pill--ok">Sursă conectată</span> Prefix tabele: <code>{{ $preflight['prefix'] ?? '?' }}</code></p>
                @isset($preflight['tables'])
                    <table class="hv-table" style="margin-top:.75rem">
                        <thead><tr><th>Tabel</th><th>Rânduri</th><th>Coloane lipsă față de cod</th><th>Coloane în plus</th></tr></thead>
                        @foreach ($preflight['tables'] as $table => $info)
                            <tr><td>{{ $table }}</td><td>{{ $info['present'] ? $info['rows'] : 'lipsește' }}</td><td>{{ implode(', ', $info['missing_columns'] ?? []) ?: '–' }}</td><td>{{ implode(', ', $info['extra_columns'] ?? []) ?: '–' }}</td></tr>
                        @endforeach
                    </table>
                @endisset
            @endif
            <p class="hv-muted" style="margin-top:.75rem">Importul real se rulează doar din linia de comandă, după ce proba a fost verificată și aprobată: <code>php artisan hikevet:import-wordpress --execute</code>.</p>
        </section>

        <div class="hv-grid-2">
            <section class="hv-card">
                <h3>Surse de date așteptate</h3>
                <table class="hv-table">@foreach ($expected as $label => $value)<tr><th style="white-space:normal;width:30%">{{ $label }}</th><td>{{ $value }}</td></tr>@endforeach</table>
            </section>
            <section class="hv-card">
                <h3>Ce este deja în platformă</h3>
                <table class="hv-table">@foreach ($archive as $label => $value)<tr><td>{{ $label }}</td><td style="text-align:right;font-weight:700">{{ $value }}</td></tr>@endforeach</table>
            </section>
        </div>

        <section class="hv-card">
            <h3>Loturi de import</h3>
            <div class="hv-scroll"><table class="hv-table">
                <thead><tr><th>Lot</th><th>Tip</th><th>Mod</th><th>Stare</th><th>Totaluri</th><th>Erori</th><th>Lipsuri</th><th>Checksum sursă</th><th>Rulat la</th></tr></thead>
                <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td>#{{ $batch->id }}</td><td>{{ $batch->kind }}</td>
                        <td><span class="hv-pill {{ $batch->dry_run ? 'hv-pill--off' : 'hv-pill--ok' }}">{{ $batch->dry_run ? 'probă' : 'import real' }}</span></td>
                        <td>{{ $batch->status }}</td>
                        <td style="font-size:.8rem">@foreach (collect($batch->counts ?? [])->filter(fn ($v) => ! is_array($v)) as $k => $v){{ $k }}: <strong>{{ $v }}</strong>@if (! $loop->last), @endif @endforeach</td>
                        <td>{{ count($batch->errors ?? []) }}</td><td>{{ count($batch->missing ?? []) }}</td>
                        <td style="font-size:.75rem">{{ $batch->source_sha256 ? substr($batch->source_sha256, 0, 16).'…' : '–' }}</td>
                        <td>{{ $batch->started_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                    @foreach (array_slice($batch->errors ?? [], 0, 5) as $error)
                        <tr><td></td><td colspan="8" style="color:#b91c1c;font-size:.82rem">{{ json_encode($error, JSON_UNESCAPED_UNICODE) }}</td></tr>
                    @endforeach
                @empty
                    <tr><td colspan="9" class="hv-muted">Nu a fost rulat niciun import.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</x-filament-panels::page>
