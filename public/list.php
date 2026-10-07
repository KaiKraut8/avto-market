<?php
require __DIR__ . "/inc.php";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("db", "app", "secret", "app");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Search: by name, description, location, country or part name
$q = trim(mb_substr($_GET["q"] ?? "", 0, 80));

function find_cars(mysqli $conn, string $q): array
{
    $where = "c.deleted_at IS NULL";
    if ($q !== "") {
        $where .= " AND (c.name LIKE ? OR c.description LIKE ? OR c.seller_location LIKE ? OR c.country LIKE ?
                         OR EXISTS (SELECT 1 FROM car_parts sp WHERE sp.car_id = c.id AND sp.part_name LIKE ?))";
    }
    // One query for the list: each car with the names of its parts and its first photo
    $sql = "SELECT c.id, c.name, c.price, " . premium_active_sql() . " AS premium,
               (c.boost_until > NOW()) AS boosted, c.description, c.seller_location, c.country, c.creation_date,
               COUNT(p.id) AS part_count,
               JSON_ARRAYAGG(p.part_name) AS parts,
               (SELECT filename FROM car_photos WHERE car_id = c.id ORDER BY id LIMIT 1) AS photo,
               (SELECT COUNT(DISTINCT visitor_id) FROM car_views v WHERE v.car_id = c.id) AS people,
               EXISTS (SELECT 1 FROM wishlist w WHERE w.car_id = c.id AND w.visitor_id = ?) AS wished
        FROM cars c
        LEFT JOIN car_parts p ON p.car_id = c.id
        WHERE $where
        GROUP BY c.id
        ORDER BY premium DESC, boosted DESC, c.id";
    $visitor = visitor_id();
    $stmt = $conn->prepare($sql);
    if ($q !== "") {
        $like = "%" . addcslashes($q, "%_\\") . "%";   // the search text is matched literally
        $stmt->bind_param("ssssss", $visitor, $like, $like, $like, $like, $like);
    } else {
        $stmt->bind_param("s", $visitor);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$cars = find_cars($conn, $q);
// nothing matched: still offer every car, as other options
$noMatch = $q !== "" && !$cars;
if ($noMatch) {
    $cars = find_cars($conn, "");
}
$conn->close();

// Sellers who bought a premium listing are shown first, in their own block
$groups = [
    "premium" => array_values(array_filter($cars, fn($c) => (int)$c["premium"] === 1)),
    "regular" => array_values(array_filter($cars, fn($c) => (int)$c["premium"] !== 1)),
];
$n = 0;

$quoted = "&ldquo;" . htmlspecialchars($q) . "&rdquo;";

site_header($q !== "" ? "Search: " . $q : "All cars", "list.php");
?>

<main class="wrap">
    <div class="page-title">
        <h1><?= $q !== "" && !$noMatch ? "Results for " . $quoted : "All cars" ?></h1>
<?php if (!$noMatch): ?>
        <span class="count"><?= count($cars) ?> <?= count($cars) === 1 ? "result" : "results" ?></span>
<?php endif; ?>
    </div>

    <form class="search-bar" action="list.php" method="get" role="search">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by make, model, part or location, e.g. BMW, Volan, Ljubljana" aria-label="Search cars" maxlength="80">
<?php if ($q !== ""): ?>
        <a class="search-clear" href="list.php" aria-label="Clear search">&times;</a>
<?php endif; ?>
        <button type="submit" class="btn accent">Search</button>
    </form>

<?php if ($noMatch): ?>
    <div class="empty search-empty">No cars match <?= $quoted ?>. Here are other options you might like.</div>
<?php endif; ?>

<?php if ($cars): ?>
<?php foreach ($groups as $group => $list): if (!$list) continue; ?>
<?php if ($group === "premium"): ?>
    <div class="group-label premium-label"><span class="crown" aria-hidden="true">&#9813;</span> Premium listings</div>
<?php elseif ($groups["premium"] || $q !== ""): ?>
    <div class="group-label"><?= $noMatch ? "Other options you might like" : ($q !== "" ? "Other options related to " . $quoted : "Other options") ?></div>
<?php endif; ?>
    <div class="results <?= $group ?>-results">
<?php foreach ($list as $car):
    $i = $n++;
    $parts = array_values(array_filter(json_decode($car["parts"] ?? "[]", true) ?: [], fn($p) => $p !== null));
    $shown = array_slice($parts, 0, 4);
    $link = "edit.php?id=" . urlencode($car["id"]);
?>
        <article class="car-card<?= (int)$car["premium"] === 1 ? " premium" : ((int)$car["boosted"] === 1 ? " boosted" : "") ?>" style="--i: <?= $i ?>" data-href="<?= $link ?>">
<?php if ((int)$car["premium"] === 1): ?>
            <span class="premium-ribbon"><span aria-hidden="true">&#9813;</span> Premium</span>
<?php elseif ((int)$car["boosted"] === 1): ?>
            <span class="boost-ribbon"><span aria-hidden="true">&#8679;</span> Pushed</span>
<?php endif; ?>
            <a class="thumb" href="<?= $link ?>">
<?php if ($car["photo"]): ?>
                <img src="uploads/<?= htmlspecialchars($car["photo"]) ?>" alt="">
<?php else: ?>
                <?= htmlspecialchars(mb_strtoupper(mb_substr($car["name"], 0, 1))) ?>
<?php endif; ?>
            </a>
            <div class="car-body">
                <svg class="card-curve" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="curve-stroke-<?= $i ?>" x1="0" x2="1">
                            <stop offset="0" stop-color="#ffc94d" stop-opacity=".55"/>
                            <stop offset=".5" stop-color="#ffb02e"/>
                            <stop offset="1" stop-color="#ff7a1a"/>
                        </linearGradient>
                        <linearGradient id="curve-fill-<?= $i ?>" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#ffa62b" stop-opacity=".16"/>
                            <stop offset="1" stop-color="#ff7a1a" stop-opacity=".02"/>
                        </linearGradient>
                    </defs>
                    <path class="curve-area" d="M0,98 C45,98 80,85 99.5,0 L100,100 L0,100 Z" fill="url(#curve-fill-<?= $i ?>)"/>
                    <path class="curve-line" d="M0,98 C45,98 80,85 99.5,0" fill="none" stroke="url(#curve-stroke-<?= $i ?>)" stroke-width="3" vector-effect="non-scaling-stroke"/>
                </svg>
                <h2><a href="<?= $link ?>"><?= htmlspecialchars($car["name"]) ?></a></h2>
                <div class="meta">
<?php if (!empty($car["seller_location"])): ?>
                    <span class="loc"><svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="10" r="2.5" fill="currentColor"/></svg><?= htmlspecialchars($car["seller_location"] . ($car["country"] ? ", " . $car["country"] : "")) ?></span> &middot;
<?php endif; ?>
                    Added <?= htmlspecialchars(substr($car["creation_date"], 0, 10)) ?>
                </div>
<?php if ($car["description"] !== null && $car["description"] !== ""): ?>
                <p class="card-desc"><?= htmlspecialchars($car["description"]) ?></p>
<?php else: ?>
                <p class="card-desc empty-desc">No description yet.</p>
<?php endif; ?>
                <div class="chips">
<?php foreach ($shown as $part): ?>
                    <span class="chip"><?= htmlspecialchars($part) ?></span>
<?php endforeach; ?>
<?php if (count($parts) > count($shown)): ?>
                    <span class="chip more">+<?= count($parts) - count($shown) ?> more</span>
<?php endif; ?>
<?php if (!$parts): ?>
                    <span class="chip more">No parts yet</span>
<?php endif; ?>
                </div>
                <button type="button" class="views-toggle" data-car="<?= (int)$car["id"] ?>" aria-expanded="false">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" fill="currentColor"/></svg>
                    <b><?= (int)$car["people"] ?></b> <?= (int)$car["people"] === 1 ? "person" : "people" ?> looked at this car
                </button>
                <div class="views-pop" hidden></div>
            </div>
            <div class="car-side">
                <?= heart_button((int)$car["id"], (bool)$car["wished"]) ?>
                <span class="price<?= $car["price"] === null ? " muted" : "" ?>"><?= fmt_price($car["price"]) ?></span>
                <span class="id-tag">#<?= htmlspecialchars($car["id"]) ?> &middot; <?= (int)$car["part_count"] ?> parts</span>
                <a class="btn details" href="<?= $link ?>">Details</a>
            </div>
        </article>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php else: ?>
    <div class="empty">No cars yet. <a href="edit.php">Add the first one</a>.</div>
<?php endif; ?>
</main>

<script>
// The whole card opens the car. Links, buttons and the views panel keep their own behaviour,
// selecting text doesn't navigate, and Ctrl/Cmd/middle click opens it in a new tab.
document.querySelectorAll(".car-card[data-href]").forEach((card) => {
    const open = (e) => {
        if (e.target.closest("a, button, .views-pop, form, input")) return;
        if (String(window.getSelection())) return;
        if (e.ctrlKey || e.metaKey || e.button === 1) {
            window.open(card.dataset.href, "_blank");
        } else {
            location.href = card.dataset.href;
        }
    };
    card.addEventListener("click", open);
    card.addEventListener("auxclick", (e) => { if (e.button === 1) open(e); });
});

// Eye button: open a small panel with who is watching right now and the view history
(() => {
    const timers = new Map();

    async function fill(btn, pop) {
        try {
            const res = await fetch("views.php?action=car&id=" + btn.dataset.car, { cache: "no-store" });
            const d = await res.json();
            const max = Math.max(1, ...d.days.map((x) => x.n));
            pop.replaceChildren();

            const now = document.createElement("div");
            now.className = "pop-now";
            now.innerHTML = '<span class="live-dot"></span><b></b> <span></span>';
            now.querySelector(".live-dot").classList.toggle("idle", d.watching === 0);
            now.querySelector("b").textContent = d.watching;
            now.lastChild.textContent = d.watching === 1 ? "person is viewing this car right now" : "people are viewing this car right now";

            const hist = document.createElement("div");
            hist.className = "pop-history";
            hist.textContent = d.people + (d.people === 1 ? " person" : " people") + " looked at it in total · " + d.total + (d.total === 1 ? " view" : " views")
                + (d.last_viewed ? " · last " + d.last_viewed.slice(0, 16) : "");

            const chart = document.createElement("div");
            chart.className = "spark";
            for (const day of d.days) {
                const col = document.createElement("div");
                col.className = "spark-col";
                col.title = day.date + ": " + day.n + (day.n === 1 ? " view" : " views");
                const bar = document.createElement("span");
                bar.style.height = Math.max(4, Math.round(day.n / max * 100)) + "%";
                if (day.n === 0) bar.className = "zero";
                const lab = document.createElement("small");
                lab.textContent = day.label;
                col.append(bar, lab);
                chart.append(col);
            }
            const cap = document.createElement("div");
            cap.className = "spark-cap";
            cap.textContent = "Views, last 7 days";

            pop.append(now, hist, chart, cap);
        } catch (e) {
            pop.textContent = "Could not load views.";
        }
    }

    document.querySelectorAll(".views-toggle").forEach((btn) => {
        const pop = btn.nextElementSibling;
        btn.addEventListener("click", () => {
            const open = pop.hidden;
            pop.hidden = !open;
            btn.setAttribute("aria-expanded", String(open));
            clearInterval(timers.get(btn));
            if (open) {
                pop.textContent = "Loading…";
                fill(btn, pop);
                timers.set(btn, setInterval(() => fill(btn, pop), 10000)); // keep "right now" live while open
            }
        });
    });
})();
</script>

<?php site_footer(); ?>
