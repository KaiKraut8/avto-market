// The cookie notice: "OK" remembers for a year that it was seen, so it isn't shown again
const notice = document.querySelector('[data-cookie-notice]');

notice?.querySelector('[data-cookie-ok]').addEventListener('click', () => {
    document.cookie = 'kai_cookies_seen=1; max-age=31536000; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    notice.remove();
});
