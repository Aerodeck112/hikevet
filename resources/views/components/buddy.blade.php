{{-- Un personaj de pe fatada: Raza (pisica cu ochelari) sau Hiko (ciobanescul), care urmareste cursorul si spune ceva la click.
     pose: sit (pisica stand), peek (se iteste peste margine), sleep (pisica doarme pe margine). --}}
@props(['kind' => 'cat', 'pose' => 'sit', 'say' => [], 'label' => null, 'greet' => true])
@php
    $kind = $kind === 'dog' ? 'dog' : 'cat';
    $lines = array_values(array_filter((array) $say, 'strlen'));
    $name = $kind === 'dog' ? 'Hiko' : 'Raza';
    $who = $kind === 'dog' ? 'Hiko, câinele HiKeVet' : 'Raza, pisica HiKeVet';
@endphp
<div {{ $attributes->class(['buddy', 'buddy--'.$kind, 'buddy--'.$pose]) }} data-buddy @if ($lines) data-say='@json($lines)' @endif @if ($greet && $lines) data-greet @endif>
    @if ($lines)
        <p class="buddy-say" aria-live="polite"><b class="buddy-name">{{ $name }}</b><span class="buddy-line"></span></p>
        <button type="button" class="buddy-hit" aria-label="{{ $label ?? $who.': apasă și îți spune ceva' }}">
            @include('partials.mascot-'.$kind)
        </button>
    @else
        @include('partials.mascot-'.$kind)
    @endif
    @if ($pose === 'sleep')<span class="buddy-z" aria-hidden="true"><i>z</i><i>z</i><i>z</i></span>@endif
</div>
