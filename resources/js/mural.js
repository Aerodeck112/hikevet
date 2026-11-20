// Animatiile „mural”: personajele de pe prima pagina, turul clinicii si episoadele.
// Se incarca doar pe paginile care le folosesc. Cine are miscarea redusa vede totul static.
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const BARKS = ['Ham!', 'Ham-ham!', 'Mă scarpini?', 'Vin la control!'];

function eyeFollower(svg, pupils, radius) {
    const moveX = gsap.quickTo(pupils, 'x', { duration: 0.35, ease: 'power3.out' });
    const moveY = gsap.quickTo(pupils, 'y', { duration: 0.35, ease: 'power3.out' });
    return (event) => {
        const box = svg.getBoundingClientRect();
        const dx = event.clientX - (box.left + box.width / 2);
        const dy = event.clientY - (box.top + box.height * 0.38);
        const length = Math.hypot(dx, dy) || 1;
        const strength = Math.min(1, length / 220);
        moveX((dx / length) * radius * strength);
        moveY((dy / length) * radius * strength);
    };
}

function initWall(wall) {
    const cat = wall.querySelector('.mascot-cat');
    const dogButton = wall.querySelector('.peek-dog');
    const dog = dogButton?.querySelector('.mascot-dog');
    const woof = dogButton?.querySelector('.woof');
    const mm = gsap.matchMedia();

    gsap.set(cat.querySelectorAll('.cat-lids'), { scaleY: 0, transformOrigin: '50% 0%' });

    let barkIndex = 0;
    const bark = () => {
        if (!dogButton) return;
        woof.textContent = BARKS[barkIndex++ % BARKS.length];
        dogButton.classList.remove('is-barking');
        void dogButton.offsetWidth;
        dogButton.classList.add('is-barking');
        clearTimeout(dogButton._barkTimer);
        dogButton._barkTimer = setTimeout(() => dogButton.classList.remove('is-barking'), 1400);
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            gsap.fromTo(dog.querySelectorAll('.dog-ear'), { rotation: 0 }, { rotation: (i) => (i ? 10 : -10), svgOrigin: '120 90', duration: 0.12, yoyo: true, repeat: 3, ease: 'power1.inOut' });
            gsap.fromTo(dog.querySelector('.dog-head'), { y: 0 }, { y: -8, duration: 0.14, yoyo: true, repeat: 3, ease: 'power2.out' });
        }
    };
    dogButton?.addEventListener('click', bark);

    mm.add('(prefers-reduced-motion: no-preference)', () => {
        const intro = gsap.timeline({ defaults: { ease: 'power3.out' } });
        intro.from('.bubble-shape', { scale: 0.6, autoAlpha: 0, rotation: -6, transformOrigin: '50% 60%', duration: 0.8, ease: 'back.out(1.6)' })
            .from('.bubble h1 span', { yPercent: 60, autoAlpha: 0, stagger: 0.08, duration: 0.6 }, '-=0.45')
            .from('.bubble p, .bubble-actions', { y: 16, autoAlpha: 0, stagger: 0.06, duration: 0.45 }, '-=0.3')
            .from('.poster', { y: 80, rotation: (i) => (i ? 14 : -10), autoAlpha: 0, stagger: 0.12, duration: 0.7 }, 0.15)
            .from('.paint', { scale: 0, autoAlpha: 0, stagger: 0.06, duration: 0.4, ease: 'back.out(2)' }, 0.4)
            .from('.perch-cat', { y: -140, autoAlpha: 0, duration: 0.7, ease: 'bounce.out' }, 0.55)
            .from('.peek-dog', { y: 110, duration: 0.6, ease: 'back.out(1.8)' }, 0.8)
            .from('.sticker-sun', { scale: 0, rotation: -90, duration: 0.5, ease: 'back.out(2.2)' }, 0.9);

        gsap.to(cat.querySelector('.cat-tail'), { rotation: 14, svgOrigin: '150 210', duration: 1.3, yoyo: true, repeat: -1, ease: 'sine.inOut' });
        gsap.to(dog.querySelector('.dog-tongue'), { scaleY: 1.12, transformOrigin: '50% 0%', duration: 0.32, yoyo: true, repeat: -1, ease: 'sine.inOut' });

        const lids = cat.querySelectorAll('.cat-lids');
        const blink = gsap.timeline({ repeat: -1, repeatDelay: 3.2 });
        blink.to(lids, { scaleY: 1, duration: 0.08 }).to(lids, { scaleY: 0, duration: 0.12 });

        const shades = cat.querySelector('.cat-shades');
        let lifted = false;
        const lift = (up) => {
            if (up === lifted) return;
            lifted = up;
            gsap.to(shades, { y: up ? -44 : 0, rotation: up ? -8 : 0, svgOrigin: '110 86', duration: 0.45, ease: up ? 'back.out(2)' : 'power2.inOut' });
        };
        // Pe telefon pisica isi ridica singura ochelarii din cand in cand.
        const peekLoop = gsap.timeline({ repeat: -1, repeatDelay: 5, delay: 3 })
            .call(() => lift(true)).to({}, { duration: 2.2 }).call(() => lift(false));

        const followCat = eyeFollower(cat, cat.querySelector('.cat-pupils'), 5);
        const followDog = eyeFollower(dog, dog.querySelector('.dog-pupils'), 4);
        const onMove = (event) => {
            followCat(event);
            followDog(event);
            const box = cat.getBoundingClientRect();
            const near = Math.hypot(event.clientX - (box.left + box.width / 2), event.clientY - (box.top + box.height / 2)) < 260;
            if (event.pointerType === 'mouse') {
                peekLoop.pause();
                lift(near);
            }
        };
        window.addEventListener('pointermove', onMove, { passive: true });

        const earTwitch = gsap.timeline({ repeat: -1, repeatDelay: 4.5, delay: 2 })
            .to(dog.querySelector('.dog-ear--r'), { rotation: 9, svgOrigin: '178 100', duration: 0.1, yoyo: true, repeat: 1 });

        return () => {
            window.removeEventListener('pointermove', onMove);
            peekLoop.kill();
            earTwitch.kill();
            blink.kill();
        };
    });
}

