import { post } from './csrf';

// Wishlist hearts: one click handler for every .heart button on any page
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.heart');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    if (btn.disabled) return;
    btn.disabled = true;

    try {
        const res = await post(btn.dataset.url);
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Request failed');

        // every heart for this car on the page follows along
        document.querySelectorAll(`.heart[data-car="${btn.dataset.car}"]`).forEach((h) => {
            h.setAttribute('aria-pressed', String(data.wished));
            h.setAttribute('aria-label', data.tooltip);
            h.title = data.tooltip;
            const label = h.querySelector('.heart-label');
            if (label) label.textContent = data.label;
            h.classList.remove('pop');
            void h.offsetWidth;            // restart the animation
            if (data.wished) h.classList.add('pop');
        });

        document.querySelectorAll('[data-wish-count]').forEach((el) => {
            el.textContent = data.count;
            el.hidden = data.count === 0;
        });

        // on the wishlist page, a removed car leaves the list
        if (!data.wished) {
            const card = btn.closest('[data-remove-on-unwish]');
            if (card) {
                card.classList.add('leaving');
                setTimeout(() => {
                    card.remove();
                    const empty = document.getElementById('wish-empty');
                    if (empty && !document.querySelector('[data-remove-on-unwish]')) empty.hidden = false;
                }, 300);
            }
        }
    } catch (err) {
        btn.classList.add('shake');
        setTimeout(() => btn.classList.remove('shake'), 400);
    } finally {
        btn.disabled = false;
    }
});
