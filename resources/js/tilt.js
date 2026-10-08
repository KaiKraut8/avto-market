// Depth for pointer devices: cards tilt toward the pointer, hero layers slide at their own depth.
// Nothing moves on touch screens or for people who prefer reduced motion.
const still = matchMedia('(prefers-reduced-motion: reduce)').matches || !matchMedia('(pointer: fine)').matches;

if (!still) {
    document.querySelectorAll('[data-tilt]').forEach((el) => {
        const max = parseFloat(el.dataset.tiltMax || '9');
        el.classList.add('tilt');
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
    });

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
