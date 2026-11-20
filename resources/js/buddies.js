// Personajele de pe paginile interioare (x-buddy): urmaresc cursorul cu privirea,
// apar o singura data cand intra in ecran si spun cate ceva la click. Fara biblioteci.
// Cine are miscarea redusa le vede statice, dar replicile merg in continuare.
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Balonul se deschide spre partea cu mai mult loc si se strange pe latimea textului.
function place(buddy, bubble) {
    const box = buddy.getBoundingClientRect();
    const toRight = buddy.hasAttribute('data-say-right') || box.left + box.width / 2 < window.innerWidth / 2;
    buddy.classList.toggle('buddy--left', toRight);
    const room = toRight ? window.innerWidth - (box.left + box.width * 0.62) : box.left + box.width * 0.38;
    bubble.style.width = '';
    bubble.style.maxWidth = Math.max(140, Math.min(260, room - 14)) + 'px';
    const scale = bubble.getBoundingClientRect().width / (bubble.offsetWidth || 1) || 1;
    const range = document.createRange();
    range.selectNodeContents(bubble.querySelector('.buddy-line') || bubble);
    const widest = Math.max(0, ...[...range.getClientRects()].map((r) => r.width / scale));
    const chrome = bubble.offsetWidth - bubble.clientWidth + parseFloat(getComputedStyle(bubble).paddingLeft) * 2;
    if (widest) bubble.style.width = Math.ceil(widest + chrome + 1) + 'px';
}

function speaker(buddy) {
    const bubble = buddy.querySelector('.buddy-say');
    const lineEl = bubble?.querySelector('.buddy-line') || bubble;
    let lines = [];
    try { lines = JSON.parse(buddy.dataset.say || '[]'); } catch { lines = []; }
    let index = 0;
    let timer;

    return (line) => {
        if (!bubble || !lines.length) return;
        const text = line ?? lines[index++ % lines.length];
        lineEl.textContent = text;
        place(buddy, bubble);
        buddy.classList.remove('is-talking');
        void buddy.offsetWidth;
        buddy.classList.add('is-saying', 'is-talking');
        clearTimeout(timer);
        timer = setTimeout(() => buddy.classList.remove('is-saying', 'is-talking'), 1900 + text.length * 45);
    };
}

export function initBuddies(root = document) {
    const buddies = [...root.querySelectorAll('[data-buddy]:not([data-ready])')];
    if (!buddies.length) return;

    const visible = new Set();
    const speakers = new Map();

    buddies.forEach((buddy) => {
        buddy.dataset.ready = '';
        const say = speaker(buddy);
        speakers.set(buddy, say);
        buddy.querySelector('.buddy-hit')?.addEventListener('click', () => say());
    });

    const io = new IntersectionObserver((entries) => {
        entries.forEach(({ target, isIntersecting }) => {
            target.classList.toggle('is-off', !isIntersecting);
            if (isIntersecting) visible.add(target); else visible.delete(target);
            if (isIntersecting && !target.classList.contains('is-in')) {
                target.classList.add('is-in');
                setTimeout(() => target.classList.add('is-landed'), 900);
                if (target.hasAttribute('data-greet')) setTimeout(() => speakers.get(target)(), reduced() ? 300 : 900);
            }
        });
    }, { threshold: 0.35 });
    buddies.forEach((b) => io.observe(b));

    // Privirea urmareste cursorul (doar mouse/creion; pe telefon nu are sens).
    let pending = null;
    const look = () => {
        const { clientX: x, clientY: y } = pending;
        pending = null;
        visible.forEach((buddy) => {
            const svg = buddy.querySelector('.mascot');
            const pupils = svg?.querySelector('.cat-pupils, .dog-pupils');
            if (!pupils) return;
            const box = svg.getBoundingClientRect();
            const dx = x - (box.left + box.width / 2);
            const dy = y - (box.top + box.height * 0.4);
            const length = Math.hypot(dx, dy) || 1;
            const radius = buddy.classList.contains('buddy--dog') ? 4 : 5;
            const strength = Math.min(1, length / 240);
            pupils.style.transform = `translate(${((dx / length) * radius * strength).toFixed(2)}px, ${((dy / length) * radius * strength).toFixed(2)}px)`;
        });
    };
    window.addEventListener('pointermove', (event) => {
        if (event.pointerType === 'touch' || !visible.size) return;
        if (!pending) requestAnimationFrame(look);
        pending = event;
    }, { passive: true });
}
