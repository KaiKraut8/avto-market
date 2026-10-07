// The welcome panel: tabs between sign up and log in, and "Skip for now", which hides it
// until the browser is closed (a cookie without an expiry date).
const welcome = document.querySelector('[data-welcome]');

if (welcome) {
    const panel = welcome.querySelector('.welcome-panel');
    const tabs = [...welcome.querySelectorAll('[data-welcome-tab]')];

    const show = (name, focus = true) => {
        tabs.forEach((t) => {
            const on = t.dataset.welcomeTab === name;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
        });
        if (focus) welcome.querySelector(`#wp-${name} input:not([type=hidden])`)?.focus({ preventScroll: true });
    };
    tabs.forEach((t) => t.addEventListener('click', () => show(t.dataset.welcomeTab)));

    const skip = () => {
        document.cookie = 'kai_auth_skipped=1; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        welcome.classList.add('leaving');
        document.documentElement.classList.remove('welcome-open');
        setTimeout(() => welcome.remove(), 350);
    };
    welcome.querySelectorAll('[data-welcome-skip]').forEach((b) => b.addEventListener('click', skip));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && welcome.isConnected) skip(); });

    document.documentElement.classList.add('welcome-open');   // the page behind doesn't scroll
    // after a failed attempt, put the cursor in the first empty field
    if (welcome.hasAttribute('data-instant')) {
        const open = welcome.querySelector('.welcome-form:not([hidden])');
        open?.querySelector('input:not([type=hidden]):not([value]), input[value=""]')?.focus({ preventScroll: true });
    } else {
        panel.focus?.();
    }
}
