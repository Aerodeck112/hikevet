<x-filament-panels::page>
    <div class="hv-client">
        <dl class="hv-stats">
            <div class="hv-stat"><dt>Versiune platformă</dt><dd>{{ $version }}</dd></div>
            <div class="hv-stat"><dt>PHP</dt><dd style="font-size:1.2rem">{{ $php }}</dd></div>
            <div class="hv-stat"><dt>Laravel</dt><dd style="font-size:1.2rem">{{ $laravel }}</dd></div>
        </dl>

        @if ($pending)
            <section class="hv-card" style="border-color:#21b073">
                <h3>Pachet verificat: versiunea {{ $pending['version'] }}</h3>
                <p>{{ $pending['files'] }} fișiere · {{ number_format($pending['bytes'] / 1048576, 1, ',', '.') }} MB · {{ $pending['has_migrations'] ? 'conține migrări ale bazei de date' : 'fără migrări' }}</p>
                <p class="hv-muted" style="word-break:break-all">SHA-256: {{ $pending['sha256'] }}</p>
                @if ($pending['changelog'])<p style="margin-top:.5rem"><strong>Modificări:</strong> {{ $pending['changelog'] }}</p>@endif
                <table class="hv-table" style="margin-top:.75rem">
                    @foreach ($pending['checks'] as $check)
                        <tr><td>{{ $check['label'] }}</td><td><span class="hv-pill {{ $check['ok'] ? 'hv-pill--ok' : 'hv-pill--bad' }}">{{ $check['ok'] ? 'în regulă' : 'blochează' }}</span></td><td>{{ $check['detail'] }}</td></tr>
                    @endforeach
                </table>
                <p class="hv-muted" style="margin-top:.75rem">La instalare: backup al bazei de date și al fișierelor înlocuite, mod mentenanță, copiere, migrări, golire cache, verificare, revenire online. La eroare, versiunea anterioară este pusă la loc automat.</p>
                <div style="display:flex;gap:.5rem;margin-top:1rem">
                    <x-filament::button wire:click="install" wire:confirm="Instalezi versiunea {{ $pending['version'] }}? Site-ul intră în mentenanță câteva momente." :disabled="! $pending['ok']" wire:loading.attr="disabled">Instalează actualizarea</x-filament::button>
                    <x-filament::button color="gray" wire:click="discard">Renunță</x-filament::button>
                </div>
            </section>
        @endif

        <div class="hv-grid-2">
            <section class="hv-card">
                <h3>Sănătatea sistemului</h3>
                <table class="hv-table">
                    @foreach ($health as $item)
                        <tr><td>{{ $item['label'] }}</td><td><span class="hv-pill {{ $item['ok'] ? 'hv-pill--ok' : 'hv-pill--warn' }}">{{ $item['ok'] ? 'în regulă' : 'de verificat' }}</span></td><td>{{ $item['detail'] }}</td></tr>
                    @endforeach
                </table>
            </section>
            <section class="hv-card">
                <h3>Backupuri</h3>
                <table class="hv-table">
                    @forelse ($backups as $backup)
                        <tr><td style="word-break:break-all">{{ $backup['name'] }}</td><td>{{ date('d.m.Y H:i', $backup['created']) }}</td><td style="text-align:right">{{ round($backup['bytes'] / 1024) }} KB</td></tr>
                    @empty
                        <tr><td class="hv-muted">Niciun backup încă.</td></tr>
                    @endforelse
                </table>
                <p class="hv-muted" style="margin-top:.5rem">Backupurile sunt criptate și stau în <code>storage/app/backups</code>, în afara site-ului public. Restaurarea se face din linia de comandă.</p>
            </section>
        </div>

        <section class="hv-card">
            <h3>Istoricul actualizărilor</h3>
            @forelse ($runs as $run)
                <details style="padding:.5rem 0;border-bottom:1px solid rgb(244 244 245)">
                    <summary style="cursor:pointer">{{ $run->from_version }} → {{ $run->to_version }} · {{ $run->started_at?->format('d.m.Y H:i') }} ·
                        <span class="hv-pill {{ $run->status === 'succeeded' ? 'hv-pill--ok' : 'hv-pill--bad' }}">{{ ['succeeded' => 'instalată', 'rolled_back' => 'anulată, versiunea veche restaurată', 'failed' => 'eșuată', 'running' => 'în curs'][$run->status] ?? $run->status }}</span></summary>
                    <pre style="font-size:.78rem;white-space:pre-wrap;margin-top:.5rem">{{ $run->log }}</pre>
                </details>
            @empty
                <p class="hv-muted">Nicio actualizare instalată din panou.</p>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
