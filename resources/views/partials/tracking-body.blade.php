@if ($clinic['gtm_id'] || $clinic['ga4_id'])
<div class="consent no-print" x-data="consent" x-show="visible" x-cloak role="dialog" aria-label="Acord pentru cookie-uri">
    <p><strong>Cookie-uri de măsurare.</strong> Le folosim doar cu acordul tău, ca să înțelegem cum este folosit site-ul. Site-ul funcționează complet și fără ele.</p>
    <div class="consent-actions">
        <button class="btn btn-ghost btn-sm" type="button" @click="choose(false)">Refuz</button>
        <button class="btn btn-dark btn-sm" type="button" @click="choose(true)">Accept</button>
    </div>
</div>
@endif
