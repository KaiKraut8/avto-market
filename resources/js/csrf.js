// Every fetch/beacon the scripts make is a same-site POST that needs Laravel's CSRF token
const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function post(url, data = {}) {
    const body = new URLSearchParams(data);
    return fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body,
    });
}

export function beacon(url, data = {}) {
    const body = new FormData();
    body.append('_token', token);
    for (const [k, v] of Object.entries(data)) body.append(k, v);
    navigator.sendBeacon(url, body);
}

// Translated labels for the scripts, put in the page by the layout
export function t(key) {
    return window.KAI?.i18n?.[key] ?? key;
}
