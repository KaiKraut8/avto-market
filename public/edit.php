<?php
require __DIR__ . "/inc.php";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("db", "app", "secret", "app");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$message = "";
$user = current_user();

// Only the car's seller may change it. Cars listed before accounts existed have no seller yet,
// so any logged-in user may manage those until they're assigned to an account.
function can_edit_car(mysqli $conn, $carId, ?array $user): bool
{
    if (!$user || !$carId) {
        return false;
    }
    $stmt = $conn->prepare("SELECT user_id FROM cars WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $carId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && ($row["user_id"] === null || (int)$row["user_id"] === (int)$user["id"]);
}

function deny(int $carId): never
{
    header("Location: " . ($carId ? "edit.php?id=$carId" : "login.php"));
    exit;
}

// Delete: soft delete, the row stays in the database but is hidden from the list
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
    if (!can_edit_car($conn, $id, $user)) deny((int)$id);
    if ($id !== false && $id !== null) {
        $stmt = $conn->prepare("UPDATE cars SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->query("INSERT INTO cars_log (car_id, action) VALUES (" . (int)$id . ", 'delete')");
    }
    header("Location: index.php");
    exit;
}

// Add / remove a part of the car; redirect back so a refresh doesn't resubmit
$partAction = $_POST["action"] ?? "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && in_array($partAction, ["add_part", "remove_part"], true)) {
    $carId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
    if (!can_edit_car($conn, $carId, $user)) deny((int)$carId);
    if ($carId !== false && $carId !== null) {
        if ($partAction === "add_part") {
            $part = trim($_POST["part_name"] ?? "");
            $description = trim($_POST["description"] ?? "");
            if ($part !== "" && mb_strlen($part) <= 100) {
                // Link to a category when the name matches one (NULL for free-text parts)
                $stmt = $conn->prepare("INSERT INTO car_parts (car_id, part_name, description, creation_date, category_id)
                    VALUES (?, ?, ?, NOW(), (SELECT id FROM part_categories WHERE name = ?))");
                $stmt->bind_param("isss", $carId, $part, $description, $part);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $partId = filter_input(INPUT_POST, "part_id", FILTER_VALIDATE_INT);
            $stmt = $conn->prepare("DELETE FROM car_parts WHERE id = ? AND car_id = ?");
            $stmt->bind_param("ii", $partId, $carId);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: edit.php?id=" . (int)$carId);
    exit;
}

// Photos: upload (several at once) and remove
$photoAction = $_POST["action"] ?? "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && in_array($photoAction, ["upload_photos", "remove_photo"], true)) {
    $carId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
    if (!can_edit_car($conn, $carId, $user)) deny((int)$carId);
    $added = 0;
    $skipped = 0;
    if ($carId !== false && $carId !== null) {
        if ($photoAction === "upload_photos") {
            [$added, $skipped] = save_uploaded_photos($conn, $carId, $_FILES["photos"] ?? null);
        } else {
            $photoId = filter_input(INPUT_POST, "photo_id", FILTER_VALIDATE_INT);
            $stmt = $conn->prepare("SELECT filename FROM car_photos WHERE id = ? AND car_id = ?");
            $stmt->bind_param("ii", $photoId, $carId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $stmt = $conn->prepare("DELETE FROM car_photos WHERE id = ?");
                $stmt->bind_param("i", $photoId);
                $stmt->execute();
                $stmt->close();
                @unlink(__DIR__ . "/uploads/" . basename($row["filename"]));
            }
        }
    }
    header("Location: edit.php?id=" . (int)$carId . "&added=$added&skipped=$skipped");
    exit;
}

// Push an existing car forward for a week (premium is bought for the account, on the Premium tab)
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "buy_boost") {
    $carId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
    if (!can_edit_car($conn, $carId, $user)) deny((int)$carId);
    buy_boost($conn, $carId);
    header("Location: edit.php?id=" . (int)$carId . "&boosted=1");
    exit;
}

// A buyer contacts the seller: their own details have to be valid before the seller's are shown
$contactErrors = null;
$contactOld = [];
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "contact_seller") {
    $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
    $contactOld = [
        "name" => trim($_POST["buyer_name"] ?? ""),
        "email" => trim($_POST["buyer_email"] ?? ""),
        "phone" => trim($_POST["buyer_phone"] ?? ""),
        "message" => trim($_POST["buyer_message"] ?? ""),
    ];
    $contactErrors = [];
    if (!valid_person_name($contactOld["name"])) $contactErrors[] = "Enter your real name (letters only, 2 to 60 characters).";
    if (!valid_email($contactOld["email"])) $contactErrors[] = "Enter a valid email address, like name@example.com.";
    if (!valid_phone($contactOld["phone"])) $contactErrors[] = "Enter a valid phone number with 8 to 15 digits, like +386 40 123 456.";
    if (mb_strlen($contactOld["message"]) > 2000) $contactErrors[] = "Your message is too long (max 2000 characters).";

    if (!$contactErrors && $id) {
        $visitor = visitor_id();
        $msg = $contactOld["message"] === "" ? null : $contactOld["message"];
        $stmt = $conn->prepare("INSERT INTO car_inquiries (car_id, visitor_id, name, email, phone, message)
                                SELECT id, ?, ?, ?, ?, ? FROM cars WHERE id = ? AND deleted_at IS NULL
                                AND (user_id IS NOT NULL OR seller_email IS NOT NULL)");
        $stmt->bind_param("sssssi", $visitor, $contactOld["name"], $contactOld["email"], $contactOld["phone"], $msg, $id);
        $stmt->execute();
        header("Location: edit.php?id=" . (int)$id . "&contacted=1#contact");
        exit;
    }
}

// Save submitted form: a new car when there is no id yet, otherwise update the existing one
if ($_SERVER["REQUEST_METHOD"] === "POST" && $contactErrors === null) {
    $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
    $name = trim($_POST["name"] ?? "");
    $plan = $_POST["plan"] ?? "free";   // free | boost | premium (+ billing monthly / yearly), only asked when the car is first posted
    $billing = ($_POST["billing"] ?? "monthly") === "yearly" ? "yearly" : "monthly";
    $description = trim($_POST["description"] ?? "");
    $description = $description === "" ? null : $description;
    // where the car is: required for every listing
    $location = trim($_POST["seller_location"] ?? "");
    $country = $_POST["country"] ?? "";
    // a new car needs a logged-in seller; an existing one may only be changed by its seller
    if ($id ? !can_edit_car($conn, $id, $user) : !$user) deny((int)$id);
    $priceIn = str_replace([".", ",", " ", "€"], ["", ".", "", ""], $_POST["price"] ?? "");
    $price = $priceIn === "" ? null : filter_var($priceIn, FILTER_VALIDATE_FLOAT);

    if ($name === "" || mb_strlen($name) > 50) {
        $message = "Name is required (max 50 characters).";
    } elseif ($price === false || ($price !== null && $price < 0)) {
        $message = "Price must be a number.";
    } elseif ($description !== null && mb_strlen($description) > 5000) {
        $message = "Description is too long (max 5000 characters).";
    } elseif (mb_strlen($location) < 2 || mb_strlen($location) > 80) {
        $message = "Location is required: the town or city where the car is (2 to 80 characters).";
    } elseif (!in_array($country, COUNTRIES, true)) {
        $message = "Choose the country where the car is listed.";
    } else {
        if ($id === false || $id === null) {
            $stmt = $conn->prepare("INSERT INTO cars (user_id, name, price, description, seller_location, country, creation_date)
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isdsss", $user["id"], $name, $price, $description, $location, $country);
            $ok = $stmt->execute();
            $id = $conn->insert_id;
            $flag = "saved=1";
            // a premium seller's cars are premium already; the others choose free, a push, or premium for the account
            if ($ok && !$user["is_premium"] && $plan === "premium") {
                buy_account_premium($conn, (int)$user["id"], $billing);
                $flag = "premium=1";
            } elseif ($ok && !$user["is_premium"] && $plan === "boost") {
                buy_boost($conn, $id);
                $flag = "boosted=1";
            }
            // photos chosen while posting the car
            if ($ok && !empty($_FILES["photos"]["name"][0])) {
                [$added, $skipped] = save_uploaded_photos($conn, $id, $_FILES["photos"]);
                $flag .= "&added=$added&skipped=$skipped";
            }
        } else {
            $stmt = $conn->prepare("UPDATE cars SET name = ?, price = ?, description = ?, seller_location = ?, country = ?
                                    WHERE id = ? AND deleted_at IS NULL");
            $stmt->bind_param("sdsssi", $name, $price, $description, $location, $country, $id);
            $flag = "saved=1";   // editing never changes premium or a push
            $ok = $stmt->execute();
        }
        if ($ok) {
            $stmt->close();
            header("Location: edit.php?id=" . (int)$id . "&" . $flag);
            exit;
        }
        $message = "Could not save: " . $stmt->error;
        $stmt->close();
    }
} elseif ($contactErrors === null) {
    $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
    if (($id === false || $id === null) && !$user) {
        header("Location: login.php?next=edit.php");   // selling a car needs an account
        exit;
    }
}

$car = null;
if ($id !== false && $id !== null) {
    // Fetch only the clicked row, and only the columns we show
    // the seller's contact details come from their profile (older cars kept them on the car)
    $stmt = $conn->prepare("SELECT c.id, c.user_id, c.name, c.price, " . premium_active_sql() . " AS premium,
                                   (c.boost_until > NOW()) AS boosted, c.boost_until, c.description, c.seller_location, c.country, c.creation_date,
                                   COALESCE(u.name, c.seller_name) AS seller_name,
                                   COALESCE(u.phone, c.seller_phone) AS seller_phone,
                                   COALESCE(u.email, c.seller_email) AS seller_email
                            FROM cars c LEFT JOIN users u ON u.id = c.user_id
                            WHERE c.id = ? AND c.deleted_at IS NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $car = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    // a new car starts at the seller's own location
    $car = ["id" => "", "user_id" => $user["id"] ?? null, "name" => $_POST["name"] ?? "", "price" => $_POST["price"] ?? null,
            "premium" => (int)($user["is_premium"] ?? 0), "boosted" => 0, "plan" => $_POST["plan"] ?? "free", "description" => $_POST["description"] ?? null,
            "seller_location" => $_POST["seller_location"] ?? ($user["location"] ?? null), "country" => $_POST["country"] ?? ($user["country"] ?? COUNTRIES[0]),
            "creation_date" => ""];
}

// Unknown or deleted car: just go back to the garage
if (!$car) {
    header("Location: index.php");
    exit;
}

// Count this visit (not the reloads after saving or uploading) and read the view stats
$views = null;
$wished = false;
$contacted = false;
$canEdit = $car["id"] === "" ? (bool)$user : can_edit_car($conn, $car["id"], $user);
$isOwner = $user && $car["user_id"] !== null && (int)$car["user_id"] === (int)$user["id"];
if ($car["id"] !== "") {
    $visitor = visitor_id();
    $stmt = $conn->prepare("SELECT 1 FROM wishlist WHERE visitor_id = ? AND car_id = ?");
    $stmt->bind_param("si", $visitor, $car["id"]);
    $stmt->execute();
    $wished = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    $stmt = $conn->prepare("SELECT 1 FROM car_inquiries WHERE visitor_id = ? AND car_id = ? LIMIT 1");
    $stmt->bind_param("si", $visitor, $car["id"]);
    $stmt->execute();
    $contacted = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if ($_SERVER["REQUEST_METHOD"] === "GET" && !isset($_GET["saved"]) && !isset($_GET["added"]) && !isset($_GET["skipped"])) {
        $stmt = $conn->prepare("INSERT INTO car_views (car_id, visitor_id) VALUES (?, ?)");
        $stmt->bind_param("is", $car["id"], $visitor);
        $stmt->execute();
        $stmt->close();
    }
    $stmt = $conn->prepare("INSERT INTO car_watchers (car_id, visitor_id, last_seen) VALUES (?, ?, NOW())
                            ON DUPLICATE KEY UPDATE last_seen = NOW()");
    $stmt->bind_param("is", $car["id"], $visitor);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT
            (SELECT COUNT(*) FROM car_views WHERE car_id = ?) AS total,
            (SELECT COUNT(DISTINCT visitor_id) FROM car_views WHERE car_id = ?) AS people,
            (SELECT COUNT(*) FROM car_watchers WHERE car_id = ? AND last_seen > NOW() - INTERVAL " . WATCH_WINDOW . " SECOND) AS watching");
    $stmt->bind_param("iii", $car["id"], $car["id"], $car["id"]);
    $stmt->execute();
    $views = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (isset($_GET["saved"])) {
    $message = "Saved.";
}
if (isset($_GET["premium"]) && $user && $user["is_premium"]) {
    $message = "Premium activated for your account (" . ($user["premium_plan"] === "yearly" ? "yearly" : "monthly") . ", until "
             . substr($user["premium_until"], 0, 10) . "): all your cars are now shown first in All cars, in gold.";
}
if (isset($_GET["boosted"]) && (int)$car["boosted"] === 1) {
    $message = "Pushed forward until " . substr($car["boost_until"], 0, 16) . ": shown first among the regular cars.";
}
if (isset($_GET["added"]) || isset($_GET["skipped"])) {
    $addedN = (int)($_GET["added"] ?? 0);
    $skippedN = (int)($_GET["skipped"] ?? 0);
    if ($addedN) $message = trim($message . " " . $addedN . " photo" . ($addedN === 1 ? "" : "s") . " added.");
    if ($skippedN) $message = trim($message . " " . $skippedN . " file" . ($skippedN === 1 ? "" : "s") . " skipped (must be a JPG, PNG, WebP or GIF image under 8 MB).");
}

$photos = [];
if ($car["id"] !== "") {
    $stmt = $conn->prepare("SELECT id, filename FROM car_photos WHERE car_id = ? ORDER BY id");
    $stmt->bind_param("i", $car["id"]);
    $stmt->execute();
    $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$categories = $conn->query("SELECT name FROM part_categories ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Only this car's parts, only the columns we show
$parts = [];
if ($car["id"] !== "") {
    $stmt = $conn->prepare("SELECT id, part_name, description, creation_date FROM car_parts WHERE car_id = ? ORDER BY id");
    $stmt->bind_param("i", $car["id"]);
    $stmt->execute();
    $parts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();
?>
<?php site_header($car["id"] !== "" ? $car["name"] : "New car"); ?>

<main class="wrap">
    <p class="crumbs"><a href="index.php">Home</a> &rsaquo; <a href="list.php">All cars</a> &rsaquo; <?= $car["id"] !== "" ? htmlspecialchars($car["name"]) : "New car" ?></p>

    <div class="detail-head">
        <h1><?= $car["id"] !== "" ? htmlspecialchars($car["name"]) : "New car" ?><?php if ((int)$car["premium"] === 1): ?> <span class="premium-tag"><span aria-hidden="true">&#9813;</span> Premium</span><?php endif; ?></h1>
<?php if ($car["id"] !== ""): ?>
        <div class="detail-actions">
            <?= heart_button((int)$car["id"], $wished, true) ?>
            <a class="btn accent" href="#contact">
                <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                Contact seller
            </a>
            <span class="price<?= $car["price"] === null ? " muted" : "" ?>"><?= fmt_price($car["price"]) ?></span>
        </div>
<?php endif; ?>
    </div>

<?php if ($message): ?>
    <div class="notice"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

    <div class="detail<?= $car["id"] === "" ? " new-car" : "" ?>">
        <div>
<?php if ($car["id"] !== ""): ?>
            <div class="panel">
<?php if ($photos): ?>
                <img class="hero-img" id="hero-img" src="uploads/<?= htmlspecialchars($photos[0]["filename"]) ?>" alt="<?= htmlspecialchars($car["name"]) ?>">
<?php else: ?>
                <div class="hero"><?= htmlspecialchars(mb_strtoupper(mb_substr($car["name"], 0, 1))) ?></div>
<?php endif; ?>
<?php if (count($photos) > 1): ?>
                <div class="thumbs">
<?php foreach ($photos as $photo): ?>
                    <img src="uploads/<?= htmlspecialchars($photo["filename"]) ?>" alt="" onclick="document.getElementById('hero-img').src = this.src">
<?php endforeach; ?>
                </div>
<?php endif; ?>
            </div>

            <div class="panel">
                <h2>About this car</h2>
<?php if ($car["description"] !== null && $car["description"] !== ""): ?>
                <div class="description"><?= nl2br(htmlspecialchars($car["description"])) ?></div>
<?php else: ?>
                <p class="hint">No description yet.<?= $canEdit ? " Write one in the panel on the right and press Save." : "" ?></p>
<?php endif; ?>
            </div>

            <div class="panel">
                <h2>Parts (<?= count($parts) ?>)</h2>
                <div class="table-scroll">
                    <table class="parts">
                        <thead>
                            <tr><th>Part</th><th>Description</th><th>Added</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr>
                        </thead>
                        <tbody>
<?php if ($parts): ?>
<?php foreach ($parts as $part): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($part["part_name"]) ?></strong></td>
                                <td><?= htmlspecialchars($part["description"]) ?></td>
                                <td><?= htmlspecialchars($part["creation_date"]) ?></td>
<?php if ($canEdit): ?>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($car["id"]) ?>">
                                        <input type="hidden" name="part_id" value="<?= htmlspecialchars($part["id"]) ?>">
                                        <input type="hidden" name="action" value="remove_part">
                                        <button type="submit" class="btn danger small">Remove</button>
                                    </form>
                                </td>
<?php endif; ?>
                            </tr>
<?php endforeach; ?>
<?php else: ?>
                            <tr><td colspan="4">No parts yet</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<?php if ($canEdit): ?>
            <div class="panel">
                <h2>Photos (<?= count($photos) ?>)</h2>
<?php if ($photos): ?>
                <div class="photo-grid">
<?php foreach ($photos as $photo): ?>
                    <figure>
                        <img src="uploads/<?= htmlspecialchars($photo["filename"]) ?>" alt="">
                        <form method="post">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($car["id"]) ?>">
                            <input type="hidden" name="photo_id" value="<?= htmlspecialchars($photo["id"]) ?>">
                            <input type="hidden" name="action" value="remove_photo">
                            <button type="submit" class="btn danger small" onclick="return confirm('Remove this photo?');">Remove</button>
                        </form>
                    </figure>
<?php endforeach; ?>
                </div>
<?php endif; ?>
                <form method="post" enctype="multipart/form-data" id="photo-form" data-shrink-form>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($car["id"]) ?>">
                    <input type="hidden" name="action" value="upload_photos">
                    <div class="field">
                        <label for="photos">Add photos</label>
                        <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
                    </div>
                    <button type="submit" class="btn">Upload</button>
                    <span class="count" id="photo-status"></span>
                </form>
            </div>

            <div class="panel">
                <h2>Add a part</h2>
                <form method="post">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($car["id"]) ?>">
                    <input type="hidden" name="action" value="add_part">
                    <div class="row">
                        <div>
                            <label for="part_name">Part</label>
                            <input type="text" id="part_name" name="part_name" maxlength="100" list="part-options" required>
                            <datalist id="part-options">
<?php foreach ($categories as $option): ?>
                                <option value="<?= htmlspecialchars($option["name"]) ?>">
<?php endforeach; ?>
                            </datalist>
                        </div>
                        <div>
                            <label for="description">Description</label>
                            <input type="text" id="description" name="description">
                        </div>
                        <button type="submit" class="btn">Add part</button>
                    </div>
                </form>
            </div>
<?php endif; ?>
<?php endif; ?>
        </div>

        <aside>
<?php if ($car["id"] !== ""):
    $hasSeller = !empty($car["seller_email"]) && !empty($car["seller_phone"]);
?>
            <div class="panel contact-panel" id="contact">
                <h2>Contact seller</h2>
<?php if ($isOwner): ?>
                <p class="hint">This is your listing. Buyers who contact you see the name, phone and email from <a href="account.php">your profile</a>.</p>
<?php elseif (!$hasSeller): ?>
                <p class="hint">The seller hasn't added contact details yet. Once they do, you can reach them here.</p>
<?php else: ?>
                <div class="seller-head">
                    <span class="seller-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($car["seller_name"] ?? "?", 0, 1))) ?></span>
                    <span>
                        <b><?= htmlspecialchars($car["seller_name"] ?? "Seller") ?></b>
<?php if (!empty($car["seller_location"])): ?>
                        <small><?= htmlspecialchars($car["seller_location"] . ($car["country"] ? ", " . $car["country"] : "")) ?></small>
<?php endif; ?>
                    </span>
                </div>
<?php if ($contacted): ?>
<?php if (isset($_GET["contacted"])): ?>
                <div class="notice">&#10003; Your details were sent. You can now reach the seller directly.</div>
<?php endif; ?>
                <a class="seller-line" href="tel:<?= htmlspecialchars(preg_replace("/[^+0-9]/", "", $car["seller_phone"])) ?>">
                    <span>Phone</span><b><?= htmlspecialchars($car["seller_phone"]) ?></b>
                </a>
                <a class="seller-line" href="mailto:<?= htmlspecialchars($car["seller_email"]) ?>?subject=<?= rawurlencode("About your " . $car["name"]) ?>">
                    <span>Email</span><b><?= htmlspecialchars($car["seller_email"]) ?></b>
                </a>
<?php else: ?>
                <p class="hint">Enter accurate contact details of your own and the seller's phone and email are shown right away.</p>
<?php if ($contactErrors): ?>
                <ul class="form-errors">
<?php foreach ($contactErrors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
                <form method="post" action="edit.php?id=<?= (int)$car["id"] ?>#contact" class="contact-form">
                    <input type="hidden" name="id" value="<?= (int)$car["id"] ?>">
                    <input type="hidden" name="action" value="contact_seller">
                    <div class="field">
                        <label for="buyer_name">Your name</label>
                        <input type="text" id="buyer_name" name="buyer_name" maxlength="60" required autocomplete="name" value="<?= htmlspecialchars($contactOld["name"] ?? "") ?>">
                    </div>
                    <div class="field">
                        <label for="buyer_email">Email</label>
                        <input type="email" id="buyer_email" name="buyer_email" maxlength="120" required autocomplete="email" value="<?= htmlspecialchars($contactOld["email"] ?? "") ?>">
                    </div>
                    <div class="field">
                        <label for="buyer_phone">Phone</label>
                        <input type="tel" id="buyer_phone" name="buyer_phone" maxlength="25" required autocomplete="tel" placeholder="+386 40 123 456" value="<?= htmlspecialchars($contactOld["phone"] ?? "") ?>">
                    </div>
                    <div class="field">
                        <label for="buyer_message">Message (optional)</label>
                        <textarea id="buyer_message" name="buyer_message" rows="3" maxlength="2000"><?= htmlspecialchars($contactOld["message"] ?? ("Hi, is the " . $car["name"] . " still available?")) ?></textarea>
                    </div>
                    <button type="submit" class="btn accent">Contact seller</button>
                </form>
<?php endif; ?>
<?php endif; ?>
            </div>
<?php endif; ?>

            <div class="panel">
                <h2><?= $car["id"] === "" ? "Sell your car" : "Car data" ?></h2>
<?php if ($car["id"] !== ""): ?>
                <table class="specs">
                    <tr><th>ID</th><td>#<?= htmlspecialchars($car["id"]) ?></td></tr>
                    <tr><th>Price</th><td><?= fmt_price($car["price"]) ?></td></tr>
                    <tr><th>Location</th><td><?= !empty($car["seller_location"]) ? htmlspecialchars($car["seller_location"] . ($car["country"] ? ", " . $car["country"] : "")) : '<span class="hint">Not set</span>' ?></td></tr>
                    <tr><th>Added</th><td><?= htmlspecialchars($car["creation_date"]) ?></td></tr>
                    <tr><th>Parts</th><td><?= count($parts) ?></td></tr>
                    <tr><th>Seen by</th><td><?= (int)$views["people"] ?> <?= (int)$views["people"] === 1 ? "person" : "people" ?> <span class="hint">(<?= (int)$views["total"] ?> views)</span></td></tr>
                    <tr><th>Watching now</th><td><span class="live-dot"></span><span id="watching-now"><?= (int)$views["watching"] ?></span></td></tr>
                </table>
<?php endif; ?>
<?php if ($canEdit): ?>
                <form method="post" class="car-form" enctype="multipart/form-data" data-shrink-form>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($car["id"]) ?>">
                    <div class="field">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" maxlength="50" required placeholder="e.g. BMW 320d Touring" value="<?= htmlspecialchars($car["name"]) ?>">
                    </div>
                    <div class="field">
                        <label for="price">Price (€)</label>
                        <input type="number" id="price" name="price" min="0" step="100" placeholder="e.g. 24900" value="<?= $car["price"] !== null ? htmlspecialchars((string)(int)$car["price"]) : "" ?>">
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="seller_location">Location <span class="req" aria-hidden="true">*</span></label>
                            <input type="text" id="seller_location" name="seller_location" minlength="2" maxlength="80" required placeholder="e.g. Ljubljana" value="<?= htmlspecialchars($car["seller_location"] ?? "") ?>">
                        </div>
                        <div class="field">
                            <label for="country">Country <span class="req" aria-hidden="true">*</span></label>
                            <select id="country" name="country" required>
<?php foreach (COUNTRIES as $country): ?>
                                <option<?= ($car["country"] ?? COUNTRIES[0]) === $country ? " selected" : "" ?>><?= htmlspecialchars($country) ?></option>
<?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="field wide">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="6" maxlength="5000" placeholder="Condition, history, equipment, anything a buyer should know..."><?= htmlspecialchars($car["description"] ?? "") ?></textarea>
                    </div>
<?php if ($car["id"] === ""): ?>
                    <div class="field wide">
                        <label for="new-photos">Photos</label>
                        <input type="file" id="new-photos" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                        <p class="hint file-hint">Add one or more. JPG, PNG, WebP or GIF; big photos are shrunk automatically.</p>
                    </div>
<?php endif; ?>
                    <p class="hint profile-note wide">Buyers reach you with the name, phone and email from your profile. <a href="account.php">Edit profile</a></p>
<?php if ($car["id"] === "" && $user["is_premium"]): ?>
                    <fieldset class="plan-pick">
                        <legend>Listing</legend>
                        <div class="plan plan-premium">
                            <span class="plan-card included">
                                <b><span aria-hidden="true">&#9813;</span> Premium listing</b>
                                <span class="plan-price">Included</span>
                                <small>Your premium account puts this car at the top of All cars, in gold.</small>
                            </span>
                        </div>
                    </fieldset>
<?php elseif ($car["id"] === ""): ?>
                    <fieldset class="plan-pick">
                        <legend>How do you want to list it?</legend>
                        <label class="plan">
                            <input type="radio" name="plan" value="free"<?= !in_array($car["plan"] ?? "free", ["boost", "premium"], true) ? " checked" : "" ?>>
                            <span class="plan-card">
                                <b>Free listing</b>
                                <span class="plan-price">0 €</span>
                                <small>Listed with all the other cars.</small>
                            </span>
                        </label>
                        <label class="plan plan-boost">
                            <input type="radio" name="plan" value="boost"<?= ($car["plan"] ?? "free") === "boost" ? " checked" : "" ?>>
                            <span class="plan-card">
                                <b><span aria-hidden="true">&#8679;</span> Push forward</b>
                                <span class="plan-price"><?= fmt_eur(BOOST_WEEKLY) ?> <i>/ week</i></span>
                                <small>Shown first among the regular cars for 7 days.</small>
                            </span>
                        </label>
                        <label class="plan plan-premium">
                            <input type="radio" name="plan" value="premium"<?= ($car["plan"] ?? "free") === "premium" ? " checked" : "" ?>>
                            <span class="plan-card">
                                <b><span aria-hidden="true">&#9813;</span> Premium</b>
                                <span class="plan-price">from <?= fmt_eur(PREMIUM_MONTHLY) ?> <i>/ month</i></span>
                                <small>For your account: every car you list goes to the top, in gold.</small>
                            </span>
                        </label>
                        <!-- only shown once Premium is picked -->
                        <div class="premium-billing">
                            <label class="plan plan-premium">
                                <input type="radio" name="billing" value="monthly"<?= ($_POST["billing"] ?? "monthly") !== "yearly" ? " checked" : "" ?>>
                                <span class="plan-card">
                                    <b>Monthly</b>
                                    <span class="plan-price"><?= fmt_eur(PREMIUM_MONTHLY) ?> <i>/ month</i></span>
                                    <small>Cancel any time.</small>
                                </span>
                            </label>
                            <label class="plan plan-premium">
                                <input type="radio" name="billing" value="yearly"<?= ($_POST["billing"] ?? "") === "yearly" ? " checked" : "" ?>>
                                <span class="plan-card">
                                    <b>Yearly</b>
                                    <span class="plan-price"><?= fmt_eur(premium_yearly_price()) ?> <i>/ year</i></span>
                                    <small><s><?= fmt_eur(PREMIUM_MONTHLY * 12) ?></s> if paid monthly.</small>
                                    <span class="save-badge">Save <?= PREMIUM_YEARLY_SAVING ?>%</span>
                                </span>
                            </label>
                        </div>
                        <p class="hint demo-note">Demo: the chosen option is switched on right away, no payment is taken.</p>
                    </fieldset>
<?php endif; ?>
                    <button type="submit" class="btn accent"><?= $car["id"] === "" ? "Post car" : "Save" ?></button>
                </form>
<?php endif; ?>
            </div>

<?php if ($car["id"] !== "" && $canEdit && (int)$car["premium"] !== 1): ?>
            <div class="panel upsell">
                <span class="upsell-crown" aria-hidden="true">&#9813;</span>
                <h2>Push this car forward</h2>
                <div class="upsell-option">
<?php if ((int)$car["boosted"] === 1): ?>
                    <p><b>&#8679; Pushed until <?= htmlspecialchars(substr($car["boost_until"], 0, 16)) ?></b>. Shown first among the regular cars.</p>
<?php else: ?>
                    <p>Show it first among the regular cars for a week.</p>
<?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($car["id"]) ?>">
                        <input type="hidden" name="action" value="buy_boost">
                        <button type="submit" class="btn details"><?= (int)$car["boosted"] === 1 ? "Add another week" : "Push forward" ?> &middot; <?= fmt_eur(BOOST_WEEKLY) ?> / week</button>
                    </form>
                </div>
                <div class="upsell-option">
                    <p>Or make your <b>account premium</b>: every car you list goes to the top, in gold.</p>
                    <a class="btn gold" href="premium.php">&#9813; See premium plans</a>
                </div>
                <p class="hint demo-note">Demo: no payment is taken.</p>
            </div>
<?php endif; ?>

<?php if ($car["id"] !== "" && $canEdit): ?>
            <div class="panel">
                <h2>Remove</h2>
                <p class="hint">The car is hidden from the site but stays in the database.</p>
                <form method="post" onsubmit="return confirm('Delete this car? It stays in the database.');">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($car["id"]) ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn danger">Delete car</button>
                </form>
            </div>
<?php endif; ?>
        </aside>
    </div>
</main>

<?php if ($car["id"] !== ""): ?>
<script>
// Check in every 15 s while this page is open, so it counts as "watching now"; check out when leaving.
(() => {
    const carId = <?= (int)$car["id"] ?>;
    const out = document.getElementById("watching-now");
    async function ping() {
        try {
            const res = await fetch("views.php?action=ping", { method: "POST", body: new URLSearchParams({ car: carId }) });
            const data = await res.json();
            if (out && typeof data.watching === "number") out.textContent = data.watching;
        } catch (e) { /* offline: try again on the next tick */ }
    }
    setInterval(ping, 15000);
    document.addEventListener("visibilitychange", () => { if (!document.hidden) ping(); });
    addEventListener("pagehide", () => navigator.sendBeacon("views.php?action=leave", new URLSearchParams({ car: carId })));
})();
</script>
<?php endif; ?>

<script>
// The server accepts requests of about 1 MB in total, so photos are shrunk in the browser first,
// each to its share of that budget, before the form is sent.
const REQUEST_BUDGET = 900 * 1024;

document.querySelectorAll("form[data-shrink-form]").forEach((form) => {
    form.addEventListener("submit", async (e) => {
        const input = form.querySelector('input[type="file"]');
        if (form.dataset.ready || !input || input.files.length === 0) return;
        if (!form.checkValidity()) return;          // let the browser show what's missing first
        e.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const label = button ? button.textContent : "";
        if (button) { button.disabled = true; button.textContent = "Preparing photos..."; }

        const files = [...input.files];
        const total = files.reduce((n, f) => n + f.size, 0);
        const budget = Math.floor(REQUEST_BUDGET / files.length);
        const out = new DataTransfer();
        for (const file of files) {
            const shrinkable = /^image\/(jpeg|png|webp)$/.test(file.type);
            out.items.add(total > REQUEST_BUDGET && shrinkable && file.size > budget ? await shrink(file, budget) : file);
        }
        input.files = out.files;
        if (button) { button.textContent = label; }
        form.dataset.ready = "1";
        form.submit();
    });
});

async function shrink(file, budget) {
    try {
        const bitmap = await createImageBitmap(file);
        for (const side of [1600, 1280, 1024, 800, 640]) {
            const scale = Math.min(1, side / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement("canvas");
            canvas.width = Math.round(bitmap.width * scale);
            canvas.height = Math.round(bitmap.height * scale);
            canvas.getContext("2d").drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            for (const quality of [0.85, 0.72, 0.6]) {
                const blob = await new Promise((r) => canvas.toBlob(r, "image/jpeg", quality));
                if (blob && blob.size <= budget) {
                    return new File([blob], file.name.replace(/\.\w+$/, "") + ".jpg", { type: "image/jpeg" });
                }
            }
        }
    } catch (err) { /* fall back to the original file */ }
    return file;
}
</script>

<?php site_footer(); ?>
