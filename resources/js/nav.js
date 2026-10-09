// The menu: the three-line button slides in a panel with all the tabs; the backdrop, × and Escape close it
const toggle = document.querySelector('[data-nav-toggle]');
const topbar = toggle?.closest('.topbar');
const nav = document.getElementById('site-nav');

if (toggle && nav) {
    const setOpen = (open) => {
        if (open === topbar.classList.contains('nav-open')) return;
        topbar.classList.toggle('nav-open', open);
        document.documentElement.classList.toggle('nav-locked', open);   // the page stays put behind the panel
        toggle.setAttribute('aria-expanded', String(open));
        nav.inert = !open;
        if (open) nav.querySelector('.nav-close').focus({ preventScroll: true });
        else if (nav.contains(document.activeElement)) toggle.focus({ preventScroll: true });
    };
    nav.inert = true;   // nothing in the closed panel can be tabbed to
    toggle.addEventListener('click', () => setOpen(true));
    topbar.querySelectorAll('[data-nav-close]').forEach((el) => el.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
}
