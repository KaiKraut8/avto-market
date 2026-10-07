<?php
// Shared bits for every page: accounts, price formatting and the site frame (head, nav, footer).

// ---- sessions: who is logged in ----
session_name("kai_session");
session_set_cookie_params(["lifetime" => 0, "path" => "/", "httponly" => true, "samesite" => "Lax"]);
session_start();

// One shared connection for the helpers in this file
function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = new mysqli("db", "app", "secret", "app");
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// The logged-in seller, or null. Includes is_premium (an account subscription that is running).
function current_user(bool $reload = false): ?array
{
    static $user = false;
    if ($user === false || $reload) {
        $user = null;
        if (!empty($_SESSION["uid"])) {
            $stmt = db()->prepare("SELECT id, name, email, phone, location, country, premium_plan, premium_since, premium_until,
                                          (premium_until IS NOT NULL AND premium_until > NOW()) AS is_premium
                                   FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION["uid"]);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc() ?: null;
        }
    }
    return $user;
}

function log_in(int $userId): void
{
    session_regenerate_id(true);   // new session id on login, so an old one can't be reused
    $_SESSION["uid"] = $userId;
    current_user(true);
}

// Only allow redirects back to our own pages
function safe_next(?string $next, string $fallback = "account.php"): string
{
    return ($next && preg_match('/^[a-z]+\.php(\?[a-zA-Z0-9_=&%.-]*)?$/', $next)) ? $next : $fallback;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header("Location: login.php?next=" . urlencode(basename($_SERVER["REQUEST_URI"])));
        exit;
    }
    return $user;
}

// CSRF protection for the account forms
function csrf_token(): string
{
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_ok(): bool
{
    return is_string($_POST["csrf"] ?? null) && hash_equals(csrf_token(), $_POST["csrf"]);
}

// Company details shown in the footer. Placeholders: replace them with the real ones.
const COMPANY = [
    "name" => "KAI Garage d.o.o.",
    "tagline" => "Hand-picked used cars, checked and documented part by part.",
    "email" => "kaigarage.info@gmail.com",
    "phone" => "+386 40 123 456",
    "address" => "Dunajska cesta 100, 1000 Ljubljana, Slovenia",
    "hours" => "Mon–Fri 8:00–18:00, Sat 9:00–13:00",
];

function fmt_price(?string $price): string
{
    if ($price === null || $price === "") {
        return "Price on request";
    }
    return number_format((float)$price, 0, ",", ".") . " €";
}

// Anonymous visitor id kept in a cookie, so views can be counted per person. Call before any output.
function visitor_id(): string
{
    $id = $_COOKIE["kai_visitor"] ?? "";
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        $id = bin2hex(random_bytes(16));
        setcookie("kai_visitor", $id, [
            "expires" => time() + 365 * 24 * 3600,
            "path" => "/",
            "httponly" => true,
            "samesite" => "Lax",
        ]);
        $_COOKIE["kai_visitor"] = $id;
    }
    return $id;
}

// Someone counts as "watching now" if their open car page checked in within this many seconds
const WATCH_WINDOW = 40;

// How many cars this visitor has on their wishlist (for the badge in the menu)
function wishlist_count(): int
{
    $conn = new mysqli("db", "app", "secret", "app");
    if ($conn->connect_error) {
        return 0;
    }
    $visitor = visitor_id();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM wishlist w JOIN cars c ON c.id = w.car_id AND c.deleted_at IS NULL
                            WHERE w.visitor_id = ?");
    $stmt->bind_param("s", $visitor);
    $stmt->execute();
    $n = (int)$stmt->get_result()->fetch_row()[0];
    $conn->close();
    return $n;
}

// Paid placements. Purchases are simulated: there is no payment provider yet, so no money is taken.
//  - Premium: a monthly or yearly subscription for a seller's account. Every car of a premium
//    seller is shown first in All cars, in its own gold block.
//  - Push forward: weekly, per car, shown first among the regular (non-premium) cars
const PREMIUM_MONTHLY = 44.59;
const PREMIUM_YEARLY_SAVING = 39;   // percent saved against paying monthly for a year
const BOOST_WEEKLY = 6.99;

function premium_yearly_price(): float
{
    return round(PREMIUM_MONTHLY * 12 * (1 - PREMIUM_YEARLY_SAVING / 100), 2);
}

function fmt_eur(float $amount): string
{
    return number_format($amount, 2, ",", ".") . " €";
}

