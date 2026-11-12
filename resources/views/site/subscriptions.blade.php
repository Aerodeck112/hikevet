@extends('layouts.site')

@php
    $user = auth()->user();
    $existingPets = $user ? $user->activePets()->count() : 0;
    $prefill = $user ? ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'address' => $user->address] : [];
@endphp

@section('content')
@include('site._head', ['title' => 'Abonamente pentru animăluțe', 'lead' => $page->lead])

<section style="padding-bottom: var(--section)">
    <div class="wrap split" style="align-items:start">
        <div class="prose">
            {!! $page->body !!}
            <p><strong>Abonamentul este un plan de servicii veterinare, nu o asigurare.</strong> Plata se face fizic la clinică, lunar, pe 6 luni sau pe 12 luni. Deparazitările interne și externe nu sunt incluse și se achită separat.</p>
            <p><a class="btn btn-primary" href="#configurator" data-track="subscription_start">Configurează abonamentul</a></p>
        </div>
        <div x-data="priceCalc(@js($tiers))" class="calc-panel" style="border:1px solid var(--line)">
            <h2 class="h-sm">Prețul pentru primul animal</h2>
            <div class="choice" role="group" aria-label="Specie">
                <button type="button" :aria-pressed="(species === 'dog').toString()" @click="setSpecies('dog')">Câine</button>
                <button type="button" :aria-pressed="(species === 'cat').toString()" @click="setSpecies('cat')">Pisică</button>
            </div>
            <div class="calc-weight"><label for="sub-weight">Greutate</label><output for="sub-weight"><span x-text="weightLabel"></span> kg</output></div>
            <input class="range" id="sub-weight" type="range" :min="bounds.min" :max="bounds.max" step="0.25" x-model.number="weight">
            <dl class="calc-result" aria-live="polite">
                <div><dt>Lunar</dt><dd><span x-text="tier ? tier.monthly : '–'"></span> <small>lei</small></dd></div>
                <div><dt>6 luni</dt><dd><span x-text="tier ? tier.m6 : '–'"></span> <small>lei</small></dd></div>
                <div><dt>12 luni</dt><dd><span x-text="tier ? tier.m12 : '–'"></span> <small>lei</small></dd></div>
            </dl>
        </div>
    </div>
</section>

