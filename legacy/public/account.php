<?php
require __DIR__ . "/inc.php";

$user = require_login();
$errors = [];
$saved = false;

// Update the profile (email is the login and stays as it is)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $country = $_POST["country"] ?? "";

    if (!csrf_ok()) $errors[] = "Your session expired. Please try again.";
    if (!valid_person_name($name)) $errors[] = "Enter your real name (letters only, 2 to 60 characters).";
    if (!valid_phone($phone)) $errors[] = "Enter a valid phone number with 8 to 15 digits, like +386 40 123 456.";
    if (mb_strlen($location) > 80) $errors[] = "Location is too long (max 80 characters).";
    if (!in_array($country, COUNTRIES, true)) $errors[] = "Choose a country.";

    if (!$errors) {
        $loc = $location === "" ? null : $location;
        $stmt = db()->prepare("UPDATE users SET name = ?, phone = ?, location = ?, country = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $phone, $loc, $country, $user["id"]);
        $stmt->execute();
        header("Location: account.php?saved=1");
        exit;
    }
    $user = array_merge($user, ["name" => $name, "phone" => $phone, "location" => $location, "country" => $country]);
}

// This seller's cars
$stmt = db()->prepare("SELECT c.id, c.name, c.price, c.seller_location, c.country, (c.boost_until > NOW()) AS boosted, c.boost_until,
                              (SELECT filename FROM car_photos f WHERE f.car_id = c.id ORDER BY f.id LIMIT 1) AS photo,
                              (SELECT COUNT(DISTINCT visitor_id) FROM car_views v WHERE v.car_id = c.id) AS people,
                              (SELECT COUNT(*) FROM car_inquiries q WHERE q.car_id = c.id) AS inquiries
                       FROM cars c WHERE c.user_id = ? AND c.deleted_at IS NULL ORDER BY c.id DESC");
$stmt->bind_param("i", $user["id"]);
$stmt->execute();
$cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

site_header("Your profile", "account.php");
?>

<main class="wrap">
    <div class="page-title">
        <h1>Hi, <?= htmlspecialchars(explode(" ", $user["name"])[0]) ?></h1>
        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="btn ghost">Log out</button>
        </form>
    </div>

<?php if (isset($_GET["saved"])): ?>
    <div class="notice">Profile saved.</div>
<?php endif; ?>

    <div class="detail">
        <div>
            <section class="panel">
                <h2>Your cars (<?= count($cars) ?>)</h2>
<?php if ($cars): ?>
                <div class="my-cars">
<?php foreach ($cars as $c): ?>
                    <a class="my-car" href="edit.php?id=<?= (int)$c["id"] ?>">
<?php if ($c["photo"]): ?>
                        <img src="uploads/<?= htmlspecialchars($c["photo"]) ?>" alt="">
<?php else: ?>
                        <span class="mini-thumb"><?= htmlspecialchars(mb_strtoupper(mb_substr($c["name"], 0, 1))) ?></span>
<?php endif; ?>
                        <span class="my-car-text">
                            <b><?= htmlspecialchars($c["name"]) ?></b>
                            <small>
                                <?= (int)$c["people"] ?> <?= (int)$c["people"] === 1 ? "person" : "people" ?> looked &middot;
                                <?= (int)$c["inquiries"] ?> <?= (int)$c["inquiries"] === 1 ? "inquiry" : "inquiries" ?>
<?php if ($user["is_premium"]): ?>
                                &middot; <span class="gold">&#9813; Premium</span>
<?php elseif ((int)$c["boosted"] === 1): ?>
                                &middot; <span class="amber">&#8679; Pushed until <?= htmlspecialchars(substr($c["boost_until"], 0, 10)) ?></span>
<?php endif; ?>
                            </small>
                        </span>
                        <span class="price"><?= fmt_price($c["price"]) ?></span>
                    </a>
<?php endforeach; ?>
                </div>
<?php else: ?>
                <p class="hint">You haven't listed a car yet.</p>
<?php endif; ?>
                <a class="btn accent" href="edit.php" style="margin-top:1rem">+ Sell a car</a>
            </section>
        </div>

        <aside>
            <section class="panel <?= $user["is_premium"] ? "premium-status" : "upsell" ?>">
<?php if ($user["is_premium"]): ?>
                <h2>&#9813; Premium seller</h2>
                <p>Every car you list is shown first in All cars, in gold.</p>
                <p class="hint"><?= $user["premium_plan"] === "yearly" ? "Yearly" : "Monthly" ?> plan, until <?= htmlspecialchars(substr($user["premium_until"], 0, 10)) ?>.</p>
<?php else: ?>
                <span class="upsell-crown" aria-hidden="true">&#9813;</span>
                <h2>Go premium</h2>
                <p>Put every car you list at the top of All cars, in gold. From <?= fmt_eur(PREMIUM_MONTHLY) ?> a month.</p>
                <a class="btn gold" href="premium.php">&#9813; See premium plans</a>
<?php endif; ?>
            </section>

            <section class="panel">
                <h2>Profile</h2>
                <p class="hint">Buyers who contact you about a car see your name, phone and email.</p>
<?php if ($errors): ?>
                <ul class="form-errors">
<?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label>Email (login)</label>
                        <input type="email" value="<?= htmlspecialchars($user["email"]) ?>" disabled>
                    </div>
                    <div class="field">
                        <label for="name">Full name</label>
                        <input type="text" id="name" name="name" maxlength="60" required value="<?= htmlspecialchars($user["name"]) ?>">
                    </div>
                    <div class="field">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" maxlength="25" required value="<?= htmlspecialchars($user["phone"]) ?>">
                    </div>
                    <div class="field">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" maxlength="80" placeholder="e.g. Ljubljana" value="<?= htmlspecialchars($user["location"] ?? "") ?>">
                    </div>
                    <div class="field">
                        <label for="country">Country</label>
                        <select id="country" name="country">
<?php foreach (COUNTRIES as $c): ?>
                            <option<?= ($user["country"] ?? COUNTRIES[0]) === $c ? " selected" : "" ?>><?= htmlspecialchars($c) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn accent">Save profile</button>
                </form>
            </section>
        </aside>
    </div>
</main>

<?php site_footer(); ?>
