import { post, beacon } from './csrf';

// On a car's page: check in every 15 s so it counts as "watching now", check out when leaving
const page = document.querySelector('[data-watch-url]');
if (page) {
    const out = document.getElementById('watching-now');
    const ping = async () => {
        try {
            const res = await post(page.dataset.watchUrl);
            const data = await res.json();
            if (out && typeof data.watching === 'number') out.textContent = data.watching;
        } catch (e) { /* offline: try again on the next tick */ }
    };
    setInterval(ping, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) ping(); });
    addEventListener('pagehide', () => beacon(page.dataset.leaveUrl));
}

// Gallery thumbnails swap the big photo: it fades out, the new one is decoded meanwhile, then fades in.
// The thumbnail that is showing stays lit.
const hero = document.getElementById('hero-img');
const thumbs = [...document.querySelectorAll('.thumbs img')];
const light = () => thumbs.forEach((t) => t.classList.toggle('active', !!hero && t.src === hero.src));
light();
thumbs.forEach((img) => {
    img.addEventListener('click', () => {
        if (!hero || hero.src === img.src) return;
        const next = new Image();
        next.src = img.src;
        hero.classList.add('swapping');
        const decoded = next.decode ? next.decode().catch(() => {}) : Promise.resolve();
        const faded = new Promise((done) => setTimeout(done, 220));
        Promise.all([decoded, faded]).then(() => {
            hero.src = img.src;
            hero.classList.remove('swapping');
            light();
        });
    });
});