<section class="section section--mint" id="configurator">
    <div class="wrap" style="max-width:920px">
        <div class="cfg card" x-data="configurator({ csrf: '{{ csrf_token() }}', token: '{{ (string) \Illuminate\Support\Str::uuid() }}', offerUrl: '{{ site_url('/abonamente/oferta') }}', submitUrl: '{{ site_url('/abonamente/contract') }}', prefill: @js($prefill) })">
            <header class="cfg-head">
                <div>
                    <h2 class="display h-md">Configurează abonamentul HiKeVet</h2>
                    <p style="margin:.6rem 0 0">Simplu, clar și adaptat nevoilor animăluțului tău.</p>
                </div>
                <div class="cfg-progress" role="group" aria-label="Progres formular">
                    <p>Pasul <strong x-text="step">1</strong> din 6</p>
                    <div class="cfg-dots" aria-hidden="true"><template x-for="i in 6"><span :class="{ on: i <= step }"></span></template></div>
                </div>
            </header>

            @if (request()->boolean('hv_new_pet') || $existingPets > 0)
                <p class="notice"><strong>Cont detectat:</strong> ai deja {{ $existingPets }} animăluț(e) în cont. Oferta pentru acest animal aplică automat regula de reducere pentru mai multe animale, în funcție de categoria de greutate.</p>
            @endif

            <p class="notice notice--error" x-show="error" x-text="error" x-ref="error" tabindex="-1" role="alert" x-cloak></p>

            <fieldset :disabled="locked" style="border:0;padding:0;margin:0;min-width:0">
            <section data-step="1" x-show="step === 1">
                <h3 class="h-sm">1. Datele animăluțului</h3>
                <p>Completează numele și acceptă documentele legale pentru a continua.</p>
                <div class="field"><label for="hv-petName">Numele animăluțului *</label><input id="hv-petName" x-model="form.pet_name" placeholder="Ex.: Rex" maxlength="80" autocomplete="off"></div>
                <label class="check"><input type="checkbox" x-model="form.agree"><span>Sunt de acord cu <a href="{{ site_url('/termeni-si-conditii-abonamente/') }}" target="_blank" rel="noopener">Termenii și condițiile</a> și cu <a href="{{ site_url('/politica-de-confidentialitate/') }}" target="_blank" rel="noopener">Politica de confidențialitate</a>.</span></label>
            </section>

            <section data-step="2" x-show="step === 2" x-cloak>
                <h3 class="h-sm">2. Specie și sex</h3>
                <p>Alege variantele care descriu animăluțul.</p>
                <div class="choice" role="group" aria-label="Specie">
                    <button type="button" :aria-pressed="(form.species === 'dog').toString()" @click="pick('species', 'dog')">Câine</button>
                    <button type="button" :aria-pressed="(form.species === 'cat').toString()" @click="pick('species', 'cat')">Pisică</button>
                </div>
                <div class="choice" role="group" aria-label="Sex">
                    <button type="button" :aria-pressed="(form.sex === 'male').toString()" @click="pick('sex', 'male')">Mascul</button>
                    <button type="button" :aria-pressed="(form.sex === 'female').toString()" @click="pick('sex', 'female')">Femelă</button>
                </div>
            </section>

            <section data-step="3" x-show="step === 3" x-cloak>
                <h3 class="h-sm">3. Vârstă și rasă</h3>
                <p>Aceste date ne ajută să calculăm corect oferta.</p>
                <div class="grid-2">
                    <div class="field"><label for="hv-dob">Data nașterii *</label><input id="hv-dob" type="date" x-model="form.dob" max="{{ now()->toDateString() }}"></div>
                    <div class="field">
                        <label for="hv-breed">Rasa *</label>
                        <select id="hv-breed" x-model="form.breed">
                            <option value="">Selectează</option>
                            <template x-for="b in breeds" :key="b"><option :value="b" x-text="b" :selected="form.breed === b"></option></template>
                            <option value="__other__">Altă rasă (scrie mai jos)</option>
                        </select>
                    </div>
                </div>
                <div class="field" x-show="form.breed === '__other__'"><label for="hv-breedOther">Scrie rasa</label><input id="hv-breedOther" x-model="form.breed_other" maxlength="80"></div>
            </section>

            <section data-step="4" x-show="step === 4" x-cloak>
                <h3 class="h-sm">4. Date medicale și proprietar</h3>
                <p>Completează informațiile medicale și datele persoanei care semnează contractul.</p>
                <div class="grid-2">
                    <div class="field"><label for="hv-weight">Greutate (kg) *</label><input id="hv-weight" type="number" min="0" max="70" step="0.1" inputmode="decimal" x-model="form.weight" placeholder="Ex.: 12,5"></div>
                    <div class="field"><label for="hv-ster">Sterilizat / castrat *</label><select id="hv-ster" x-model="form.sterilized"><option value="">Selectează</option><option value="da">Da</option><option value="nu">Nu</option></select></div>
                    <div class="field"><label for="hv-chronic">Afecțiune cronică? *</label><select id="hv-chronic" x-model="form.chronic"><option value="">Selectează</option><option value="nu">Nu</option><option value="da">Da</option></select></div>
                    <div class="field" x-show="form.chronic === 'da'"><label for="hv-chronicTxt">Ce afecțiune?</label><input id="hv-chronicTxt" x-model="form.chronic_text" placeholder="Ex.: dermatită" maxlength="200"></div>
                    <div class="field"><label for="hv-emergency">Urgențe medicale în trecut? *</label><select id="hv-emergency" x-model="form.emergency"><option value="">Selectează</option><option value="nu">Nu</option><option value="da">Da</option></select></div>
                </div>
                <fieldset class="cfg-routine">
                    <legend class="label">Controale de rutină</legend>
                    @foreach (['lunar' => 'Lunar', 'trimestrial' => 'Trimestrial', 'semestrial' => 'Semestrial', 'anual' => 'Anual', 'urgenta' => 'Numai urgențe'] as $value => $label)
                        <label><input type="checkbox" value="{{ $value }}" x-model="form.routine"> {{ $label }}</label>
                    @endforeach
                </fieldset>
                <hr class="cfg-hr">
                <div class="grid-2">
                    <div class="field"><label for="hv-ownerName">Nume și prenume *</label><input id="hv-ownerName" x-model="form.owner_name" autocomplete="name" maxlength="120"></div>
                    <div class="field"><label for="hv-ownerEmail">Email *</label><input id="hv-ownerEmail" type="email" x-model="form.owner_email" autocomplete="email" @if ($user) readonly @endif></div>
                    <div class="field"><label for="hv-ownerPhone">Telefon *</label><input id="hv-ownerPhone" type="tel" x-model="form.owner_phone" autocomplete="tel"></div>
                    <div class="field"><label for="hv-ownerAddr">Adresă *</label><input id="hv-ownerAddr" x-model="form.owner_addr" autocomplete="street-address"></div>
                    <div class="field"><label for="hv-ownerCnp">CNP *</label><input id="hv-ownerCnp" x-model="form.owner_cnp" inputmode="numeric" maxlength="13" autocomplete="off"><span class="hint">13 cifre. Este cerut de contract și se păstrează criptat.</span></div>
                    <div class="field"><label for="hv-ownerCiSerie">Carte de identitate, seria *</label><input id="hv-ownerCiSerie" x-model="form.owner_ci_serie" maxlength="4" autocomplete="off"></div>
                    <div class="field"><label for="hv-ownerCiNr">Carte de identitate, numărul *</label><input id="hv-ownerCiNr" x-model="form.owner_ci_nr" inputmode="numeric" maxlength="10" autocomplete="off"></div>
                    <div class="field"><label for="hv-petMicrochip">Microcip sau număr carnet (opțional)</label><input id="hv-petMicrochip" x-model="form.pet_microchip" maxlength="40"></div>
                </div>
            </section>

            <section data-step="5" x-show="step === 5" x-cloak>
                <h3 class="h-sm">5. Oferta, documentele și semnătura</h3>
                <p>Verifică oferta, deschide documentele și semnează contractul.</p>
                <template x-if="offer">
                    <div>
                        <dl class="calc-result cfg-offer">
                            <div><dt>Categorie</dt><dd style="font-size:1.15rem" x-text="offer.tier_label"></dd></div>
                            <div><dt>Lunar</dt><dd><span x-text="offer.final.monthly"></span> <small>lei</small></dd></div>
                            <div><dt>6 luni</dt><dd><span x-text="offer.final.m6"></span> <small>lei</small></dd></div>
                            <div><dt>12 luni</dt><dd><span x-text="offer.final.m12"></span> <small>lei</small></dd></div>
                        </dl>
                        <div class="notice notice--ok" style="margin-top:1rem">
                            <strong x-text="offer.discount_label"></strong><br>
                            Preț standard: <span x-text="offer.standard.monthly"></span> lei/lună, 6 luni <span x-text="offer.standard.m6"></span> lei, 12 luni <span x-text="offer.standard.m12"></span> lei.
                        </div>
                        <div class="table-wrap">
                            <table class="table">
                                <caption class="sr-only">Servicii incluse, conform anexei</caption>
                                <thead><tr><th scope="col">Serviciu</th><th scope="col">Preț normal</th><th scope="col">Preț abonament</th></tr></thead>
                                <tbody><template x-for="row in offer.services" :key="row.name"><tr><td x-text="row.display_name"></td><td><span x-text="row.normal"></span> lei</td><td><span x-text="row.abon"></span> lei</td></tr></template></tbody>
                                <tfoot><tr><td>Total</td><td><span x-text="offer.total_normal"></span> lei</td><td><span x-text="offer.total_subscription"></span> lei</td></tr></tfoot>
                            </table>
                        </div>
                        <p class="notice notice--warn" style="margin-top:1rem"><strong>Important:</strong> deparazitările interne și externe nu sunt incluse în abonament și se achită separat.</p>
                    </div>
                </template>
                <h4 class="label" style="margin-top:1.5rem">Confirmări legale</h4>
                <label class="check"><input type="checkbox" x-model="form.legal.contract"><span>Am citit <a href="{{ site_url('/contract-abonament/') }}" target="_blank" rel="noopener">Contractul</a>.</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.annex"><span>Am citit <a href="{{ site_url('/anexa-abonamente/') }}" target="_blank" rel="noopener">Anexa</a>.</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.terms"><span>Am citit <a href="{{ site_url('/termeni-si-conditii-abonamente/') }}" target="_blank" rel="noopener">Termenii</a>.</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.guide"><span>Am citit <a href="{{ site_url('/ghid-proprietar/') }}" target="_blank" rel="noopener">Ghidul</a>.</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.privacy"><span>Sunt de acord cu <a href="{{ site_url('/politica-de-confidentialitate/') }}" target="_blank" rel="noopener">prelucrarea datelor</a>.</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.not_insurance"><span>Înțeleg că abonamentul este un plan de servicii, nu o asigurare.</span></label>
                <h4 class="label" style="margin-top:1.5rem" id="hv-sign-label">Semnătura</h4>
                <div x-ref="pad" @signed="signed = $event.detail">
                    <div x-data="signaturePad">
                        <div class="sign-wrap"><canvas x-ref="canvas" width="720" height="180" aria-labelledby="hv-sign-label" role="img"></canvas></div>
                        <p class="hint" style="margin:.5rem 0 0">Semnează cu degetul, cu stylusul sau cu mouse-ul. Este o semnătură electronică simplă, nu una calificată.</p>
                        <p style="margin-top:.75rem"><button class="btn btn-ghost btn-sm" type="button" @click="clear()">Șterge semnătura</button></p>
                    </div>
                </div>
            </section>
            </fieldset>

            <section data-step="6" x-show="step === 6" x-cloak>
                <h3 class="h-sm">6. Confirmarea abonamentului</h3>
                <p>Alege perioada și trimite contractul către clinică. Plata se efectuează fizic la clinica HiKeVet; contractul rămâne în așteptare până când plata este confirmată de clinică.</p>
                <div class="cfg-periods" role="radiogroup" aria-label="Perioada abonamentului">
                    @foreach ([1 => ['Lunar', 'Plată lunară la clinică', 'monthly'], 6 => ['6 luni', 'Preț total pentru 6 luni', 'm6'], 12 => ['12 luni', 'Preț total pentru 12 luni', 'm12']] as $months => [$title, $note, $key])
                        <label class="cfg-period" :class="{ 'is-selected': form.payment_period_months === {{ $months }} }">
                            <input type="radio" name="hv_payment_period" value="{{ $months }}" x-model.number="form.payment_period_months" :disabled="sent">
                            <span class="cfg-period-title">{{ $title }}</span>
                            <span class="cfg-period-price"><strong x-text="offer ? offer.final.{{ $key }} : '–'"></strong> lei</span>
                            <span class="cfg-period-note">{{ $note }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="notice notice--ok" x-show="sent" x-cloak role="status">
                    <strong x-text="result ? result.message : ''"></strong><br>
                    Contract nr. <span x-text="result ? result.number : ''"></span>. Te așteptăm la clinică până la <span x-text="result ? result.valid_until : ''"></span> pentru plată.
                    <span x-show="result && result.account_created">Ți-am trimis pe email linkul pentru activarea contului.</span>
                </div>
                <div class="cfg-actions">
                    <button class="btn btn-primary" type="button" @click="send()" :disabled="loading || sent" x-text="sent ? 'Abonament înregistrat' : (loading ? 'Se înregistrează…' : 'Confirm și plătesc la clinică')">Confirm și plătesc la clinică</button>
                    <button class="btn btn-ghost" type="button" @click="edit()" x-show="!sent">Corectează datele</button>
                    <a class="btn btn-dark" :href="result ? result.account_url : '#'" x-show="sent" x-cloak>Vezi contul meu</a>
                </div>
            </section>

            <footer class="cfg-foot" x-show="step < 6">
                <button class="btn btn-ghost" type="button" @click="back()" :disabled="step === 1">Înapoi</button>
                <button class="btn btn-dark" type="button" @click="next()" x-show="step < 5" :disabled="loading" x-text="step === 4 ? (loading ? 'Se calculează…' : 'Calculează oferta') : 'Continuă'">Continuă</button>
                <button class="btn btn-primary" type="button" @click="openPreview()" x-show="step === 5" x-cloak>Semnează și generează contractul</button>
                <button class="btn btn-ghost btn-sm" type="button" @click="reset()" style="margin-left:auto">Reîncepe</button>
            </footer>

            <div class="modal" x-show="previewOpen" x-cloak @keydown.escape.window="previewOpen = false">
                <div class="modal-backdrop" @click="previewOpen = false"></div>
                <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="hv-preview-title" x-trap.noscroll="previewOpen">
                    <h3 class="h-sm" id="hv-preview-title">Previzualizare contract</h3>
                    <div class="prose" style="margin:1rem 0">
                        <p><strong>CONTRACT ABONAMENT MEDICAL VETERINAR</strong></p>
                        <p><strong>Beneficiar:</strong> <span x-text="form.owner_name"></span>, <span x-text="form.owner_email"></span>, <span x-text="form.owner_phone"></span></p>
                        <p><strong>Animal:</strong> <span x-text="form.pet_name"></span>, <span x-text="form.species === 'dog' ? 'Câine' : 'Pisică'"></span>, <span x-text="breedLabel"></span>, <span x-text="form.sex === 'male' ? 'Mascul' : 'Femelă'"></span></p>
                        <p><strong>Categorie:</strong> <span x-text="offer ? offer.tier_label : ''"></span>. <strong>Preț lunar final:</strong> <span x-text="offer ? offer.final.monthly : ''"></span> lei.</p>
                        <p><strong>Regula pentru mai multe animale:</strong> <span x-text="offer ? offer.discount_label : ''"></span></p>
                        <p class="notice"><strong>Important:</strong> ai la dispoziție {{ $list->contract_valid_days }} zile de la data semnării online pentru a te prezenta la clinică și a efectua plata. În caz contrar, contractul și oferta se anulează automat.</p>
                        <p>PDF-ul complet se generează la trimitere.</p>
                    </div>
                    <div class="cfg-actions">
                        <button class="btn btn-ghost" type="button" @click="previewOpen = false">Corectează datele</button>
                        <button class="btn btn-primary" type="button" @click="confirmPreview()" x-ref="confirm">Confirm contractul și continui</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap split" style="align-items:start">
        <div>
            <h2 class="display h-md">Mai multe animăluțe, preț mai mic</h2>
            <p style="margin-top:1.25rem">Primul animal are prețul standard al abonamentului, conform categoriei lui de greutate.</p>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Animal</th><th scope="col">Aceeași categorie de kg</th><th scope="col">Categorie diferită</th></tr></thead>
                    <tbody>
                        @foreach ($list->multi_pet_rules['different_tier_percent'] as $ordinal => $percent)
                            <tr><td>Al {{ ['2' => 'doilea', '3' => 'treilea', '4' => 'patrulea'][$ordinal] ?? $ordinal.'-lea' }}{{ (int) $ordinal === (int) $list->multi_pet_rules['max_ordinal'] ? ' și următoarele' : '' }}</td>
                                <td>{{ $list->multi_pet_rules['same_tier_percent'] }}% reducere</td><td>{{ $percent }}% reducere</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="hint" style="margin-top:.75rem">Reducerea se calculează automat pentru animalele din același cont de client.</p>
        </div>
        <div>
            <h2 class="display h-md">Ai nelămuriri?</h2>
            <div class="prose" style="margin-top:1.25rem">{!! $b['closing'] ?? '' !!}</div>
            <p><a class="btn btn-dark" href="{{ site_url('/hikevet-contact/') }}">Contactează-ne</a></p>
        </div>
    </div>
</section>
@endsection
