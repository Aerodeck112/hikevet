// Interactiuni mici cu Raza si Hiko, pe tot site-ul. Toate se opresc (sau raman statice)
// pentru cine are „miscare redusa” activata in sistem.
import { t, intl } from './i18n';
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const say = (buddy, text) => buddy?.dispatchEvent(new CustomEvent('buddy:say', { detail: text }));

// 1. Context: anotimp (caciuli de Mos Craciun in decembrie si pana pe 7 ianuarie) si salut dupa ora.
export function contextLine(kind, now = new Date()) {
    const h = now.getHours();
    const day = now.getDay();
    const m = now.getMonth();
    const d = now.getDate();
    if (m === 11 && d >= 24 && d <= 26) return kind === 'cat' ? t('Crăciun fericit! Miau!') : t('Crăciun fericit! Ham-ham!');
    if ((m === 11 && d === 31) || (m === 0 && d === 1)) return t('La mulți ani! Fără artificii lângă noi, te rog.');
    if (day === 0) return t('E duminică. Cererile le citim de luni dimineață.');
    if (h < 6) return kind === 'cat' ? t('Zzz… e noapte. Și tu ești treaz?') : t('E noapte, dar eu păzesc clinica.');
    if (h < 11) return kind === 'cat' ? t('Bună dimineața! Încă îmi beau laptele.') : t('Bună dimineața! Am alergat deja de două ori prin curte.');
    if (h >= 20) return kind === 'cat' ? t('Bună seara! Eu abia acum mă trezesc.') : t('Bună seara! Clinica s-a închis, dar cererile le primim.');
    return null;
}

function festive(now = new Date()) {
    return (now.getMonth() === 11) || (now.getMonth() === 0 && now.getDate() <= 7);
}

// 2. Mangaiere: miscarea cursorului inainte-inapoi peste personaj aduce inimioare.
function initPetting() {
    const lines = { cat: [t('Purrr…'), t('Mai la stânga. Acolo!'), t('Bine, încă puțin.')], dog: [t('Ce bine e!'), t('Nu te opri!'), t('Coada mea nu se mai oprește.')] };
    document.addEventListener('pointermove', (e) => {
        if (e.pointerType !== 'mouse') return;
        const hit = e.target.closest?.('.buddy-hit, .peek-dog, .perch-cat');
        if (!hit) return;
        const s = hit._pet || (hit._pet = { dir: 0, x: e.clientX, flips: 0, since: performance.now(), cooldown: 0 });
        const dx = e.clientX - s.x;
        s.x = e.clientX;
        if (Math.abs(dx) < 3) return;
        const dir = Math.sign(dx);
        const now = performance.now();
        if (now - s.since > 1400) { s.flips = 0; s.since = now; }
        if (dir !== s.dir) { s.flips++; s.dir = dir; }
        if (s.flips >= 5 && now > s.cooldown) {
            s.flips = 0;
            s.cooldown = now + 2500;
            hearts(hit, e.clientX, e.clientY);
            const buddy = hit.closest('[data-buddy]');
            const kind = buddy?.classList.contains('buddy--dog') || hit.classList.contains('peek-dog') ? 'dog' : 'cat';
            const pool = lines[kind];
            say(buddy, pool[Math.floor(Math.random() * pool.length)]);
        }
    }, { passive: true });
}

function hearts(origin, x, y) {
    const n = reduced() ? 1 : 6;
    for (let i = 0; i < n; i++) {
        const h = document.createElement('span');
        h.className = 'pet-heart';
        h.style.left = x + (Math.random() * 40 - 20) + 'px';
        h.style.top = y - 10 + 'px';
        h.style.setProperty('--dx', (Math.random() * 60 - 30).toFixed(0) + 'px');
        h.style.animationDelay = i * 90 + 'ms';
        document.body.appendChild(h);
        setTimeout(() => h.remove(), 1800 + i * 90);
    }
}

// 3. Recompensa pentru Hiko, pe prima pagina. Numaram cate a primit azi (doar in browserul tau).
function initTreats() {
    const button = document.querySelector('[data-treat]');
    const dog = document.querySelector('.peek-dog');
    if (!button || !dog) return;
    const key = 'hv-treats-' + new Date().toISOString().slice(0, 10);
    const counter = button.querySelector('[data-treat-count]');
    let count = Number(localStorage.getItem(key) || 0);
    const render = () => { if (counter) counter.textContent = count ? t(':count azi', { count }) : ''; };
    render();
    const lines = [t('Mmm! Mulțumesc!'), t('Încă una? Te rog!'), t('Ultima, promit!'), t('Bine, ultima-ultima.'), t('Gata! Medicul zice că ajunge. Sănătate întâi!')];

    button.addEventListener('click', () => {
        if (count >= 5) {
            woof(dog, t('Gata pe azi. Mâine primesc iar!'));
            return;
        }
        count++;
        localStorage.setItem(key, String(count));
        render();
        const done = () => {
            dog.classList.remove('is-chomping');
            void dog.offsetWidth;
            dog.classList.add('is-chomping');
            woof(dog, lines[Math.min(count, lines.length) - 1]);
            if (count === 5) button.disabled = true;
        };
        if (reduced()) return done();
        const from = button.getBoundingClientRect();
        const to = dog.getBoundingClientRect();
        const bone = document.createElement('span');
        bone.className = 'flying-bone';
        bone.style.left = from.left + from.width / 2 + 'px';
        bone.style.top = from.top + from.height / 2 + 'px';
        bone.style.setProperty('--tx', to.left + to.width / 2 - (from.left + from.width / 2) + 'px');
        bone.style.setProperty('--ty', to.top + to.height * 0.62 - (from.top + from.height / 2) + 'px');
        document.body.appendChild(bone);
        bone.addEventListener('animationend', () => { bone.remove(); done(); }, { once: true });
    });
    if (count >= 5) button.disabled = true;
}

