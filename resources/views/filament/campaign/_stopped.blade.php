@unless (\App\Services\Campaign\CampaignMailer::active())
    <div class="hv-cp-flash hv-cp-flash--warn"><strong>Campania de sterilizare este oprită și rămâne ca istoric.</strong>
        Nu se mai fac programări, nu se trimite niciun email (nici automat, nici manual) și nu se mai încarcă fotografii de pe telefon.
        Dosarele, documentele și rapoartele pentru Primărie rămân disponibile.</div>
@endunless
