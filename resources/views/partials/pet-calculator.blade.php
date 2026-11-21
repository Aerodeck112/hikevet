{{-- Calculatorul de abonament: animalul desenat creste cu greutatea; preturile vin din lista de preturi activa. --}}
<div class="petcalc" x-data="priceCalc(@js($tiers))">
    <div class="petcalc-top">
        <div class="choice petcalc-species" role="group" aria-label="{{ __('Specie') }}">
            <button type="button" :aria-pressed="(species === 'dog').toString()" @click="setSpecies('dog')">{{ __('Câine') }}</button>
            <button type="button" :aria-pressed="(species === 'cat').toString()" @click="setSpecies('cat')">{{ __('Pisică') }}</button>
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

    <label class="sr-only" for="{{ $id }}">{{ __('Greutatea animalului, în kilograme') }}</label>
    <input class="range petcalc-range" id="{{ $id }}" type="range" :min="bounds.min" :max="bounds.max" step="0.25" x-model.number="weight">

    <ol class="petcalc-ruler" aria-label="{{ __('Categorii de greutate') }}">
        <template x-for="t in speciesTiers" :key="t.label">
            <li :class="{ 'is-on': tier && tier.label === t.label }" @click="weight = Math.min(bounds.max, Math.max(bounds.min, t.min + (t.max - t.min) / 2))">
                <span x-text="t.label"></span>
            </li>
        </template>
    </ol>

    <div class="petcalc-periods" aria-live="polite">
        <div class="pc-period">
            <span class="pc-name">{{ __('Lunar') }}</span>
            <strong><span x-text="money(shown.monthly)"></span> <small>{{ __('lei/lună') }}</small></strong>
        </div>
        <div class="pc-period">
            <span class="pc-name">{{ __('6 luni') }}</span>
            <strong><span x-text="money(shown.m6)"></span> <small>{{ __('lei') }}</small></strong>
            <span class="pc-note" x-show="tier">≈ <span x-text="money(perMonth6)"></span> {{ __('lei/lună') }}<template x-if="save6 > 0"><span>, {!! __('economisești :amount', ['amount' => '<b x-text="'.e(\Illuminate\Support\Js::from(__(':amount lei'))).'.replace(\':amount\', money(save6))"></b>']) !!}</span></template></span>
        </div>
        <div class="pc-period pc-period--best">
            <span class="pc-name">{{ __('12 luni') }}</span>
            <strong><span x-text="money(shown.m12)"></span> <small>{{ __('lei') }}</small></strong>
            <span class="pc-note" x-show="tier">≈ <span x-text="money(perMonth12)"></span> {{ __('lei/lună') }}<template x-if="save12 > 0"><span>, {!! __('economisești :amount', ['amount' => '<b x-text="'.e(\Illuminate\Support\Js::from(__(':amount lei'))).'.replace(\':amount\', money(save12))"></b>']) !!}</span></template></span>
        </div>
    </div>
    <p class="calc-note">{!! __('Categoria :tier.', ['tier' => '<strong x-text="tier ? tier.label : \'–\'"></strong>']) !!} {{ __('Preț standard pentru primul animal; al doilea, al treilea și al patrulea primesc reducere.') }} {{ __('Deparazitările se achită separat.') }}</p>
</div>
