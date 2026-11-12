import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { priceCalc } from './price-calc';
import { configurator } from './configurator';
import { signaturePad } from './signature';

window.Alpine = Alpine;
Alpine.plugin(focus);

Alpine.data('priceCalc', priceCalc);
Alpine.data('configurator', configurator);
Alpine.data('signaturePad', signaturePad);

// Acord pentru masurare: Google Tag Manager / GA4 se incarca doar dupa accept.
Alpine.data('consent', () => ({
    visible: false,
    init() {
        const saved = localStorage.getItem('hv-consent');
        if (saved === 'yes') this.load();
        else if (saved !== 'no') this.visible = true;
    },
    choose(accepted) {
        localStorage.setItem('hv-consent', accepted ? 'yes' : 'no');
        this.visible = false;
        if (accepted) this.load();
    },
    load() {
        const cfg = window.hvTracking || {};
        window.gtag('consent', 'update', { analytics_storage: 'granted' });
        const add = (src) => { const s = document.createElement('script'); s.async = true; s.src = src; document.head.appendChild(s); };
        if (cfg.gtm) {
            window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
            add('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(cfg.gtm));
        } else if (cfg.ga4) {
            add('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.ga4));
            window.gtag('js', new Date());
            window.gtag('config', cfg.ga4);
        }
    },
}));

// Evenimente de conversie: un singur push per actiune, fara duplicate.
document.addEventListener('click', (event) => {
    const el = event.target.closest('[data-track]');
    if (el && window.dataLayer) window.dataLayer.push({ event: el.dataset.track });
});
window.hvTrack = (name, params = {}) => { if (window.dataLayer) window.dataLayer.push({ event: name, ...params }); };

Alpine.start();
