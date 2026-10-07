<?php
require __DIR__ . "/inc.php";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("db", "app", "secret", "app");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$visitor = visitor_id();

// ---- API: toggle a car on this visitor's wishlist ----
if (($_GET["action"] ?? null) === "toggle") {
    header("Content-Type: application/json");
    header("Cache-Control: no-store");
    $carId = filter_input(INPUT_POST, "car", FILTER_VALIDATE_INT);

    $stmt = $conn->prepare("SELECT 1 FROM cars WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $carId);
    $stmt->execute();
    if (!$carId || !$stmt->get_result()->fetch_row()) {
        http_response_code(404);
        echo json_encode(["error" => "Car not found"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM wishlist WHERE visitor_id = ? AND car_id = ?");
    $stmt->bind_param("si", $visitor, $carId);
    $stmt->execute();
    $wished = $stmt->affected_rows === 0;   // nothing removed, so it wasn't there: add it
    if ($wished) {
        $stmt = $conn->prepare("INSERT INTO wishlist (visitor_id, car_id) VALUES (?, ?)");
        $stmt->bind_param("si", $visitor, $carId);
        $stmt->execute();
    }
    $conn->close();
    echo json_encode(["wished" => $wished, "count" => wishlist_count()]);
    exit;
}

// ---- page: this visitor's wished cars, most recently added first ----
$stmt = $conn->prepare("SELECT c.id, c.name, c.price, c.description, w.created_at AS wished_at,
                               (SELECT filename FROM car_photos f WHERE f.car_id = c.id ORDER BY f.id LIMIT 1) AS photo,
                               (SELECT COUNT(*) FROM car_parts p WHERE p.car_id = c.id) AS part_count
                        FROM wishlist w
                        JOIN cars c ON c.id = w.car_id AND c.deleted_at IS NULL
                        WHERE w.visitor_id = ?
                        ORDER BY w.created_at DESC, c.id");
$stmt->bind_param("s", $visitor);
$stmt->execute();
$cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

$sum = array_sum(array_map(fn($c) => (float)($c["price"] ?? 0), $cars));

site_header("Wishlist", "wishlist.php");
?>

<main class="wrap">
    <div class="page-title">
        <h1>Wishlist</h1>
<?php if ($cars): ?>
        <span class="count"><?= count($cars) ?> <?= count($cars) === 1 ? "car" : "cars" ?> &middot; together <?= fmt_price((string)$sum) ?></span>
<?php endif; ?>
    </div>

    <div class="empty" id="wish-empty"<?= $cars ? " hidden" : "" ?>>
        <svg class="empty-heart" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.6-9.3C.9 7.8 3 4 6.8 4c2.1 0 3.6 1.1 4.4 2.5h1.6C13.6 5.1 15.1 4 17.2 4 21 4 23.1 7.8 21.6 11.2c-2.1 4.7-9.6 9.3-9.6 9.3Z"/></svg>
        Your wishlist is empty. Tap the heart on any car to save it here.
        <div style="margin-top:1rem"><a class="btn accent" href="list.php">Browse all cars</a></div>
    </div>

    <div class="results">
<?php foreach ($cars as $i => $car):
    $link = "edit.php?id=" . (int)$car["id"];
?>
        <article class="car-card wish-card" style="--i: <?= $i ?>" data-href="<?= $link ?>" data-remove-on-unwish>
            <a class="thumb" href="<?= $link ?>">
<?php if ($car["photo"]): ?>
                <img src="uploads/<?= htmlspecialchars($car["photo"]) ?>" alt="">
<?php else: ?>
                <?= htmlspecialchars(mb_strtoupper(mb_substr($car["name"], 0, 1))) ?>
<?php endif; ?>
            </a>
            <div class="car-body">
                <h2><a href="<?= $link ?>"><?= htmlspecialchars($car["name"]) ?></a></h2>
                <div class="meta">Saved <?= htmlspecialchars(substr($car["wished_at"], 0, 16)) ?> &middot; <?= (int)$car["part_count"] ?> parts</div>
<?php if ($car["description"]): ?>
                <p class="card-desc"><?= htmlspecialchars($car["description"]) ?></p>
<?php endif; ?>
            </div>
            <div class="car-side">
                <?= heart_button((int)$car["id"], true) ?>
                <span class="price<?= $car["price"] === null ? " muted" : "" ?>"><?= fmt_price($car["price"]) ?></span>
                <a class="btn details" href="<?= $link ?>">Details</a>
            </div>
        </article>
<?php endforeach; ?>
    </div>
</main>

<script>
// Click anywhere on a card to open the car (links and buttons keep their own behaviour)
document.querySelectorAll(".car-card[data-href]").forEach((card) => {
    card.addEventListener("click", (e) => {
        if (e.target.closest("a, button") || String(window.getSelection())) return;
        if (e.ctrlKey || e.metaKey) window.open(card.dataset.href, "_blank");
        else location.href = card.dataset.href;
    });
});
</script>

<?php site_footer(); ?>
