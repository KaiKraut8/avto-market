// Depth for pointer devices: cards tilt toward the pointer, hero layers slide at their own depth.
// Nothing moves on touch screens, where there is no pointer to follow.
const still = !matchMedia('(pointer: fine)').matches;

// Elements that tilt without being marked up: selector → how far they turn, in degrees
const AUTO_TILT = [
    ['.car-card', 4],
    ['.deal-tile', 8],
    ['.promo-plan', 10],
    ['.stat', 8],
    ['.my-car', 6],
    ['.feature', 8],
    ['.alert-row', 4],
];

if (!still) {
    const tilt = (el, max) => {
        if (el.classList.contains('tilt')) return;
        el.classList.add('tilt');
        if (el.dataset.tiltGlare !== '0') el.classList.add('tilt-glare');
        el.addEventListener('pointermove', (e) => {
            const r = el.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
            el.style.setProperty('--rx', (-y * max).toFixed(2) + 'deg');
            el.style.setProperty('--ry', (x * max).toFixed(2) + 'deg');
            el.style.setProperty('--gx', (x * 100 + 50).toFixed(1) + '%');
            el.style.setProperty('--gy', (y * 100 + 50).toFixed(1) + '%');
            el.classList.add('tilting');
        });
        el.addEventListener('pointerleave', () => {
            el.style.setProperty('--rx', '0deg'); el.style.setProperty('--ry', '0deg');
            el.classList.remove('tilting');
        });
    };

    document.querySelectorAll('[data-tilt]').forEach((el) => tilt(el, parseFloat(el.dataset.tiltMax || '9')));
    AUTO_TILT.forEach(([selector, max]) => document.querySelectorAll(selector).forEach((el) => tilt(el, max)));

    const scene = document.querySelector('[data-parallax]');
    if (scene) {
        const layers = [...scene.parentElement.querySelectorAll('[data-depth]')];
        let frame = null, mx = 0, my = 0;
        const draw = () => {
            layers.forEach((l) => {
                const d = parseFloat(l.dataset.depth);
                l.style.setProperty('--px', (mx * d * 24).toFixed(1) + 'px');
                l.style.setProperty('--py', (my * d * 14).toFixed(1) + 'px');
            });
            frame = null;
        };
        scene.parentElement.addEventListener('pointermove', (e) => {
            mx = e.clientX / innerWidth - 0.5; my = e.clientY / innerHeight - 0.5;
            if (!frame) frame = requestAnimationFrame(draw);
        });
    }
}
