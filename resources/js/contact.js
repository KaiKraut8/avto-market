import { t } from './csrf';

// Contact page: copy the phone number or email address with one click
document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(btn.dataset.copy);
            const old = btn.textContent;
            btn.textContent = t('copied');
            btn.classList.add('copied');
            setTimeout(() => { btn.textContent = old; btn.classList.remove('copied'); }, 1600);
        } catch (e) { /* clipboard not allowed: the text is still visible to copy by hand */ }
    });
});
