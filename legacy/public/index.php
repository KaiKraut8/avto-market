<?php
require __DIR__ . "/inc.php";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("db", "app", "secret", "app");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Every active car with what the highlight cards need
$sql = "SELECT c.id, c.name, c.price, c.description, c.creation_date,
               " . premium_active_sql() . " AS premium, (c.boost_until > NOW()) AS boosted,
               (SELECT COUNT(*) FROM car_parts p WHERE p.car_id = c.id) AS part_count,
               (SELECT JSON_ARRAYAGG(part_name) FROM (SELECT part_name FROM car_parts p WHERE p.car_id = c.id ORDER BY p.id LIMIT 3) x) AS parts,
               (SELECT filename FROM car_photos f WHERE f.car_id = c.id ORDER BY f.id LIMIT 1) AS photo,
               (SELECT COUNT(DISTINCT visitor_id) FROM car_views v WHERE v.car_id = c.id) AS people
        FROM cars c
        WHERE c.deleted_at IS NULL
        ORDER BY c.id";
$cars = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Highlights: premium cars only. If there are fewer than three, fill up with pushed cars, then the rest.
$premiumCars = array_values(array_filter($cars, fn($c) => (int)$c["premium"] === 1));
$highlights = $premiumCars;
if (count($highlights) < 3) {
    $others = array_values(array_filter($cars, fn($c) => (int)$c["premium"] !== 1));
    usort($others, fn($a, $b) => [(int)$b["boosted"], (int)$a["id"]] <=> [(int)$a["boosted"], (int)$b["id"]]);
    $highlights = array_merge($highlights, array_slice($others, 0, 3 - count($highlights)));
}
// distinct people across all active cars (one person looking at three cars is still one person)
$totalPeople = (int)$conn->query("SELECT COUNT(DISTINCT v.visitor_id) FROM car_views v
                                  JOIN cars c ON c.id = v.car_id AND c.deleted_at IS NULL")->fetch_row()[0];
$conn->close();

// Real numbers for the hero
$priced = array_filter($cars, fn($c) => $c["price"] !== null);
$prices = array_map(fn($c) => (float)$c["price"], $priced);
$lowest = $prices ? min($prices) : null;
$totalParts = array_sum(array_column($cars, "part_count"));

// Badges: cheapest = best price, most expensive = premium, most viewed = most watched, newest = new arrival
$badges = array_fill_keys(array_column($cars, "id"), []);
$priciest = null;
if (count($priced) > 1) {
    $byPrice = array_values($priced);
    usort($byPrice, fn($a, $b) => (float)$a["price"] <=> (float)$b["price"]);
    $cheapest = $byPrice[0];
    $priciest = $byPrice[count($byPrice) - 1];
    $badges[$cheapest["id"]][] = ["Best price", "deal"];
    $badges[$priciest["id"]][] = ["Premium pick", "premium"];
}
$mostWatched = array_reduce($cars, fn($a, $c) => $a === null || $c["people"] > $a["people"] ? $c : $a);
if ($mostWatched && $mostWatched["people"] > 0) {
    $badges[$mostWatched["id"]][] = ["Most watched", "hot"];
}
$newest = array_reduce($cars, fn($a, $c) => $a === null || $c["creation_date"] > $a["creation_date"] ? $c : $a);
if ($newest && !$badges[$newest["id"]]) {
    $badges[$newest["id"]][] = ["New arrival", "new"];
}

// Top pick for the hero: most watched if anyone looked, otherwise the premium car, otherwise the first one
$topPick = ($mostWatched && $mostWatched["people"] > 0) ? $mostWatched : ($priciest ?? ($cars[0] ?? null));
$topLabel = ($mostWatched && $mostWatched["people"] > 0) ? "Most watched right now" : "Top pick of the week";

function initial(string $name): string
{
    return htmlspecialchars(mb_strtoupper(mb_substr($name, 0, 1)));
}

site_header("Best car offers in town", "index.php");
?>

<section class="home-hero">
<?php if ($topPick && $topPick["photo"]): ?>
    <div class="hero-backdrop" style="background-image: url('uploads/<?= htmlspecialchars($topPick["photo"]) ?>')"></div>
<?php endif; ?>
    <div class="hero-shade"></div>
    <div class="hero-sun" aria-hidden="true"></div>
    <div class="hero-road" aria-hidden="true"></div>
    <div class="hero-sparks" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>

    <div class="inner hero-grid">
        <div class="hero-copy">
            <p class="pill"><span class="live-dot"></span><?= count($cars) ?> <?= count($cars) === 1 ? "car" : "cars" ?> in the garage right now</p>
            <h1>The best car offers <span>in town.</span></h1>
            <p class="hero-lede">Hand-picked cars, photographed and documented part by part. Fair prices, no surprises: find your next car before someone else does.</p>
            <form class="search-bar hero-search" action="list.php" method="get" role="search">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <input type="search" name="q" placeholder="What car are you looking for? e.g. BMW, Mercedes, Ljubljana" aria-label="Search cars" maxlength="80">
                <button type="submit" class="btn accent">Search</button>
            </form>
            <div class="hero-cta">
                <a class="btn accent big" href="list.php">Browse all cars <span aria-hidden="true">&rarr;</span></a>
                <a class="btn ghost big" href="views.php">See what's trending</a>
            </div>
            <dl class="hero-facts">
<?php if ($lowest !== null): ?>
                <div><dt>Prices from</dt><dd><?= fmt_price((string)$lowest) ?></dd></div>
<?php endif; ?>
                <div><dt>Parts documented</dt><dd><?= $totalParts ?></dd></div>
<?php if ($totalPeople > 0): ?>
                <div><dt>Interested buyers</dt><dd><?= $totalPeople ?></dd></div>
<?php endif; ?>
            </dl>
        </div>

<?php if ($topPick): ?>
        <a class="hero-car" href="edit.php?id=<?= (int)$topPick["id"] ?>">
            <span class="hero-car-tag"><?= $topLabel ?></span>
            <div class="hero-car-frame">
<?php if ($topPick["photo"]): ?>
                <img src="uploads/<?= htmlspecialchars($topPick["photo"]) ?>" alt="<?= htmlspecialchars($topPick["name"]) ?>">
<?php else: ?>
                <span class="initial"><?= initial($topPick["name"]) ?></span>
<?php endif; ?>
            </div>
            <div class="hero-car-info">
                <strong><?= htmlspecialchars($topPick["name"]) ?></strong>
                <span class="price"><?= fmt_price($topPick["price"]) ?></span>
            </div>
        </a>
<?php endif; ?>
    </div>
    <a class="scroll-cue" href="#highlights" aria-label="Scroll to the highlights"><span></span></a>
</section>

<main class="wrap home">
    <div class="section-head" id="highlights">
        <div>
            <p class="eyebrow">Hot right now</p>
            <h2 class="big-title"><?= $premiumCars ? "Premium highlights" : "Today's highlights" ?></h2>
        </div>
        <a href="list.php">See every car &rarr;</a>
    </div>

<?php if ($cars): ?>
    <section class="offer-row">
<?php foreach ($highlights as $i => $car):
    $parts = array_values(array_filter(json_decode($car["parts"] ?? "[]", true) ?: []));
?>
        <a class="offer<?= (int)$car["premium"] === 1 ? " premium" : "" ?>" href="edit.php?id=<?= (int)$car["id"] ?>" style="--i: <?= $i ?>">
<?php if ((int)$car["premium"] === 1): ?>
            <span class="premium-ribbon"><span aria-hidden="true">&#9813;</span> Premium</span>
<?php endif; ?>
            <div class="offer-photo">
<?php if ($car["photo"]): ?>
                <img src="uploads/<?= htmlspecialchars($car["photo"]) ?>" alt="<?= htmlspecialchars($car["name"]) ?>" loading="lazy">
<?php else: ?>
                <span class="initial"><?= initial($car["name"]) ?></span>
<?php endif; ?>
                <div class="offer-badges">
<?php foreach ($badges[$car["id"]] as [$label, $kind]): ?>
                    <span class="tag tag-<?= $kind ?>"><?= $label ?></span>
<?php endforeach; ?>
                </div>
<?php if ((int)$car["people"] > 0): ?>
                <span class="offer-eye" title="People who looked at this car">
                    <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3.2" fill="currentColor"/></svg>
                    <?= (int)$car["people"] ?>
                </span>
<?php endif; ?>
                <span class="offer-price"><?= fmt_price($car["price"]) ?></span>
            </div>
            <div class="offer-body">
                <h3><?= htmlspecialchars($car["name"]) ?></h3>
<?php if ($car["description"]): ?>
                <p class="offer-desc"><?= htmlspecialchars($car["description"]) ?></p>
<?php else: ?>
                <p class="offer-desc">Checked, photographed and ready for a test drive.</p>
<?php endif; ?>
<?php if ($parts): ?>
                <div class="chips">
<?php foreach ($parts as $part): ?>
                    <span class="chip"><?= htmlspecialchars($part) ?></span>
<?php endforeach; ?>
<?php if ((int)$car["part_count"] > count($parts)): ?>
                    <span class="chip more">+<?= (int)$car["part_count"] - count($parts) ?></span>
<?php endif; ?>
                </div>
<?php endif; ?>
                <span class="offer-go">View this car <span aria-hidden="true">&rarr;</span></span>
            </div>
        </a>
<?php endforeach; ?>
    </section>
<?php else: ?>
    <div class="empty">The garage is empty. <a href="edit.php">Add the first car</a>.</div>
<?php endif; ?>

    <section class="premium-promo">
        <span class="promo-crown" aria-hidden="true">&#9813;</span>
        <div class="promo-copy">
            <p class="eyebrow">For sellers</p>
            <h2>Selling cars? <span>Go premium.</span></h2>
            <p>A premium seller account puts every car you list first on All cars and in the home page highlights, in gold.</p>
            <div class="promo-cta">
                <a class="btn gold big" href="premium.php">&#9813; Get premium</a>
                <a class="btn ghost big" href="<?= current_user() ? "edit.php" : "signup.php" ?>"><?= current_user() ? "List a car for free" : "Create a free seller account" ?></a>
            </div>
        </div>
        <div class="promo-plans">
            <a class="promo-plan" href="premium.php">
                <b>Monthly</b>
                <span class="promo-price"><?= fmt_eur(PREMIUM_MONTHLY) ?></span>
                <small>per month</small>
            </a>
            <a class="promo-plan best" href="premium.php">
                <span class="save-badge">Save <?= PREMIUM_YEARLY_SAVING ?>%</span>
                <b>Yearly</b>
                <span class="promo-price"><?= fmt_eur(premium_yearly_price()) ?></span>
                <small><s><?= fmt_eur(PREMIUM_MONTHLY * 12) ?></s> per year</small>
            </a>
            <a class="promo-plan boost" href="edit.php">
                <b>&#8679; Push forward</b>
                <span class="promo-price"><?= fmt_eur(BOOST_WEEKLY) ?></span>
                <small>per week, no premium</small>
            </a>
        </div>
    </section>

    <section class="why">
        <div class="why-item">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <h3>Every part documented</h3>
            <p>Steering, brakes, seats, keys: each car lists what it has, so you know exactly what you're buying.</p>
        </div>
        <div class="why-item">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h3l2-3h6l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="13" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            <h3>Real photos, no stock images</h3>
            <p>What you see is the actual car sitting in our garage, not a picture from a catalogue.</p>
        </div>
        <div class="why-item">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            <h3>See the interest live</h3>
            <p>Every car shows how many people looked at it and who's viewing it right now. Good offers go fast.</p>
        </div>
    </section>

    <section class="cta-band">
        <div>
            <h2>Found the one? Come for a test drive.</h2>
            <p><?= htmlspecialchars(COMPANY["address"]) ?> &middot; <?= htmlspecialchars(COMPANY["hours"]) ?></p>
        </div>
        <div class="cta-actions">
            <a class="btn dark big" href="tel:<?= htmlspecialchars(preg_replace("/[^+0-9]/", "", COMPANY["phone"])) ?>">Call <?= htmlspecialchars(COMPANY["phone"]) ?></a>
            <a class="btn light big" href="mailto:<?= htmlspecialchars(COMPANY["email"]) ?>">Email us</a>
        </div>
    </section>
</main>

<?php site_footer(); ?>
