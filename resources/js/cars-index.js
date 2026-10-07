import { t } from './csrf';

// The whole card opens the car. Links, buttons and the views panel keep their own behaviour,
// selecting text doesn't navigate, and Ctrl/Cmd/middle click opens it in a new tab.
document.querySelectorAll('.car-card[data-href]').forEach((card) => {
    const open = (e) => {
        if (e.target.closest('a, button, .views-pop, form, input')) return;
        if (String(window.getSelection())) return;
        if (e.ctrlKey || e.metaKey || e.button === 1) {
            window.open(card.dataset.href, '_blank');
        } else {
            location.href = card.dataset.href;
        }
    };
    card.addEventListener('click', open);
    card.addEventListener('auxclick', (e) => { if (e.button === 1) open(e); });
});

// Eye button: a small panel with who is watching right now and the view history.
// The sentences come translated from the server.
const timers = new Map();

async function fill(btn, pop) {
    try {
        const res = await fetch(btn.dataset.url, { cache: 'no-store', headers: { Accept: 'application/json' } });
        const d = await res.json();
        const max = Math.max(1, ...d.days.map((x) => x.n));
        pop.replaceChildren();

        const now = document.createElement('div');
        now.className = 'pop-now';
        const dot = document.createElement('span');
        dot.className = 'live-dot' + (d.watching === 0 ? ' idle' : '');
        const num = document.createElement('b');
        num.textContent = d.watching;
        const txt = document.createElement('span');
        txt.textContent = d.text.watching;
        now.append(dot, num, ' ', txt);

        const hist = document.createElement('div');
        hist.className = 'pop-history';
        hist.textContent = d.text.history;

        const chart = document.createElement('div');
        chart.className = 'spark';
        for (const day of d.days) {
            const col = document.createElement('div');
            col.className = 'spark-col';
            col.title = day.title;
            const bar = document.createElement('span');
            bar.style.height = Math.max(4, Math.round((day.n / max) * 100)) + '%';
            if (day.n === 0) bar.className = 'zero';
            const lab = document.createElement('small');
            lab.textContent = day.label;
            col.append(bar, lab);
            chart.append(col);
        }
        const cap = document.createElement('div');
        cap.className = 'spark-cap';
        cap.textContent = d.text.caption;

        pop.append(now, hist, chart, cap);
    } catch (e) {
        pop.textContent = t('views_failed');
    }
}

document.querySelectorAll('.views-toggle').forEach((btn) => {
    const pop = btn.nextElementSibling;
    btn.addEventListener('click', () => {
        const open = pop.hidden;
        pop.hidden = !open;
        btn.setAttribute('aria-expanded', String(open));
        clearInterval(timers.get(btn));
        if (open) {
            pop.textContent = t('loading');
            fill(btn, pop);
            timers.set(btn, setInterval(() => fill(btn, pop), 10000)); // keep "right now" live while open
        }
    });
});
