// Site-wide motion. Headlines arrive word by word, sections rise into view as they scroll in, numbers
// count up, the wallpaper and a progress bar follow the scrolling, and on pointer devices a soft light
// follows the cursor, panels light up along their edge and the big buttons lean toward it. Nothing is
// hidden unless <html> carries the "motion" class, which the layout's <head> sets before the first paint.
const html = document.documentElement;
const reduced = false;   // the motion plays for everyone, whatever the system's reduced-motion setting
const fine = matchMedia('(pointer: fine)').matches;

html.classList.add('motion-js');   // the <head> script drops "motion" again if this never arrives

// Keep in step with the html.motion :is(...) list in app.css
const REVEAL = '.car-card, .offer, .deal-card, .deal-tile, .why-item, .panel, .stat, .alert-row, .my-car, .promo-plan, .feature, '
    + '.section-head, .group-label, .deals-member-bar, .premium-promo, .cta-band, .footer-grid > div, .views-table tbody tr, .empty';
const COUNT = '.hero-facts dd, .stat > b, .views-table .num, .deal-card-price b, .promo-price, .offer-price';
const HEADLINES = '.home-hero h1, .page-title h1, .detail-head h1, .contact-head h1, .premium-head h1, .premium-hero h1, .why-hero h1, '
    + '.auth-card h1, .cancel-box h1, .withdraw-box h1, .test-pay h1';

// ---- headlines arrive word by word ----
// Each run of text becomes <span class="words"> holding one <span class="w"> per word, with the spaces kept
// between them; child elements (a gradient span, an icon, a tag) stay as they are and take a turn in the
// sequence too. Every piece gets its position in --w, which spaces the animation out (see .split in app.css).
const splitWords = (el) => {
    if (el.classList.contains('split')) return;
    let n = 0;
    [...el.childNodes].forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            if (!node.textContent.trim()) return;
            const wrap = document.createElement('span');
            wrap.className = 'words';
            node.textContent.split(/(\s+)/).forEach((part) => {
                if (!part) return;
                if (/^\s+$/.test(part)) { wrap.append(part); return; }
                const w = document.createElement('span');
                w.className = 'w';
                w.textContent = part;
                w.style.setProperty('--w', String(n++));
                wrap.append(w);
            });
            node.replaceWith(wrap);
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            node.style.setProperty('--w', String(n++));
        }
    });
    el.classList.add('split');
};
if (!reduced) document.querySelectorAll(HEADLINES).forEach(splitWords);

// ---- rising into view ----
if (html.classList.contains('motion')) {
    const items = new Set(document.querySelectorAll(REVEAL));

    // a row of cards cascades: each one waits a little longer than the sibling before it
    items.forEach((el) => {
        const sibs = [...el.parentElement.children].filter((s) => items.has(s));
        el.style.setProperty('--d', String(Math.min(sibs.indexOf(el), 8) * 70));
    });

    // the bars on Most watched grow from nothing once their row is in view
    const grow = (bar) => {
        const width = bar.style.width;
        if (!width) return;
        bar.style.transition = 'none';
        bar.style.width = '0';
        bar.getBoundingClientRect();   // apply the zero width before the transition starts
        bar.style.transition = 'width 1.1s cubic-bezier(.2, .8, .2, 1)';
        bar.style.width = width;
    };

    const show = (el, instant) => {
        if (instant) {
            el.setAttribute('data-shown', '');
            el.style.removeProperty('--d');
            return;
        }
        if (el.classList.contains('in') || el.hasAttribute('data-shown')) return;   // the sweep and the observer can both get here
        const delay = parseFloat(el.style.getPropertyValue('--d')) || 0;
        el.classList.add('in');
        el.querySelectorAll('.bar > span').forEach(grow);
        el.querySelectorAll('.section-head h2, h2.big-title').forEach(splitWords);   // the words start now, not at page load
        // once it has settled, the element goes back to its plain styles (hover, tilt) with nothing in the way
        setTimeout(() => {
            el.setAttribute('data-shown', '');
            el.classList.remove('in');
            el.style.removeProperty('--d');
        }, 1000 + delay);
    };

    const revealer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                revealer.unobserve(entry.target);
                show(entry.target, false);
            } else if (entry.boundingClientRect.bottom < 0) {
                // already scrolled past (the browser restored a scroll position): no entrance
                revealer.unobserve(entry.target);
                show(entry.target, true);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px' });
    items.forEach((el) => revealer.observe(el));

    // the observer leaves out the bottom tenth of the screen, where things would appear before they are looked
    // at; at the end of the page (or on a page too short to scroll) nothing can come further up, so whatever
    // is on screen there shows now
    const sweep = () => {
        if (innerHeight + scrollY < html.scrollHeight - 2) return;
        items.forEach((el) => {
            if (el.classList.contains('in') || el.hasAttribute('data-shown') || el.getBoundingClientRect().top >= innerHeight) return;
            revealer.unobserve(el);
            show(el, false);
        });
    };
    addEventListener('scroll', sweep, { passive: true });
    addEventListener('load', sweep);
    addEventListener('resize', sweep, { passive: true });
}

