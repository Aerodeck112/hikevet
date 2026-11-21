@extends('layouts.site')

@php
    $user = auth()->user();
    $existingPets = $user ? $user->activePets()->count() : 0;
    $prefill = $user ? ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'address' => $user->address] : [];
@endphp

@section('content')
@include('site._head', ['title' => __('Abonamente pentru animăluțe'), 'lead' => $page->lead])

<section style="padding-bottom: var(--section)">
    <div class="wrap split" style="align-items:start">
        <div class="prose">
            {!! $page->body !!}
            <p><strong>{{ __('Abonamentul este un plan de servicii veterinare, nu o asigurare.') }}</strong> {{ \App\Services\Payments\StripeCheckout::enabled() ? __('Plătești la clinică sau online, cu cardul, lunar, pe 6 luni sau pe 12 luni.') : __('Plătești la clinică, lunar, pe 6 luni sau pe 12 luni.') }} {{ __('Deparazitările interne și externe nu sunt incluse și se achită separat.') }}</p>
            <p><a class="btn btn-primary" href="#configurator" data-track="subscription_start">{{ __('Configurează abonamentul') }}</a></p>
        </div>
        @include('partials.pet-calculator', ['id' => 'calc-weight-sub'])
    </div>
</section>

<section class="section section--mint" id="configurator">
    <div class="wrap" style="max-width:920px">
        <div class="cfg card" x-data="configurator({ csrf: '{{ csrf_token() }}', token: '{{ (string) \Illuminate\Support\Str::uuid() }}', offerUrl: '{{ site_url('/abonamente/oferta') }}', submitUrl: '{{ site_url('/abonamente/contract') }}', prefill: @js($prefill) })">
            <header class="cfg-head">
                <div>
                    <h2 class="display h-md">{{ __('Configurează abonamentul HiKeVet') }}</h2>
                    <p style="margin:.6rem 0 0">{{ __('Simplu, clar și adaptat nevoilor animăluțului tău.') }}</p>
                </div>
                <div class="cfg-progress" role="group" aria-label="{{ __('Progres formular') }}">
                    <p>{!! __('Pasul :step din :total', ['step' => '<strong x-text="step">1</strong>', 'total' => 6]) !!}</p>
                    <div class="cfg-dots" aria-hidden="true"><template x-for="i in 6"><span :class="{ on: i <= step }"></span></template></div>
                </div>
            </header>

            @if (request()->boolean('hv_new_pet') || $existingPets > 0)
                <p class="notice"><strong>{{ __('Cont detectat:') }}</strong> {{ __('ai deja :count animăluț(e) în cont. Oferta pentru acest animal aplică automat regula de reducere pentru mai multe animale, în funcție de categoria de greutate.', ['count' => $existingPets]) }}</p>
            @endif

            <p class="notice notice--error" x-show="error" x-text="error" x-ref="error" tabindex="-1" role="alert" x-cloak></p>

            <fieldset :disabled="locked" style="border:0;padding:0;margin:0;min-width:0">
            <section data-step="1" x-show="step === 1">
                <h3 class="h-sm">{{ __('1. Datele animăluțului') }}</h3>
                <p>{{ __('Completează numele și acceptă documentele legale pentru a continua.') }}</p>
                <div class="field"><label for="hv-petName">{{ __('Numele animăluțului') }} *</label><input id="hv-petName" x-model="form.pet_name" placeholder="{{ __('Ex.: Rex') }}" maxlength="80" autocomplete="off"></div>
                <label class="check"><input type="checkbox" x-model="form.agree"><span>{!! __('Sunt de acord cu <a href=":terms_url" target="_blank" rel="noopener">Termenii și condițiile</a> și cu <a href=":privacy_url" target="_blank" rel="noopener">Politica de confidențialitate</a>.', ['terms_url' => e(site_url('/termeni-si-conditii-abonamente/')), 'privacy_url' => e(site_url('/politica-de-confidentialitate/'))]) !!}</span></label>
            </section>

            <section data-step="2" x-show="step === 2" x-cloak>
                <h3 class="h-sm">{{ __('2. Specie și sex') }}</h3>
                <p>{{ __('Alege variantele care descriu animăluțul.') }}</p>
                <div class="choice" role="group" aria-label="{{ __('Specie') }}">
                    <button type="button" :aria-pressed="(form.species === 'dog').toString()" @click="pick('species', 'dog')">{{ __('Câine') }}</button>
                    <button type="button" :aria-pressed="(form.species === 'cat').toString()" @click="pick('species', 'cat')">{{ __('Pisică') }}</button>
                </div>
                <div class="choice" role="group" aria-label="{{ __('Sex') }}">
                    <button type="button" :aria-pressed="(form.sex === 'male').toString()" @click="pick('sex', 'male')">{{ __('Mascul') }}</button>
                    <button type="button" :aria-pressed="(form.sex === 'female').toString()" @click="pick('sex', 'female')">{{ __('Femelă') }}</button>
                </div>
            </section>

            <section data-step="3" x-show="step === 3" x-cloak>
                <h3 class="h-sm">{{ __('3. Vârstă și rasă') }}</h3>
                <p>{{ __('Aceste date ne ajută să calculăm corect oferta.') }}</p>
                <div class="grid-2">
                    <div class="field"><label for="hv-dob">{{ __('Data nașterii') }} *</label><input id="hv-dob" type="date" x-model="form.dob" max="{{ now()->toDateString() }}"></div>
                    <div class="field">
                        <label for="hv-breed">{{ __('Rasa') }} *</label>
                        <select id="hv-breed" x-model="form.breed">
                            <option value="">{{ __('Selectează') }}</option>
                            <template x-for="b in breeds" :key="b"><option :value="b" x-text="b" :selected="form.breed === b"></option></template>
                            <option value="__other__">{{ __('Altă rasă (scrie mai jos)') }}</option>
                        </select>
                    </div>
                </div>
                <div class="field" x-show="form.breed === '__other__'"><label for="hv-breedOther">{{ __('Scrie rasa') }}</label><input id="hv-breedOther" x-model="form.breed_other" maxlength="80"></div>
            </section>

            <section data-step="4" x-show="step === 4" x-cloak>
                <h3 class="h-sm">{{ __('4. Date medicale și proprietar') }}</h3>
                <p>{{ __('Completează informațiile medicale și datele persoanei care semnează contractul.') }}</p>
                <div class="grid-2">
                    <div class="field"><label for="hv-weight">{{ __('Greutate (kg)') }} *</label><input id="hv-weight" type="number" min="0" max="70" step="0.1" inputmode="decimal" x-model="form.weight" placeholder="{{ __('Ex.: 12,5') }}"></div>
                    <div class="field"><label for="hv-ster">{{ __('Sterilizat / castrat') }} *</label><select id="hv-ster" x-model="form.sterilized"><option value="">{{ __('Selectează') }}</option><option value="da">{{ __('Da') }}</option><option value="nu">{{ __('Nu') }}</option></select></div>
                    <div class="field"><label for="hv-chronic">{{ __('Afecțiune cronică?') }} *</label><select id="hv-chronic" x-model="form.chronic"><option value="">{{ __('Selectează') }}</option><option value="nu">{{ __('Nu') }}</option><option value="da">{{ __('Da') }}</option></select></div>
                    <div class="field" x-show="form.chronic === 'da'"><label for="hv-chronicTxt">{{ __('Ce afecțiune?') }}</label><input id="hv-chronicTxt" x-model="form.chronic_text" placeholder="{{ __('Ex.: dermatită') }}" maxlength="200"></div>
                    <div class="field"><label for="hv-emergency">{{ __('Urgențe medicale în trecut?') }} *</label><select id="hv-emergency" x-model="form.emergency"><option value="">{{ __('Selectează') }}</option><option value="nu">{{ __('Nu') }}</option><option value="da">{{ __('Da') }}</option></select></div>
                </div>
                <fieldset class="cfg-routine">
                    <legend class="label">{{ __('Controale de rutină') }}</legend>
                    @foreach (['lunar' => __('Lunar'), 'trimestrial' => __('Trimestrial'), 'semestrial' => __('Semestrial'), 'anual' => __('Anual'), 'urgenta' => __('Numai urgențe')] as $value => $label)
                        <label><input type="checkbox" value="{{ $value }}" x-model="form.routine"> {{ $label }}</label>
                    @endforeach
                </fieldset>
                <hr class="cfg-hr">
                <div class="grid-2">
                    <div class="field"><label for="hv-ownerName">{{ __('Nume și prenume') }} *</label><input id="hv-ownerName" x-model="form.owner_name" autocomplete="name" maxlength="120"></div>
                    <div class="field"><label for="hv-ownerEmail">{{ __('Email') }} *</label><input id="hv-ownerEmail" type="email" x-model="form.owner_email" autocomplete="email" @if ($user) readonly @endif></div>
                    <div class="field"><label for="hv-ownerPhone">{{ __('Telefon') }} *</label><input id="hv-ownerPhone" type="tel" x-model="form.owner_phone" autocomplete="tel"></div>
                    <div class="field"><label for="hv-ownerAddr">{{ __('Adresă') }} *</label><input id="hv-ownerAddr" x-model="form.owner_addr" autocomplete="street-address"></div>
                    <div class="field"><label for="hv-ownerCnp">{{ __('CNP') }} *</label><input id="hv-ownerCnp" x-model="form.owner_cnp" inputmode="numeric" maxlength="13" autocomplete="off"><span class="hint">{{ __('13 cifre. Este cerut de contract și se păstrează criptat.') }}</span></div>
                    <div class="field"><label for="hv-ownerCiSerie">{{ __('Carte de identitate, seria') }} *</label><input id="hv-ownerCiSerie" x-model="form.owner_ci_serie" maxlength="4" autocomplete="off"></div>
                    <div class="field"><label for="hv-ownerCiNr">{{ __('Carte de identitate, numărul') }} *</label><input id="hv-ownerCiNr" x-model="form.owner_ci_nr" inputmode="numeric" maxlength="10" autocomplete="off"></div>
                    <div class="field"><label for="hv-petMicrochip">{{ __('Microcip sau număr carnet (opțional)') }}</label><input id="hv-petMicrochip" x-model="form.pet_microchip" maxlength="40"></div>
                </div>
            </section>

            <section data-step="5" x-show="step === 5" x-cloak>
                <h3 class="h-sm">{{ __('5. Oferta, documentele și semnătura') }}</h3>
                <p>{{ __('Verifică oferta, deschide documentele și semnează contractul.') }}</p>
                <template x-if="offer">
                    <div>
                        <dl class="calc-result cfg-offer">
                            <div><dt>{{ __('Categorie') }}</dt><dd style="font-size:1.15rem" x-text="offer.tier_label"></dd></div>
                            <div><dt>{{ __('Lunar') }}</dt><dd><span x-text="offer.final.monthly"></span> <small>{{ __('lei') }}</small></dd></div>
                            <div><dt>{{ __('6 luni') }}</dt><dd><span x-text="offer.final.m6"></span> <small>{{ __('lei') }}</small></dd></div>
                            <div><dt>{{ __('12 luni') }}</dt><dd><span x-text="offer.final.m12"></span> <small>{{ __('lei') }}</small></dd></div>
                        </dl>
                        <div class="notice notice--ok" style="margin-top:1rem">
                            <strong x-text="offer.discount_label"></strong><br>
                            {!! __('Preț standard: :monthly lei/lună, 6 luni :m6 lei, 12 luni :m12 lei.', ['monthly' => '<span x-text="offer.standard.monthly"></span>', 'm6' => '<span x-text="offer.standard.m6"></span>', 'm12' => '<span x-text="offer.standard.m12"></span>']) !!}
                        </div>
                        <div class="table-wrap">
                            <table class="table">
                                <caption class="sr-only">{{ __('Servicii incluse, conform anexei') }}</caption>
                                <thead><tr><th scope="col">{{ __('Serviciu') }}</th><th scope="col">{{ __('Preț normal') }}</th><th scope="col">{{ __('Preț abonament') }}</th></tr></thead>
                                <tbody><template x-for="row in offer.services" :key="row.name"><tr><td x-text="row.display_name"></td><td><span x-text="row.normal"></span> {{ __('lei') }}</td><td><span x-text="row.abon"></span> {{ __('lei') }}</td></tr></template></tbody>
                                <tfoot><tr><td>{{ __('Total') }}</td><td><span x-text="offer.total_normal"></span> {{ __('lei') }}</td><td><span x-text="offer.total_subscription"></span> {{ __('lei') }}</td></tr></tfoot>
                            </table>
                        </div>
                        <p class="notice notice--warn" style="margin-top:1rem"><strong>{{ __('Important:') }}</strong> {{ __('deparazitările interne și externe nu sunt incluse în abonament și se achită separat.') }}</p>
                    </div>
                </template>
                <h4 class="label" style="margin-top:1.5rem">{{ __('Confirmări legale') }}</h4>
                <label class="check"><input type="checkbox" x-model="form.legal.contract"><span>{!! __('Am citit <a href=":url" target="_blank" rel="noopener">Contractul</a>.', ['url' => e(site_url('/contract-abonament/'))]) !!}</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.annex"><span>{!! __('Am citit <a href=":url" target="_blank" rel="noopener">Anexa</a>.', ['url' => e(site_url('/anexa-abonamente/'))]) !!}</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.terms"><span>{!! __('Am citit <a href=":url" target="_blank" rel="noopener">Termenii</a>.', ['url' => e(site_url('/termeni-si-conditii-abonamente/'))]) !!}</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.guide"><span>{!! __('Am citit <a href=":url" target="_blank" rel="noopener">Ghidul</a>.', ['url' => e(site_url('/ghid-proprietar/'))]) !!}</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.privacy"><span>{!! __('Sunt de acord cu <a href=":url" target="_blank" rel="noopener">prelucrarea datelor</a>.', ['url' => e(site_url('/politica-de-confidentialitate/'))]) !!}</span></label>
                <label class="check"><input type="checkbox" x-model="form.legal.not_insurance"><span>{{ __('Înțeleg că abonamentul este un plan de servicii, nu o asigurare.') }}</span></label>
                <h4 class="label" style="margin-top:1.5rem" id="hv-sign-label">{{ __('Semnătura') }}</h4>
                <div x-ref="pad" @signed="signed = $event.detail">
                    <div x-data="signaturePad">
                        <div class="sign-wrap"><canvas x-ref="canvas" width="720" height="180" aria-labelledby="hv-sign-label" role="img"></canvas></div>
                        <p class="hint" style="margin:.5rem 0 0">{{ __('Semnează cu degetul, cu stylusul sau cu mouse-ul. Este o semnătură electronică simplă, nu una calificată.') }}</p>
                        <p style="margin-top:.75rem"><button class="btn btn-ghost btn-sm" type="button" @click="clear()">{{ __('Șterge semnătura') }}</button></p>
                    </div>
                </div>
            </section>
            </fieldset>

            <section data-step="6" x-show="step === 6" x-cloak>
                <h3 class="h-sm">{{ __('6. Confirmarea abonamentului') }}</h3>
                <p>{{ __('Alege perioada și trimite contractul către clinică. Plata se efectuează fizic la clinica HiKeVet; contractul rămâne în așteptare până când plata este confirmată de clinică.') }}</p>
                <div class="cfg-periods" role="radiogroup" aria-label="{{ __('Perioada abonamentului') }}">
                    @foreach ([1 => [__('Lunar'), __('Plată lunară la clinică'), 'monthly'], 6 => [__('6 luni'), __('Preț total pentru 6 luni'), 'm6'], 12 => [__('12 luni'), __('Preț total pentru 12 luni'), 'm12']] as $months => [$title, $note, $key])
                        <label class="cfg-period" :class="{ 'is-selected': form.payment_period_months === {{ $months }} }">
                            <input type="radio" name="hv_payment_period" value="{{ $months }}" x-model.number="form.payment_period_months" :disabled="sent">
                            <span class="cfg-period-title">{{ $title }}</span>
                            <span class="cfg-period-price"><strong x-text="offer ? offer.final.{{ $key }} : '–'"></strong> {{ __('lei') }}</span>
                            <span class="cfg-period-note">{{ $note }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="notice notice--ok" x-show="sent" x-cloak role="status">
                    <strong x-text="result ? result.message : ''"></strong><br>
                    {!! __('Contract nr. :number. Te așteptăm la clinică până la :date pentru plată.', ['number' => '<span x-text="result ? result.number : \'\'"></span>', 'date' => '<span x-text="result ? result.valid_until : \'\'"></span>']) !!}
                    <span x-show="result && result.account_created">{{ __('Ți-am trimis pe email linkul pentru activarea contului.') }}</span>
                </div>
                <div class="cfg-actions">
                    <button class="btn btn-primary" type="button" @click="send()" :disabled="loading || sent" x-text="sent ? @js(__('Abonament înregistrat')) : (loading ? @js(__('Se înregistrează…')) : @js(__('Confirm și plătesc la clinică')))">{{ __('Confirm și plătesc la clinică') }}</button>
                    <button class="btn btn-ghost" type="button" @click="edit()" x-show="!sent">{{ __('Corectează datele') }}</button>
                    <a class="btn btn-dark" :href="result ? result.account_url : '#'" x-show="sent" x-cloak>{{ __('Vezi contul meu') }}</a>
                </div>
            </section>

            <footer class="cfg-foot" x-show="step < 6">
                <button class="btn btn-ghost" type="button" @click="back()" :disabled="step === 1">{{ __('Înapoi') }}</button>
                <button class="btn btn-dark" type="button" @click="next()" x-show="step < 5" :disabled="loading" x-text="step === 4 ? (loading ? @js(__('Se calculează…')) : @js(__('Calculează oferta'))) : @js(__('Continuă'))">{{ __('Continuă') }}</button>
                <button class="btn btn-primary" type="button" @click="openPreview()" x-show="step === 5" x-cloak>{{ __('Semnează și generează contractul') }}</button>
                <button class="btn btn-ghost btn-sm" type="button" @click="reset()" style="margin-left:auto">{{ __('Reîncepe') }}</button>
            </footer>

            <div class="modal" x-show="previewOpen" x-cloak @keydown.escape.window="previewOpen = false">
                <div class="modal-backdrop" @click="previewOpen = false"></div>
                <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="hv-preview-title" x-trap.noscroll="previewOpen">
                    <h3 class="h-sm" id="hv-preview-title">{{ __('Previzualizare contract') }}</h3>
                    <div class="prose" style="margin:1rem 0">
                        <p><strong>{{ __('CONTRACT ABONAMENT MEDICAL VETERINAR') }}</strong></p>
                        <p><strong>{{ __('Beneficiar:') }}</strong> <span x-text="form.owner_name"></span>, <span x-text="form.owner_email"></span>, <span x-text="form.owner_phone"></span></p>
                        <p><strong>{{ __('Animal:') }}</strong> <span x-text="form.pet_name"></span>, <span x-text="form.species === 'dog' ? @js(__('Câine')) : @js(__('Pisică'))"></span>, <span x-text="breedLabel"></span>, <span x-text="form.sex === 'male' ? @js(__('Mascul')) : @js(__('Femelă'))"></span></p>
                        <p><strong>{{ __('Categorie:') }}</strong> <span x-text="offer ? offer.tier_label : ''"></span>. <strong>{{ __('Preț lunar final:') }}</strong> {!! __(':amount lei.', ['amount' => '<span x-text="offer ? offer.final.monthly : \'\'"></span>']) !!}</p>
                        <p><strong>{{ __('Regula pentru mai multe animale:') }}</strong> <span x-text="offer ? offer.discount_label : ''"></span></p>
                        <p class="notice"><strong>{{ __('Important:') }}</strong> {{ __('ai la dispoziție :days zile de la data semnării online pentru a te prezenta la clinică și a efectua plata.', ['days' => $list->contract_valid_days]) }} {{ __('În caz contrar, contractul și oferta se anulează automat.') }}</p>
                        <p>{{ __('PDF-ul complet se generează la trimitere.') }}</p>
                    </div>
                    <div class="cfg-actions">
                        <button class="btn btn-ghost" type="button" @click="previewOpen = false">{{ __('Corectează datele') }}</button>
                        <button class="btn btn-primary" type="button" @click="confirmPreview()" x-ref="confirm">{{ __('Confirm contractul și continui') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section section--cobalt" id="wallet">
    <div class="wrap split" style="align-items:center">
        <div>
            <h2 class="display h-lg">{{ __('Wallet-ul HiKeVet') }}</h2>
            <p class="measure" style="margin-top:1.25rem">{{ __('Cu abonament activ poți pune bani deoparte în Wallet. Pentru fiecare leu depus primești încă un leu bonus pentru servicii și jumătate de leu bonus pentru produse.') }}</p>
            <ul class="ticks">
                <li><x-hv-icon name="check" /> {{ \App\Services\Payments\StripeCheckout::enabled() ? __('Alimentezi la clinică sau online, cu cardul, din contul tău.') : __('Alimentezi la clinică.') }}</li>
                <li><x-hv-icon name="check" /> {{ __('Soldul și fiecare plată apar în contul tău de client.') }}</li>
                <li><x-hv-icon name="check" /> {{ __('Punctele și bonusurile nu sunt bani și nu se pot retrage în numerar.') }}</li>
            </ul>
            <a class="btn btn-primary" href="{{ site_url(auth()->check() ? '/panou-cont/' : '/acces-cont/') }}">{{ auth()->check() ? __('Mergi la Wallet-ul tău') : __('Intră în cont') }}</a>
        </div>
        @include('partials.wallet-calculator')
    </div>
</section>

<section class="section">
    <div class="wrap split" style="align-items:start">
        <div>
            <h2 class="display h-md">{{ __('Mai multe animăluțe, preț mai mic') }}</h2>
            <p style="margin-top:1.25rem">{{ __('Primul animal are prețul standard al abonamentului, conform categoriei lui de greutate.') }}</p>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">{{ __('Animal') }}</th><th scope="col">{{ __('Aceeași categorie de kg') }}</th><th scope="col">{{ __('Categorie diferită') }}</th></tr></thead>
                    <tbody>
                        @foreach ($list->multi_pet_rules['different_tier_percent'] as $ordinal => $percent)
                            @php
                                $andNext = (int) $ordinal === (int) $list->multi_pet_rules['max_ordinal'];
                                $ordinalLabel = match ((string) $ordinal) {
                                    '2' => $andNext ? __('Al doilea și următoarele') : __('Al doilea'),
                                    '3' => $andNext ? __('Al treilea și următoarele') : __('Al treilea'),
                                    '4' => $andNext ? __('Al patrulea și următoarele') : __('Al patrulea'),
                                    default => $andNext ? __('Al :number-lea și următoarele', ['number' => $ordinal]) : __('Al :number-lea', ['number' => $ordinal]),
                                };
                            @endphp
                            <tr><td>{{ $ordinalLabel }}</td>
                                <td>{{ __(':percent% reducere', ['percent' => $list->multi_pet_rules['same_tier_percent']]) }}</td><td>{{ __(':percent% reducere', ['percent' => $percent]) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="hint" style="margin-top:.75rem">{{ __('Reducerea se calculează automat pentru animalele din același cont de client.') }}</p>
        </div>
        <div>
            <h2 class="display h-md">{{ __('Ai nelămuriri?') }}</h2>
            <div class="prose" style="margin-top:1.25rem">{!! $b['closing'] ?? '' !!}</div>
            <p><a class="btn btn-dark" href="{{ site_url('/hikevet-contact/') }}">{{ __('Contactează-ne') }}</a></p>
        </div>
    </div>
</section>
@endsection
