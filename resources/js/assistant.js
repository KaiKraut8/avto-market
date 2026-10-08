// The chat assistant in the corner. A welcome bubble shows on arrival; left alone for 5 seconds it folds
// into a round button that can be dragged anywhere. Clicking opens the chat; the chat can be hidden again.
// The circle starts in the bottom right corner; where it was dragged to and the conversation are kept
// for the rest of the browser session (double-click the circle to send it back to the corner).
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

    // ---- position: bottom right by default; the circle can be dragged anywhere ----
    // The widget is pinned by the circle's distance to the nearest screen edges, so the circle stays put while the
    // bubble or the chat appear and disappear beside it (the chat opens toward the middle of the screen).
    const GAP = 20;
    let pos = load('kai_assistant_circle', null);   // circle's top-left corner, or null for the default corner
    const apply = () => {
        Object.assign(root.style, { left: 'auto', right: 'auto', top: 'auto', bottom: 'auto' });
        if (!pos) {
            root.style.right = GAP + 'px'; root.style.bottom = GAP + 'px';
            root.classList.remove('on-left', 'on-top');
            return;
        }
        const size = circle.offsetWidth;
        const x = Math.min(Math.max(8, pos.x), innerWidth - size - 8);
        const y = Math.min(Math.max(8, pos.y), innerHeight - size - 8);
        const left = x + size / 2 < innerWidth / 2, top = y + size / 2 < innerHeight / 2;
        if (left) root.style.left = x + 'px'; else root.style.right = (innerWidth - x - size) + 'px';
        if (top) root.style.top = y + 'px'; else root.style.bottom = (innerHeight - y - size) + 'px';
        root.classList.toggle('on-left', left);
        root.classList.toggle('on-top', top);
    };
    root.hidden = false;
    apply();
    addEventListener('resize', apply);

    let drag = null, lastTap = 0;
    circle.addEventListener('pointerdown', (e) => {
        if (e.button !== 0) return;
        const r = circle.getBoundingClientRect();
        drag = { dx: e.clientX - r.left, dy: e.clientY - r.top, x0: r.left, y0: r.top, moved: false };
        circle.setPointerCapture(e.pointerId);
    });
    circle.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const x = e.clientX - drag.dx, y = e.clientY - drag.dy;
        if (!drag.moved && Math.abs(x - drag.x0) + Math.abs(y - drag.y0) < 6) return;
        drag.moved = true;
        root.classList.add('dragging');
        pos = { x, y };
        apply();
    });
    const endDrag = () => {
        if (!drag) return;
        if (drag.moved) {
            const r = circle.getBoundingClientRect();
            pos = { x: r.left, y: r.top };
            store('kai_assistant_circle', pos);
        } else if (performance.now() - lastTap < 350) {
            // double tap: back to the corner (the first tap already toggled the chat, undo that)
            pos = null; store('kai_assistant_circle'); apply();
            chat.hidden ? open() : close();
        } else {
            chat.hidden ? open() : close();
        }
        lastTap = drag.moved ? 0 : performance.now();
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
        input.focus({ preventScroll: true });
    }
    const close = () => { chat.hidden = true; root.classList.remove('open'); };
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