// ---- numbers count up from zero ----
if (!reduced) {
    // a number with optional thousands grouping and up to two decimals, with text around it: "12.345 €", "19,90 €"
    const NUMBER = /^(\D*)(\d{1,3}(?:([.,   ])\d{3})+|\d+)(?:([.,])(\d{1,2}))?(\D*)$/;

    const count = (el) => {
        if (el.childNodes.length !== 1 || el.firstChild.nodeType !== Node.TEXT_NODE) return;
        const text = el.textContent;
        const m = NUMBER.exec(text);
        if (!m) return;
        const [, before, whole, sep, point, decimals, after] = m;
        const target = parseInt(whole.replace(/\D/g, ''), 10);
        if (!target) return;
        const group = (n) => (sep ? String(n).replace(/\B(?=(\d{3})+(?!\d))/g, sep) : String(n));
        const start = performance.now(), duration = 1100;
        let last = text;
        const tick = (now) => {
            if (el.textContent !== last) return;   // something else wrote here (a live update): leave it alone
            const t = Math.min(1, (now - start) / duration), eased = 1 - Math.pow(1 - t, 3);
            last = t < 1 ? before + group(Math.round(target * eased)) + (decimals ? point + decimals : '') + after : text;
            el.textContent = last;
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };

    const counter = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            counter.unobserve(entry.target);
            count(entry.target);
        });
    });
    document.querySelectorAll(COUNT).forEach((el) => counter.observe(el));
}

// ---- the page follows the scrolling: wallpaper depth, progress bar, a denser top bar ----
if (!reduced) {
    const bar = document.createElement('div');
    bar.className = 'scroll-progress';
    bar.setAttribute('aria-hidden', 'true');
    document.body.append(bar);

    let frame = null;
    const update = () => {
        const y = scrollY, range = Math.max(1, html.scrollHeight - innerHeight);
        html.style.setProperty('--sy', Math.min(y, innerHeight * 2).toFixed(0));
        html.style.setProperty('--sp', Math.min(1, y / range).toFixed(4));
        html.toggleAttribute('data-scrolled', y > 24);
        html.toggleAttribute('data-scrolls', range > 200);
        frame = null;
    };
    const schedule = () => { if (!frame) frame = requestAnimationFrame(update); };
    addEventListener('scroll', schedule, { passive: true });
    addEventListener('resize', schedule, { passive: true });
    update();
}

// ---- pointer devices: a soft light follows the cursor, the big buttons lean toward it ----
if (!reduced && fine) {
    const glow = document.createElement('div');
    glow.className = 'cursor-glow';
    glow.setAttribute('aria-hidden', 'true');
    document.body.append(glow);

    let gx = 0, gy = 0, tx = 0, ty = 0, frame = null, seen = false;
    const draw = () => {
        gx += (tx - gx) * 0.12; gy += (ty - gy) * 0.12;
        glow.style.transform = `translate(${gx.toFixed(1)}px, ${gy.toFixed(1)}px)`;
        frame = Math.abs(tx - gx) + Math.abs(ty - gy) > 0.5 ? requestAnimationFrame(draw) : null;
    };
    addEventListener('pointermove', (e) => {
        if (e.pointerType !== 'mouse') return;
        tx = e.clientX; ty = e.clientY;
        if (!seen) { seen = true; gx = tx; gy = ty; glow.classList.add('on'); }
        if (!frame) frame = requestAnimationFrame(draw);
    }, { passive: true });
    document.addEventListener('pointerleave', () => glow.classList.remove('on'));
    document.addEventListener('pointerenter', () => { if (seen) glow.classList.add('on'); });

    // panels: a light along the edge, nearest the pointer
    document.querySelectorAll('.panel').forEach((panel) => {
        panel.addEventListener('pointermove', (e) => {
            const r = panel.getBoundingClientRect();
            panel.style.setProperty('--mx', (e.clientX - r.left).toFixed(0) + 'px');
            panel.style.setProperty('--my', (e.clientY - r.top).toFixed(0) + 'px');
            panel.classList.add('lit');
        });
        panel.addEventListener('pointerleave', () => panel.classList.remove('lit'));
    });

    document.querySelectorAll('.hero-cta .btn, .promo-cta .btn, .cta-actions .btn, .btn.big').forEach((btn) => {
        let x = 0, y = 0, toX = 0, toY = 0, over = false, tick = null;
        const move = () => {
            x += (toX - x) * 0.2; y += (toY - y) * 0.2;
            btn.style.translate = `${x.toFixed(1)}px ${y.toFixed(1)}px`;
            if (Math.abs(toX - x) + Math.abs(toY - y) > 0.3) {
                tick = requestAnimationFrame(move);
            } else {
                tick = null;
                if (!over) { x = y = 0; btn.style.translate = ''; }
            }
        };
        btn.addEventListener('pointermove', (e) => {
            const r = btn.getBoundingClientRect();
            over = true;
            toX = (e.clientX - r.left - r.width / 2) * 0.22;
            toY = (e.clientY - r.top - r.height / 2) * 0.3;
            if (!tick) tick = requestAnimationFrame(move);
        });
        btn.addEventListener('pointerleave', () => {
            over = false; toX = toY = 0;
            if (!tick) tick = requestAnimationFrame(move);
        });
    });
}
