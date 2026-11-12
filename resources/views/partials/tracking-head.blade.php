{{-- Google Tag Manager se incarca doar dupa acordul vizitatorului (vezi resources/js/site.js). --}}
@if ($clinic['gtm_id'] || $clinic['ga4_id'])
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' });
    window.hvTracking = { gtm: @json($clinic['gtm_id']), ga4: @json($clinic['ga4_id']) };
</script>
@endif
