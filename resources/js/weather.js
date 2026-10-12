// The weather next to the menu button. The server keeps it current (a new reading from ARSO every 15 minutes);
// a page left open asks for it again on the same rhythm, and right away when its tab comes back after that long.
const slot = document.querySelector('[data-weather-url]');

if (slot) {
    const EVERY = 15 * 60 * 1000;
    let last = Date.now();

    const refresh = async () => {
        last = Date.now();
        try {
            const res = await fetch(slot.dataset.weatherUrl, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const { html } = await res.json();
            if (typeof html === 'string') slot.innerHTML = html;   // our own markup; empty when there is no recent reading
        } catch (e) { /* offline: keep what is shown and try again next time */ }
    };

    setInterval(() => { if (!document.hidden) refresh(); }, EVERY);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - last >= EVERY) refresh();
    });
}
