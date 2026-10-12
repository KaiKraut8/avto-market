// The weather next to the menu button. The page arrives with Maribor's (where the garage is). The browser is
// asked once for the visitor's location; if they allow it, the weather at their nearest ARSO station replaces it,
// now and on every later page. The location is rounded to about 1 km before it leaves the browser, and it is kept
// only in this browser (localStorage "kai_weather_near"), as {lat, lon}, or {off: true} once the visitor said no.
// The widget is fetched again every 15 minutes while the page is open.
const slot = document.querySelector('[data-weather-url]');

if (slot) {
    const KEY = 'kai_weather_near';
    const EVERY = 15 * 60 * 1000;
    const html = document.documentElement;
    const read = () => { try { return JSON.parse(localStorage.getItem(KEY)); } catch (e) { return null; } };
    const save = (value) => { try { localStorage.setItem(KEY, JSON.stringify(value)); } catch (e) { /* private mode: ask again next time */ } };

    let near = read();
    let last = Date.now();
    const known = () => near && typeof near.lat === 'number' && typeof near.lon === 'number';

    // Maribor's widget stays hidden (the "weather-near" class, set in <head>) only until the local one is in, or failed
    const ready = () => html.classList.remove('weather-near');

    const refresh = async () => {
        last = Date.now();
        const url = new URL(slot.dataset.weatherUrl, location.href);
        if (known()) {
            url.searchParams.set('lat', near.lat.toFixed(2));
            url.searchParams.set('lon', near.lon.toFixed(2));
        }
        const abort = new AbortController();
        const timer = setTimeout(() => abort.abort(), 8000);
        try {
            const res = await fetch(url, { cache: 'no-store', headers: { Accept: 'application/json' }, signal: abort.signal });
            if (res.ok) {
                const { html: markup } = await res.json();
                if (typeof markup === 'string') slot.innerHTML = markup;   // our own markup; empty when there is no recent reading
            }
        } catch (e) { /* offline or slow: keep what is shown */ }
        clearTimeout(timer);
        ready();
    };

    // asks the browser where the visitor is (the first time with the permission prompt); refreshes when the spot changed
    const locate = () => {
        navigator.geolocation.getCurrentPosition((pos) => {
            const spot = { lat: Math.round(pos.coords.latitude * 100) / 100, lon: Math.round(pos.coords.longitude * 100) / 100 };
            const moved = !known() || spot.lat !== near.lat || spot.lon !== near.lon;
            near = spot;
            save(near);
            if (moved) refresh();
        }, (err) => {
            if (err.code === err.PERMISSION_DENIED) {   // said no, or closed the prompt: don't ask again, back to Maribor
                const had = known();
                near = { off: true };
                save(near);
                if (had) refresh();
            }
            ready();
        }, { maximumAge: 30 * 60 * 1000, timeout: 15000 });
    };

    if (known()) refresh();   // the remembered spot right away, without waiting for the browser

    if ('geolocation' in navigator) {
        const forget = () => { near = { off: true }; save(near); refresh(); };
        const decide = (state, sure) => {
            if (state === 'granted') locate();                        // allowed before: check whether the visitor moved
            else if (known() && sure) forget();                       // permission taken back in the browser: back to Maribor
            else if (!near) locate();                                 // never asked: ask once
            else ready();
        };
        if (navigator.permissions?.query) {
            navigator.permissions.query({ name: 'geolocation' }).then((p) => decide(p.state, true), () => decide('prompt', false));
        } else {
            decide('prompt', false);                                  // can't tell: a remembered spot keeps being used
        }
    } else {
        ready();
    }

    setInterval(() => { if (!document.hidden) refresh(); }, EVERY);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - last >= EVERY) refresh();
    });
}
