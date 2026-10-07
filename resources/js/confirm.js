// Forms with data-confirm ask before they submit (delete car, remove photo)
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) {
        e.preventDefault();
        e.stopImmediatePropagation();
    }
}, true);
