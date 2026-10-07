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

// Gallery thumbnails swap the big photo
document.querySelectorAll('.thumbs img').forEach((img) => {
    img.addEventListener('click', () => {
        const hero = document.getElementById('hero-img');
        if (hero) hero.src = img.src;
    });
});
