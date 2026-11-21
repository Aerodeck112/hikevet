{{-- Scena de pe pagina unui serviciu: medicul ii face lui Hiko sau Razei exact acel serviciu (App\Support\VetScenes). --}}
@props(['slug'])
@php($scene = \App\Support\VetScenes::get($slug))
@if ($scene)
<figure {{ $attributes->class(['vet-scene', 'vs-'.$slug]) }} data-vet-scene>
    {!! $scene['svg'] !!}
    <figcaption class="vet-scene-say">
        @if ($scene['patient'] !== 'none')<b class="buddy-name buddy-name--{{ $scene['patient'] }}">{{ $scene['patient'] === 'cat' ? 'Raza' : 'Hiko' }}</b>@else<b class="buddy-name buddy-name--dog">Hiko</b>@endif
        {{ $scene['caption'] }}
    </figcaption>
</figure>
@endif