function woof(dogButton, text) {
    const bubble = dogButton.querySelector('.woof');
    if (!bubble) return;
    bubble.textContent = text;
    dogButton.classList.remove('is-barking');
    void dogButton.offsetWidth;
    dogButton.classList.add('is-barking');
    clearTimeout(dogButton._barkTimer);
    dogButton._barkTimer = setTimeout(() => dogButton.classList.remove('is-barking'), 1400 + text.length * 45);
}

// 4. Hiko alearga pe o bara cat citesti un articol.
function initReadingDog() {
    const article = document.querySelector('[data-reading]');
    if (!article) return;
    const bar = document.createElement('div');
    bar.className = 'read-track';
    bar.setAttribute('aria-hidden', 'true');
    bar.innerHTML = '<span class="read-fill"></span><span class="read-dog"></span>';
    document.body.appendChild(bar);
    const fill = bar.querySelector('.read-fill');
    const dog = bar.querySelector('.read-dog');
    let finished = false;
    const update = () => {
        const box = article.getBoundingClientRect();
        const total = box.height - window.innerHeight * 0.6;
        const p = Math.min(1, Math.max(0, -box.top / Math.max(1, total)));
        fill.style.transform = `scaleX(${p})`;
        dog.style.left = `calc(${(p * 100).toFixed(2)}% - ${(p * 1.9).toFixed(2)}rem)`;
        bar.classList.toggle('is-on', box.top < 0 && p < 1.02);
        if (p >= 0.99 && !finished) {
            finished = true;
            bar.classList.add('is-done');
            const buddy = document.querySelector('.head-buddy');
            say(buddy, t('Ai citit tot! Bravo, primești o lăbuță.') + ' 🐾');
        }
    };
    window.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
    update();
}

// 5. Click pe antetul turcoaz lasa urme de labute.
function initPawStamps() {
    document.addEventListener('click', (e) => {
        const panel = e.target.closest?.('.page-panel');
        if (!panel || e.target.closest('a, button, input, select, textarea, label, [data-buddy]')) return;
        const box = panel.getBoundingClientRect();
        const paw = document.createElement('span');
        paw.className = 'paw-stamp';
        paw.style.left = e.clientX - box.left + 'px';
        paw.style.top = e.clientY - box.top + 'px';
        paw.style.setProperty('--r', Math.round(Math.random() * 60 - 30) + 'deg');
        panel.appendChild(paw);
        setTimeout(() => paw.remove(), 2600);
    });
}

// 6. Cuvinte secrete: scrie „miau” sau „ham” oriunde (nu intr-un camp de formular).
function initSecretWords() {
    let typed = '';
    document.addEventListener('keydown', (e) => {
        if (e.target.closest?.('input, textarea, select, [contenteditable]') || e.key.length !== 1) return;
        typed = (typed + e.key.toLowerCase()).slice(-6);
        // „miau”/„ham” in romana, „meow”/„woof” in engleza, „miaú”/„vau” in maghiara
        const kind = /(miau|meow|miaú|nyau)$/.test(typed) ? 'cat' : /(ham|woof|vau)$/.test(typed) ? 'dog' : null;
        if (!kind) return;
        typed = '';
        let pop = document.querySelector(`.secret-pop--${kind}`);
        if (!pop) {
            const tpl = document.getElementById(`secret-${kind}`);
            if (!tpl) return;
            pop = tpl.content.firstElementChild.cloneNode(true);
            document.body.appendChild(pop);
        }
        pop.classList.remove('is-up');
        void pop.offsetWidth;
        pop.classList.add('is-up');
        pop.querySelector('.secret-say').textContent = kind === 'cat' ? t('Mă cheamă cineva? Miau!') : t('M-ai strigat? Ham-ham!');
        clearTimeout(pop._t);
        pop._t = setTimeout(() => pop.classList.remove('is-up'), 3200);
    });
}

export function initFun() {
    if (festive()) document.documentElement.classList.add('is-festive');
    initPetting();
    initTreats();
    initReadingDog();
    initPawStamps();
    initSecretWords();
}
