// Home page motion: the car in the backdrop drives as the page scrolls, and the featured car card
// can be turned around by dragging. Both stay still for people who prefer reduced motion.
const hero = document.querySelector('[data-hero]');
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

if (hero && !reduced) {
    // --p goes from 0 (top of the page) to 1 (hero scrolled out of view)
    let ticking = false;
    const update = () => {
        const p = Math.min(1, Math.max(0, scrollY / Math.max(1, hero.offsetHeight - 80)));
        hero.style.setProperty('--p', p.toFixed(4));
        ticking = false;
    };
    addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
    update();
}

const card = document.querySelector('[data-flip-card]');
if (card) {
    let rx = 0, ry = 0;            // current rotation
    let vx = 0, vy = 0;            // velocity while the card spins on after a flick
    let dragging = false, moved = false, last = null, frame = null;
    const inner = card.querySelector('.hero-car-inner');

    const render = () => inner.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg)`;

    // after a release the card keeps turning, slows down, then settles on its front or back
    const settle = () => {
        vx *= 0.94; vy *= 0.94;
        rx += vx; ry += vy;
        const targetX = 0;
        const targetY = Math.round(ry / 180) * 180;
        if (Math.abs(vx) < 0.15 && Math.abs(vy) < 0.15) {
            rx += (targetX - rx) * 0.12;
            ry += (targetY - ry) * 0.12;
            if (Math.abs(rx - targetX) < 0.05 && Math.abs(ry - targetY) < 0.05) {
                rx = targetX; ry = ((targetY % 360) + 360) % 360; render(); frame = null;
                return;
            }
        }
        render();
        frame = requestAnimationFrame(settle);
    };

    card.addEventListener('pointerdown', (e) => {
        if (e.button !== 0) return;
        e.preventDefault();   // no text selection or image drag while turning the card
        dragging = true; moved = false; last = { x: e.clientX, y: e.clientY, t: performance.now() };
        vx = vy = 0;
        if (frame) { cancelAnimationFrame(frame); frame = null; }
        card.setPointerCapture(e.pointerId);
        card.classList.add('dragging');
    });
    card.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const dx = e.clientX - last.x, dy = e.clientY - last.y;
        if (Math.abs(dx) + Math.abs(dy) > 4) moved = true;
        ry += dx * 0.6;
        rx = Math.max(-40, Math.min(40, rx - dy * 0.4));
        vy = dx * 0.6; vx = -dy * 0.4;
        last = { x: e.clientX, y: e.clientY, t: performance.now() };
        render();
    });
    const release = () => {
        if (!dragging) return;
        dragging = false;
        card.classList.remove('dragging');
        if (!frame) frame = requestAnimationFrame(settle);
    };
    card.addEventListener('pointerup', release);
    card.addEventListener('pointercancel', release);
    // a drag must not open the car page; a plain click still does
    card.addEventListener('click', (e) => { if (moved) { e.preventDefault(); e.stopPropagation(); } }, true);

    // an idle nudge, so visitors notice the card can turn
    if (!reduced) {
        setTimeout(() => { if (!dragging && !frame && ry === 0) { vy = 3; frame = requestAnimationFrame(settle); } }, 2500);
    }
}
