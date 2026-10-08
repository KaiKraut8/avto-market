// The chat assistant in the corner. A welcome bubble shows on arrival; left alone for 5 seconds it folds
// into a round button that can be dragged anywhere. Clicking opens the chat; the chat can be hidden again.
// The circle's position and the conversation are kept for the rest of the browser session.
const root = document.querySelector('[data-assistant]');

if (root) {
    const bubble = root.querySelector('[data-assistant-bubble]');
    const circle = root.querySelector('[data-assistant-circle]');
    const chat = root.querySelector('[data-assistant-chat]');
    const log = root.querySelector('[data-assistant-log]');
    const form = root.querySelector('[data-assistant-form]');
    const input = form.querySelector('input');
    const dot = root.querySelector('[data-assistant-dot]');
    const i18n = root.querySelector('[data-assistant-i18n]').dataset;
    const store = (k, v) => { try { v === undefined ? sessionStorage.removeItem(k) : sessionStorage.setItem(k, JSON.stringify(v)); } catch {} };
    const load = (k, d) => { try { return JSON.parse(sessionStorage.getItem(k)) ?? d; } catch { return d; } };

    let history = load('kai_assistant_history', []);
    let bubbleTimer = null;

    // ---- position: the circle can be dragged; it remembers where it was put ----
    const place = (x, y) => {
        const w = root.offsetWidth, h = root.offsetHeight;
        x = Math.min(Math.max(8, x), innerWidth - w - 8);
        y = Math.min(Math.max(8, y), innerHeight - h - 8);
        root.style.left = x + 'px'; root.style.top = y + 'px'; root.style.right = 'auto'; root.style.bottom = 'auto';
        root.classList.toggle('on-left', x + w / 2 < innerWidth / 2);
        root.classList.toggle('on-top', y + h / 2 < innerHeight / 2);
        return { x, y };
    };
    const saved = load('kai_assistant_pos', null);
    root.hidden = false;
    if (saved) place(saved.x, saved.y);
    else {
        // bottom right, above the cookie notice if it is showing
        const notice = document.querySelector('[data-cookie-notice]');
        const lift = notice && innerWidth < 1100 ? notice.offsetHeight + 16 : 0;
        place(innerWidth - root.offsetWidth - 20, innerHeight - root.offsetHeight - 20 - lift);
    }
    addEventListener('resize', () => { const r = root.getBoundingClientRect(); place(r.left, r.top); });

    let drag = null;
    circle.addEventListener('pointerdown', (e) => {
        if (e.button !== 0) return;
        const r = root.getBoundingClientRect();
        drag = { dx: e.clientX - r.left, dy: e.clientY - r.top, moved: false };
        circle.setPointerCapture(e.pointerId);
    });
    circle.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const x = e.clientX - drag.dx, y = e.clientY - drag.dy;
        const r = root.getBoundingClientRect();
        if (!drag.moved && Math.abs(x - r.left) + Math.abs(y - r.top) < 6) return;
        drag.moved = true;
        root.classList.add('dragging');
        place(x, y);
    });
    const endDrag = () => {
        if (!drag) return;
        if (drag.moved) { const r = root.getBoundingClientRect(); store('kai_assistant_pos', place(r.left, r.top)); }
        else open();
        root.classList.remove('dragging');
        drag = null;
    };
    circle.addEventListener('pointerup', endDrag);
    circle.addEventListener('pointercancel', () => { root.classList.remove('dragging'); drag = null; });

    // ---- the welcome bubble: shown once per session, folds away after 5 s ----
    const hideBubble = () => { clearTimeout(bubbleTimer); bubble.classList.add('gone'); setTimeout(() => bubble.remove(), 350); store('kai_assistant_bubble', 'seen'); };
    if (load('kai_assistant_bubble', null) || history.length) {
        bubble.remove();
    } else {
        root.classList.add('greeting');
        bubbleTimer = setTimeout(() => { root.classList.remove('greeting'); hideBubble(); }, 5000);
    }
    root.querySelector('[data-assistant-dismiss]')?.addEventListener('click', (e) => { e.stopPropagation(); root.classList.remove('greeting'); hideBubble(); });

    // ---- the chat ----
    const add = (role, text, links = []) => {
        const row = document.createElement('div');
        row.className = 'assistant-msg from-' + role;
        const p = document.createElement('p');
        p.textContent = text;
        row.append(p);
        if (links.length) {
            const ul = document.createElement('ul');
            links.forEach(([label, url]) => { const a = document.createElement('a'); a.href = url; a.textContent = label; const li = document.createElement('li'); li.append(a); ul.append(li); });
            row.append(ul);
        }
        log.append(row);
        log.scrollTop = log.scrollHeight;
        return row;
    };
    const render = () => {
        log.replaceChildren();
        if (!history.length) add('assistant', i18n.hello);
        history.forEach((m) => add(m.role, m.content, m.links || []));
    };

    function open() {
        if (bubble.isConnected) { root.classList.remove('greeting'); hideBubble(); }
        chat.hidden = false;
        root.classList.add('open');
        dot.hidden = true;
        render();
        const r = root.getBoundingClientRect();
        place(r.left, r.top);   // keep the open chat on screen
        input.focus({ preventScroll: true });
    }
    const close = () => { chat.hidden = true; root.classList.remove('open'); const r = root.getBoundingClientRect(); place(r.left, r.top); };
    root.querySelector('[data-assistant-open]')?.addEventListener('click', open);
    root.querySelector('[data-assistant-close]').addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !chat.hidden) close(); });

    let busy = false;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text || busy) return;
        input.value = '';
        history.push({ role: 'user', content: text });
        add('user', text);
        const wait = add('assistant', i18n.thinking);
        wait.classList.add('thinking');
        busy = true;
        try {
            const res = await fetch(root.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ messages: history.slice(-12).map(({ role, content }) => ({ role, content })) }),
            });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            wait.remove();
            add('assistant', data.reply, data.links || []);
            history.push({ role: 'assistant', content: data.reply, links: data.links || [] });
            store('kai_assistant_history', history.slice(-30));
            if (chat.hidden) dot.hidden = false;
        } catch {
            wait.remove();
            add('assistant', i18n.error);
            history.pop();
        } finally {
            busy = false;
            input.focus({ preventScroll: true });
        }
    });
}
