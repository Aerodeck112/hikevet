@if ($clinic['gtm_id'] || $clinic['ga4_id'])
<div class="consent no-print" x-data="consent" x-show="visible" x-cloak role="dialog" aria-label="{{ __('Acord pentru cookie-uri') }}">
    <x-buddy class="consent-buddy buddy--left" kind="dog" pose="peek" :say="['Fursecuri? Eu prefer recompense.', 'Alegi tu. Eu oricum dau din coadă.']" />
    <p><strong>{{ __('Cookie-uri de măsurare.') }}</strong> {{ __('Le folosim doar cu acordul tău, ca să înțelegem cum este folosit site-ul. Site-ul funcționează complet și fără ele.') }}</p>
    <div class="consent-actions">
        <button class="btn btn-ghost btn-sm" type="button" @click="choose(false)">{{ __('Refuz') }}</button>
        <button class="btn btn-dark btn-sm" type="button" @click="choose(true)">{{ __('Accept') }}</button>
    </div>
</div>
@endif
