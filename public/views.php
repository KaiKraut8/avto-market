<?php
require __DIR__ . "/inc.php";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("db", "app", "secret", "app");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Per-car view stats: all views, distinct people, and who has the page open right now
function view_stats(mysqli $conn): array
{
    $sql = "SELECT c.id, c.name,
                   (SELECT filename FROM car_photos f WHERE f.car_id = c.id ORDER BY f.id LIMIT 1) AS photo,
                   (SELECT COUNT(*) FROM car_views v WHERE v.car_id = c.id) AS total,
                   (SELECT COUNT(DISTINCT visitor_id) FROM car_views v WHERE v.car_id = c.id) AS people,
                   (SELECT COUNT(*) FROM car_watchers w WHERE w.car_id = c.id
                        AND w.last_seen > NOW() - INTERVAL " . WATCH_WINDOW . " SECOND) AS watching,
                   (SELECT MAX(viewed_at) FROM car_views v WHERE v.car_id = c.id) AS last_viewed
            FROM cars c
            WHERE c.deleted_at IS NULL
            ORDER BY people DESC, total DESC, c.id";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function json_out(array $data): never
{
    header("Content-Type: application/json");
    header("Cache-Control: no-store");
    echo json_encode($data);
    exit;
}

// ---- API: ping / leave from an open car page, stats for this tab's live refresh ----
$action = $_GET["action"] ?? null;
if ($action !== null) {
    $carId = filter_input(INPUT_POST, "car", FILTER_VALIDATE_INT);
    $visitor = visitor_id();

    if ($action === "ping" && $carId) {
        $stmt = $conn->prepare("INSERT INTO car_watchers (car_id, visitor_id, last_seen)
                                SELECT id, ?, NOW() FROM cars WHERE id = ? AND deleted_at IS NULL
                                ON DUPLICATE KEY UPDATE last_seen = NOW()");
        $stmt->bind_param("si", $visitor, $carId);
        $stmt->execute();
        $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM car_watchers WHERE car_id = ?
                                AND last_seen > NOW() - INTERVAL " . WATCH_WINDOW . " SECOND");
        $stmt->bind_param("i", $carId);
        $stmt->execute();
        json_out(["watching" => (int)$stmt->get_result()->fetch_assoc()["n"]]);
    }

    if ($action === "leave" && $carId) {
        $stmt = $conn->prepare("DELETE FROM car_watchers WHERE car_id = ? AND visitor_id = ?");
        $stmt->bind_param("is", $carId, $visitor);
        $stmt->execute();
        json_out(["ok" => true]);
    }

    // One car's numbers for the eye button on the All cars page (reading this is not a view)
    if ($action === "car") {
        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            json_out(["error" => "Bad id"]);
        }
        $stmt = $conn->prepare("SELECT
                (SELECT COUNT(*) FROM car_watchers WHERE car_id = ? AND last_seen > NOW() - INTERVAL " . WATCH_WINDOW . " SECOND) AS watching,
                (SELECT COUNT(DISTINCT visitor_id) FROM car_views WHERE car_id = ?) AS people,
                (SELECT COUNT(*) FROM car_views WHERE car_id = ?) AS total,
                (SELECT MAX(viewed_at) FROM car_views WHERE car_id = ?) AS last_viewed");
        $stmt->bind_param("iiii", $id, $id, $id, $id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();

        // views per day for the last 7 days, oldest first, zero-filled
        $stmt = $conn->prepare("SELECT DATE(viewed_at) AS d, COUNT(*) AS n FROM car_views
                                WHERE car_id = ? AND viewed_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $byDay = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "n", "d");
        $days = [];
        for ($k = 6; $k >= 0; $k--) {
            $d = date("Y-m-d", strtotime("-$k day"));
            $days[] = ["date" => $d, "label" => date("D", strtotime($d)), "n" => (int)($byDay[$d] ?? 0)];
        }
        json_out([
            "watching" => (int)$r["watching"],
            "people" => (int)$r["people"],
            "total" => (int)$r["total"],
            "last_viewed" => $r["last_viewed"],
            "days" => $days,
        ]);
    }

    if ($action === "stats") {
        $rows = array_map(fn($r) => [
            "id" => (int)$r["id"],
            "total" => (int)$r["total"],
            "people" => (int)$r["people"],
            "watching" => (int)$r["watching"],
            "last_viewed" => $r["last_viewed"],
        ], view_stats($conn));
        json_out(["cars" => $rows]);
    }

    http_response_code(400);
    json_out(["error" => "Unknown action"]);
}

$cars = view_stats($conn);
$conn->close();

$sumTotal = array_sum(array_column($cars, "total"));
$sumPeople = array_sum(array_column($cars, "people"));
$sumWatching = array_sum(array_column($cars, "watching"));
$maxPeople = max(1, ...array_map("intval", array_column($cars, "people") ?: [0]));

site_header("Most watched", "views.php");
?>

<main class="wrap">
    <div class="page-title">
        <h1>Most watched</h1>
        <span class="updated">Live &middot; updates every 10 s <span id="updated-at"></span></span>
    </div>

    <div class="stats" style="margin-bottom:1.5rem">
        <div class="stat"><b id="sum-people"><?= $sumPeople ?></b><span>people looked (per car)</span></div>
        <div class="stat"><b id="sum-total"><?= $sumTotal ?></b><span>total views</span></div>
        <div class="stat"><b id="sum-watching"><?= $sumWatching ?></b><span>watching now</span></div>
    </div>

<?php if ($cars): ?>
    <div class="table-scroll">
        <table class="views-table">
            <thead>
                <tr><th>#</th><th>Car</th><th>People looked</th><th></th><th>Total views</th><th>Watching now</th><th>Last viewed</th></tr>
            </thead>
            <tbody>
<?php foreach ($cars as $rank => $car): ?>
                <tr data-id="<?= (int)$car["id"] ?>">
                    <td><span class="rank rank-<?= $rank + 1 ?>"><?= $rank + 1 ?></span></td>
                    <td>
                        <a class="car-mini" href="edit.php?id=<?= (int)$car["id"] ?>">
<?php if ($car["photo"]): ?>
                            <img class="mini-thumb" src="uploads/<?= htmlspecialchars($car["photo"]) ?>" alt="">
<?php else: ?>
                            <span class="mini-thumb"><?= htmlspecialchars(mb_strtoupper(mb_substr($car["name"], 0, 1))) ?></span>
<?php endif; ?>
                            <?= htmlspecialchars($car["name"]) ?>
                        </a>
                    </td>
                    <td><span class="num accent" data-f="people"><?= (int)$car["people"] ?></span></td>
                    <td><div class="bar"><span data-f="bar" style="width: <?= round((int)$car["people"] / $maxPeople * 100) ?>%"></span></div></td>
                    <td><span class="num" data-f="total"><?= (int)$car["total"] ?></span></td>
                    <td class="watch-cell"><span class="live-dot<?= (int)$car["watching"] ? "" : " idle" ?>" data-f="dot"></span><span class="num" data-f="watching"><?= (int)$car["watching"] ?></span></td>
                    <td class="hint" data-f="last"><?= $car["last_viewed"] ? htmlspecialchars($car["last_viewed"]) : "Never" ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="hint" style="margin-top:1rem">A person is counted once per car (by a browser cookie). "Watching now" is everyone who has the car's page open at this moment.</p>
<?php else: ?>
    <div class="empty">No cars yet.</div>
<?php endif; ?>
</main>

<script>
// Refresh the numbers without reloading the page
(() => {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    async function refresh() {
        try {
            const { cars } = await (await fetch("views.php?action=stats", { cache: "no-store" })).json();
            const max = Math.max(1, ...cars.map((c) => c.people));
            let total = 0, people = 0, watching = 0;
            for (const c of cars) {
                total += c.total; people += c.people; watching += c.watching;
                const row = document.querySelector(`tr[data-id="${c.id}"]`);
                if (!row) continue;
                row.querySelector('[data-f="people"]').textContent = c.people;
                row.querySelector('[data-f="total"]').textContent = c.total;
                row.querySelector('[data-f="watching"]').textContent = c.watching;
                row.querySelector('[data-f="dot"]').classList.toggle("idle", c.watching === 0);
                row.querySelector('[data-f="bar"]').style.width = Math.round(c.people / max * 100) + "%";
                row.querySelector('[data-f="last"]').textContent = c.last_viewed || "Never";
            }
            set("sum-total", total); set("sum-people", people); set("sum-watching", watching);
            set("updated-at", "· last " + new Date().toLocaleTimeString());
        } catch (e) { /* try again next tick */ }
    }
    setInterval(refresh, 10000);
})();
</script>

<?php site_footer(); ?>
