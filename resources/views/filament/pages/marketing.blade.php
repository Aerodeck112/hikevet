<x-filament-panels::page>
    <div class="hv-client">
        <dl class="hv-stats">
            @foreach ($stats as $label => $value)<div class="hv-stat"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
            <div class="hv-stat"><dt>Măsurare</dt><dd><span class="hv-pill {{ $gtmConfigured ? 'hv-pill--ok' : 'hv-pill--off' }}">{{ $gtmConfigured ? 'Configurată' : 'Neconfigurată' }}</span></dd></div>
        </dl>

        <form wire:submit="save">
            {{ $this->form }}
            <div style="margin-top:1.5rem"><x-filament::button type="submit">Salvează</x-filament::button></div>
        </form>

        <div class="hv-grid-2">
            <section class="hv-card">
                <h3>Evenimente trimise către dataLayer</h3>
                <p class="hv-muted" style="margin-bottom:.5rem">Fiecare eveniment se trimite o singură dată per acțiune. În GTM, creează câte un declanșator „Eveniment personalizat” cu aceste nume.</p>
                <table class="hv-table">
                    @foreach ($events as $name => $label)<tr><td><code>{{ $name }}</code></td><td>{{ $label }}</td></tr>@endforeach
                </table>
            </section>
            <section class="hv-card">
                <h3>Instrumente Google</h3>
                <p class="hv-muted" style="margin-bottom:.5rem">Platforma nu este conectată prin API la conturile Google: indexarea, conversiile și Core Web Vitals se urmăresc direct în instrumentele de mai jos.</p>
                <ul style="display:grid;gap:.4rem">
                    @foreach ($links as $label => $url)<li><a style="color:#0b5c3b;font-weight:600" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a></li>@endforeach
                </ul>
            </section>
        </div>

        @if ($missingDescriptions->isNotEmpty())
            <section class="hv-card"><h3>Pagini fără descriere</h3><p>{{ $missingDescriptions->implode(', ') }}</p></section>
        @endif
    </div>
</x-filament-panels::page>