function initTour(tour) {
    const track = tour.querySelector('.tour-track');
    const pin = tour.querySelector('.tour-pin');
    const counter = tour.querySelector('.tour-count');
    const rooms = track.querySelectorAll('.room');
    const pad = (n) => String(n).padStart(2, '0');
    const mm = gsap.matchMedia();

    mm.add({ wide: '(min-width: 900px) and (prefers-reduced-motion: no-preference)', narrow: '(max-width: 899px), (prefers-reduced-motion: reduce)' }, (context) => {
        if (context.conditions.narrow) {
            tour.classList.add('is-static');
            return () => tour.classList.remove('is-static');
        }
        const distance = () => Math.max(0, track.scrollWidth - window.innerWidth);
        const tween = gsap.to(track, {
            x: () => -distance(),
            ease: 'none',
            scrollTrigger: {
                trigger: pin,
                pin: true,
                start: 'top top',
                end: () => '+=' + distance(),
                scrub: 0.6,
                invalidateOnRefresh: true,
                onUpdate: (self) => {
                    if (counter) counter.textContent = pad(Math.min(rooms.length, Math.round(self.progress * (rooms.length - 1)) + 1)) + ' / ' + pad(rooms.length);
                },
            },
        });
        return () => tween.scrollTrigger?.kill();
    });
}

function initEpisodes(list) {
    list.addEventListener('click', (event) => {
        const button = event.target.closest('.episode-play');
        if (!button) return;
        const episode = button.closest('.episode');
        list.querySelectorAll('.episode.is-playing video').forEach((v) => v.pause());
        let video = episode.querySelector('video');
        if (!video) {
            video = document.createElement('video');
            video.src = button.dataset.src;
            video.poster = button.dataset.poster || '';
            video.controls = true;
            video.playsInline = true;
            video.preload = 'auto';
            video.setAttribute('aria-label', button.getAttribute('aria-label') || 'Episod');
            episode.querySelector('.episode-frame').replaceChildren(video);
        }
        episode.classList.add('is-playing');
        video.play().catch(() => {});
        video.focus();
    });
}

export function initMural() {
    const wall = document.querySelector('[data-wall]');
    if (wall) initWall(wall);
    const tour = document.querySelector('[data-tour]');
    if (tour) initTour(tour);
    const episodes = document.querySelector('[data-episodes]');
    if (episodes) initEpisodes(episodes);
    window.addEventListener('load', () => ScrollTrigger.refresh());
}
