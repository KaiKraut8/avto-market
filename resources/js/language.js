// Language picker: close the menu when clicking elsewhere or pressing Escape
const menu = document.querySelector('.lang-menu');
if (menu) {
    document.addEventListener('click', (e) => { if (!menu.contains(e.target)) menu.open = false; });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') menu.open = false; });
}
