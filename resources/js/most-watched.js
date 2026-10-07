// Most watched: refresh the numbers every 10 s without reloading
const table = document.querySelector('[data-stats-url]');
if (table) {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    const refresh = async () => {
        try {
            const { cars, updated } = await (await fetch(table.dataset.statsUrl, { cache: 'no-store', headers: { Accept: 'application/json' } })).json();
            const max = Math.max(1, ...cars.map((c) => c.people));
            let total = 0, people = 0, watching = 0;
            for (const c of cars) {
                total += c.total; people += c.people; watching += c.watching;
                const row = table.querySelector(`tr[data-id="${c.id}"]`);
                if (!row) continue;
                row.querySelector('[data-f="people"]').textContent = c.people;
                row.querySelector('[data-f="total"]').textContent = c.total;
                row.querySelector('[data-f="watching"]').textContent = c.watching;
                row.querySelector('[data-f="dot"]').classList.toggle('idle', c.watching === 0);
                row.querySelector('[data-f="bar"]').style.width = Math.round((c.people / max) * 100) + '%';
                row.querySelector('[data-f="last"]').textContent = c.last_viewed;
            }
            set('sum-total', total); set('sum-people', people); set('sum-watching', watching);
            set('updated-at', updated);
        } catch (e) { /* try again next tick */ }
    };
    setInterval(refresh, 10000);
}
