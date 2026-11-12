<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
        <x-filament::button color="gray" size="sm" wire:click="shift(-1)" icon="heroicon-o-chevron-left">Săptămâna anterioară</x-filament::button>
        <x-filament::button color="gray" size="sm" wire:click="today">Săptămâna curentă</x-filament::button>
        <x-filament::button color="gray" size="sm" wire:click="shift(1)" icon="heroicon-o-chevron-right" icon-position="after">Săptămâna următoare</x-filament::button>
        <strong style="margin-left:.5rem">{{ $start->format('d.m') }} – {{ $start->addDays(6)->format('d.m.Y') }}</strong>
        <label style="margin-left:auto;display:flex;gap:.5rem;align-items:center;font-size:.9rem">Medic
            <select wire:model.live="doctor" style="border-radius:.75rem;border:1px solid #d4d4d8;padding:.35rem 2rem .35rem .75rem;background-color:#fff;color:#111">
                <option value="">Toți medicii</option>
                @foreach ($doctors as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </label>
    </div>

    @foreach ($blocks as $block)
        <p class="hv-pill hv-pill--off" style="padding:.4rem .9rem">Blocat: {{ $block->doctor?->name ?? 'toată clinica' }}, {{ $block->starts_at->format('d.m H:i') }} – {{ $block->ends_at->format('d.m H:i') }}{{ $block->reason ? ' · '.$block->reason : '' }}</p>
    @endforeach

    <div class="hv-scroll">
        <div class="hv-cal" role="table" aria-label="Calendar săptămânal">
            <div class="hv-cal-head" role="columnheader">Ora</div>
            @foreach ($days as $day)
                <div class="hv-cal-head" role="columnheader" @if ($day->isToday()) style="background:#c8efd9;color:#0a3d28" @endif>
                    {{ ucfirst($day->translatedFormat('D')) }} {{ $day->format('d.m') }}<br><span style="font-weight:400">{{ $counts[$day->toDateString()] ?? 0 }} programări</span>
                </div>
            @endforeach
            @foreach ($times as $time)
                <div class="hv-cal-time">{{ $time }}</div>
                @foreach ($days as $day)
                    <div>
                        @foreach ($cells[$day->toDateString()][$time] ?? [] as $a)
                            <span class="hv-appt hv-appt--{{ $a->status->value }}" title="{{ $a->client_phone }}{{ $a->notes ? ' · '.$a->notes : '' }}">
                                {{ $a->starts_at->format('H:i') }}–{{ $a->ends_at->format('H:i') }} {{ $a->client_name }}@if ($a->pet_name) · {{ $a->pet_name }}@endif
                                <br><span style="font-weight:400">{{ $a->doctor->name }}{{ $a->service ? ' · '.$a->service->name : '' }}</span>
                            </span>
                        @endforeach
                        <a href="{{ $createUrl }}?at={{ urlencode($day->toDateString().' '.$time) }}@if ($doctor)&doctor={{ $doctor }}@endif" style="color:#0b5c3b;font-weight:700;text-decoration:none" aria-label="Programare nouă {{ $day->format('d.m') }} la {{ $time }}">+</a>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
    <p class="hv-muted">Verde: confirmată. Galben: cerere care așteaptă confirmarea. Programările istorice ale campaniei de sterilizare sunt separate și se găsesc în Arhivă sterilizări.</p>
</x-filament-panels::page>
