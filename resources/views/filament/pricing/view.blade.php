<x-filament-panels::page>
    <div class="hv-client">
        <p class="hv-pill hv-pill--off" style="padding:.5rem 1rem">Sursă: {{ $list->source }}. Lista nu se editează; contractele semnate o folosesc ca referință. {{ $list->notes }}</p>
        <section class="hv-card">
            <h3>Categorii și tarife</h3>
            <div class="hv-scroll"><table class="hv-table">
                <thead><tr><th>Specie</th><th>Categorie</th><th>Interval kg</th><th>Lunar</th><th>6 luni</th><th>12 luni</th><th>Total servicii, preț normal</th><th>Total servicii, preț abonament</th></tr></thead>
                @foreach ($list->tiers as $tier)
                    <tr><td>{{ $tier->species === 'dog' ? 'Câine' : 'Pisică' }}</td><td><strong>{{ $tier->label }}</strong></td>
                        <td>{{ rtrim(rtrim(number_format($tier->min_kg, 2, ',', ''), '0'), ',') }} – {{ $tier->max_kg >= 999 ? 'fără limită (formular: 70)' : rtrim(rtrim(number_format($tier->max_kg, 2, ',', ''), '0'), ',').($tier->max_inclusive ? ' inclusiv' : ' exclusiv') }}</td>
                        <td>{{ $tier->price_monthly }} lei</td><td>{{ $tier->price_6m }} lei</td><td>{{ $tier->price_12m }} lei</td><td>{{ $tier->total_normal }} lei</td><td>{{ $tier->total_subscription }} lei</td></tr>
                @endforeach
            </table></div>
        </section>
        <section class="hv-card">
            <h3>Reducere pentru mai multe animale</h3>
            <p>Aceeași categorie de greutate cu un animal existent: {{ $list->multi_pet_rules['same_tier_percent'] }}%.
               Categorie diferită: @foreach ($list->multi_pet_rules['different_tier_percent'] as $ordinal => $percent)animalul nr. {{ $ordinal }}{{ (int) $ordinal === (int) $list->multi_pet_rules['max_ordinal'] ? '+' : '' }}: {{ $percent }}%@if (! $loop->last), @endif @endforeach.
               Oferta este valabilă {{ $list->contract_valid_days }} zile de la semnarea online.</p>
        </section>
        @foreach ($list->tiers as $tier)
            <section class="hv-card">
                <h3>{{ $tier->species === 'dog' ? 'Câine' : 'Pisică' }}, {{ $tier->label }}: servicii incluse</h3>
                <div class="hv-scroll"><table class="hv-table">
                    <thead><tr><th>Serviciu</th><th>Utilizări</th><th>Preț normal</th><th>Preț abonament</th><th>Puncte pe utilizare</th></tr></thead>
                    @foreach ($tier->services as $service)
                        <tr><td>{{ $service->display_name }}</td><td>{{ $service->quantity }}</td><td>{{ $service->price_normal }} lei</td><td>{{ $service->price_subscription }} lei</td><td>{{ $service->points_per_use }}</td></tr>
                    @endforeach
                </table></div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
