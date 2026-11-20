{{-- Calculatorul de abonament: animalul desenat creste cu greutatea; preturile vin din lista de preturi activa. --}}
<div class="petcalc" x-data="priceCalc(@js($tiers))">
    <div class="petcalc-top">
        <div class="choice petcalc-species" role="group" aria-label="Specie">
            <button type="button" :aria-pressed="(species === 'dog').toString()" @click="setSpecies('dog')">Câine</button>
            <button type="button" :aria-pressed="(species === 'cat').toString()" @click="setSpecies('cat')">Pisică</button>
        </div>
        <output class="petcalc-kg" for="{{ $id }}"><span x-text="weightLabel"></span> kg</output>
    </div>

    <div class="petcalc-stage" aria-hidden="true">
        <div class="petcalc-pet" :style="'--s:' + petScale" :class="'hop-' + (hop % 2)">
            <div x-show="species === 'dog'">@include('partials.mascot-dog')</div>
            <div x-show="species === 'cat'" x-cloak>@include('partials.mascot-cat')</div>
        </div>
        <div class="petcalc-table"></div>
    </div>

    <label class="sr-only" for="{{ $id }}">Greutatea animalului, în kilograme</label>
    <input class="range petcalc-range" id="{{ $id }}" type="range" :min="bounds.min" :max="bounds.max" step="0.25" x-model.number="weight">

    <ol class="petcalc-ruler" aria-label="Categorii de greutate">
        <template x-for="t in speciesTiers" :key="t.label">
            <li :class="{ 'is-on': tier && tier.label === t.label }" @click="weight = Math.min(bounds.max, Math.max(bounds.min, t.min + (t.max - t.min) / 2))">
                <span x-text="t.label"></span>
            </li>
        </template>
    </ol>

    <div class="petcalc-periods" aria-live="polite">
        <div class="pc-period">
            <span class="pc-name">Lunar</span>
            <strong><span x-text="money(shown.monthly)"></span> <small>lei/lună</small></strong>
        </div>
        <div class="pc-period">
            <span class="pc-name">6 luni</span>
            <strong><span x-text="money(shown.m6)"></span> <small>lei</small></strong>
            <span class="pc-note" x-show="tier">≈ <span x-text="money(perMonth6)"></span> lei/lună<template x-if="save6 > 0"><span>, economisești <b x-text="money(save6) + ' lei'"></b></span></template></span>
        </div>
        <div class="pc-period pc-period--best">
            <span class="pc-name">12 luni</span>
            <strong><span x-text="money(shown.m12)"></span> <small>lei</small></strong>
            <span class="pc-note" x-show="tier">≈ <span x-text="money(perMonth12)"></span> lei/lună<template x-if="save12 > 0"><span>, economisești <b x-text="money(save12) + ' lei'"></b></span></template></span>
        </div>
    </div>
    <p class="calc-note">Categoria <strong x-text="tier ? tier.label : '–'"></strong>. Preț standard pentru primul animal; al doilea, al treilea și al patrulea primesc reducere. Deparazitările se achită separat.</p>
</div>