// SQL for "this car is premium right now": its seller's account has premium.
// (Cars that got premium per car before accounts existed keep it until it runs out.)
function premium_active_sql(string $t = "c"): string
{
    return "(EXISTS (SELECT 1 FROM users pu WHERE pu.id = $t.user_id AND pu.premium_until > NOW())
             OR ($t.premium = 1 AND $t.premium_until > NOW()))";
}

function buy_account_premium(mysqli $conn, int $userId, string $plan): bool
{
    $plan = $plan === "yearly" ? "yearly" : "monthly";
    $months = $plan === "yearly" ? 12 : 1;
    $stmt = $conn->prepare("UPDATE users SET premium_plan = ?, premium_since = NOW(), premium_until = NOW() + INTERVAL $months MONTH
                            WHERE id = ? AND (premium_until IS NULL OR premium_until <= NOW())");
    $stmt->bind_param("si", $plan, $userId);
    $stmt->execute();
    return $stmt->affected_rows === 1;
}

// One more week of "pushed forward" (extends a push that is still running)
function buy_boost(mysqli $conn, int $carId): bool
{
    $stmt = $conn->prepare("UPDATE cars c SET boost_until = GREATEST(COALESCE(boost_until, NOW()), NOW()) + INTERVAL 7 DAY
                            WHERE id = ? AND deleted_at IS NULL AND NOT " . premium_active_sql());
    $stmt->bind_param("i", $carId);
    $stmt->execute();
    return $stmt->affected_rows === 1;
}

// Countries a car can be listed in (first one is the default)
const COUNTRIES = [
    "Slovenia", "Austria", "Belgium", "Bosnia and Herzegovina", "Bulgaria", "Croatia", "Czechia", "Denmark",
    "Finland", "France", "Germany", "Greece", "Hungary", "Ireland", "Italy", "Luxembourg", "Montenegro",
    "Netherlands", "North Macedonia", "Norway", "Poland", "Portugal", "Romania", "Serbia", "Slovakia",
    "Spain", "Sweden", "Switzerland", "United Kingdom", "Other",
];

// Save uploaded images ($_FILES entry of a multiple file input) for a car.
// The file contents are checked, not the browser-supplied name or type. Returns [added, skipped].
function save_uploaded_photos(mysqli $conn, int $carId, ?array $files): array
{
    $added = 0;
    $skipped = 0;
    if (!$files || !is_array($files["name"] ?? null)) {
        return [0, 0];
    }
    $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp", "image/gif" => "gif"];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < count($files["name"]); $i++) {
        if ($files["error"][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $tmp = $files["tmp_name"][$i];
        $mime = $files["error"][$i] === UPLOAD_ERR_OK ? $finfo->file($tmp) : false;
        if (!isset($allowed[$mime]) || !getimagesize($tmp) || $files["size"][$i] > 8 * 1024 * 1024) {
            $skipped++;
            continue;
        }
        $filename = bin2hex(random_bytes(16)) . "." . $allowed[$mime];
        if (!move_uploaded_file($tmp, __DIR__ . "/uploads/" . $filename)) {
            $skipped++;
            continue;
        }
        $stmt = $conn->prepare("INSERT INTO car_photos (car_id, filename) VALUES (?, ?)");
        $stmt->bind_param("is", $carId, $filename);
        $stmt->execute();
        $stmt->close();
        $added++;
    }
    return [$added, $skipped];
}

// Contact details must look real before they're accepted (sellers and buyers alike)
function valid_person_name(string $name): bool
{
    return (bool)preg_match("/^\p{L}[\p{L} .'\-]{1,59}$/u", $name);
}

function valid_phone(string $phone): bool
{
    $digits = preg_replace("/[\s\-().\/]/", "", $phone);
    return (bool)preg_match('/^\+?[0-9]{8,15}$/', $digits);   // international format: 8 to 15 digits
}

function valid_email(string $email): bool
{
    return mb_strlen($email) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Heart toggle for the wishlist. With $label it also says "Add to wishlist" / "In your wishlist".
function heart_button(int $carId, bool $wished, bool $label = false): string
{
    $svg = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.6-9.3C.9 7.8 3 4 6.8 4c2.1 0 3.6 1.1 4.4 2.5h1.6C13.6 5.1 15.1 4 17.2 4 21 4 23.1 7.8 21.6 11.2c-2.1 4.7-9.6 9.3-9.6 9.3Z"/></svg>';
    $text = $label ? '<span class="heart-label">' . ($wished ? "In your wishlist" : "Add to wishlist") . '</span>' : "";
    return '<button type="button" class="heart' . ($label ? " with-label" : "") . '" data-car="' . $carId . '" aria-pressed="' . ($wished ? "true" : "false") . '"'
        . ' aria-label="' . ($wished ? "Remove from wishlist" : "Add to wishlist") . '" title="' . ($wished ? "Remove from wishlist" : "Add to wishlist") . '">'
        . $svg . $text . '</button>';
}

function site_header(string $title, string $active = ""): void
{
    $wishCount = wishlist_count();
    $user = current_user();
    $nav = [
        "index.php" => "Home",
        "list.php" => "All cars",
        "views.php" => "Most watched",
        "wishlist.php" => "Wishlist",
        "premium.php" => '<span class="nav-crown" aria-hidden="true">&#9813;</span> Premium',
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> · KAI Garage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;700;800&family=Inter:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="topbar">
    <div class="inner">
        <a class="logo" href="index.php"><span class="mark"></span>KAI<em>Garage</em></a>
        <nav class="nav">
<?php foreach ($nav as $href => $label): ?>
            <a href="<?= $href ?>"<?= $active === $href ? ' class="active"' : "" ?>><?= $label ?><?php if ($href === "wishlist.php"): ?> <span class="nav-count" data-wish-count<?= $wishCount ? "" : " hidden" ?>><?= $wishCount ?></span><?php endif; ?></a>
<?php endforeach; ?>
            <a class="btn accent" href="edit.php">+ Add car</a>
<?php if ($user): ?>
            <a class="account-chip<?= $active === "account.php" ? " active" : "" ?>" href="account.php" title="Your profile">
                <span class="chip-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($user["name"], 0, 1))) ?></span>
                <?= htmlspecialchars(explode(" ", $user["name"])[0]) ?><?php if ($user["is_premium"]): ?> <span class="nav-crown" title="Premium seller">&#9813;</span><?php endif; ?>
            </a>
<?php else: ?>
            <a href="login.php"<?= $active === "login.php" ? ' class="active"' : "" ?>>Log in</a>
            <a class="btn ghost" href="signup.php">Sign up</a>
<?php endif; ?>
        </nav>
    </div>
</header>
<?php if ($active !== "index.php"): ?>
<nav class="back-menu" aria-label="Go back">
    <a class="back-fab" href="index.php" aria-label="Back to the garage">
        <span class="arrow" aria-hidden="true">&larr;</span>
        <span class="label">Back to the garage</span>
    </a>
<?php if (basename($_SERVER["SCRIPT_NAME"]) === "edit.php"): ?>
    <a class="back-sub" href="list.php">
        <span class="arrow" aria-hidden="true">&larr;</span>
        <span class="label">Back to all cars</span>
    </a>
<?php endif; ?>
</nav>
<?php endif; ?>
<?php
}

function site_footer(): void
{
    ?>
<script src="wishlist.js" defer></script>
<footer class="footer">
    <div class="inner footer-grid">
        <div>
            <a class="logo" href="index.php"><span class="mark"></span>KAI<em>Garage</em></a>
            <p class="footer-tag"><?= htmlspecialchars(COMPANY["tagline"]) ?></p>
        </div>
        <div>
            <h3>Contact</h3>
            <ul>
                <li><span>Email</span><a href="mailto:<?= htmlspecialchars(COMPANY["email"]) ?>"><?= htmlspecialchars(COMPANY["email"]) ?></a></li>
                <li><span>Phone</span><a href="tel:<?= htmlspecialchars(preg_replace("/[^+0-9]/", "", COMPANY["phone"])) ?>"><?= htmlspecialchars(COMPANY["phone"]) ?></a></li>
            </ul>
        </div>
        <div>
            <h3>Visit us</h3>
            <ul>
                <li><span>Address</span><?= htmlspecialchars(COMPANY["address"]) ?></li>
                <li><span>Hours</span><?= htmlspecialchars(COMPANY["hours"]) ?></li>
            </ul>
        </div>
    </div>
    <div class="inner footer-bottom">&copy; <?= date("Y") ?> <?= htmlspecialchars(COMPANY["name"]) ?>. All rights reserved.</div>
</footer>

</body>
</html>
<?php
}
