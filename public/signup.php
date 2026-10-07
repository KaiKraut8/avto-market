<?php
require __DIR__ . "/inc.php";

if (current_user()) {
    header("Location: account.php");
    exit;
}

$next = safe_next($_GET["next"] ?? $_POST["next"] ?? null);
$errors = [];
$old = ["name" => "", "email" => "", "phone" => "", "location" => "", "country" => COUNTRIES[0]];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? "");
    }
    $password = $_POST["password"] ?? "";

    if (!csrf_ok()) $errors[] = "Your session expired. Please try again.";
    if (!valid_person_name($old["name"])) $errors[] = "Enter your real name (letters only, 2 to 60 characters).";
    if (!valid_email($old["email"])) $errors[] = "Enter a valid email address, like name@example.com.";
    if (!valid_phone($old["phone"])) $errors[] = "Enter a valid phone number with 8 to 15 digits, like +386 40 123 456.";
    if (mb_strlen($password) < 8) $errors[] = "Choose a password of at least 8 characters.";
    if ($old["location"] !== "" && mb_strlen($old["location"]) > 80) $errors[] = "Location is too long (max 80 characters).";
    if (!in_array($old["country"], COUNTRIES, true)) $errors[] = "Choose a country.";

    if (!$errors) {
        $email = mb_strtolower($old["email"]);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $location = $old["location"] === "" ? null : $old["location"];
        $stmt = db()->prepare("INSERT INTO users (name, email, password_hash, phone, location, country) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $old["name"], $email, $hash, $old["phone"], $location, $old["country"]);
        if ($stmt->execute()) {
            log_in(db()->insert_id);
            header("Location: " . $next);
            exit;
        }
        $errors[] = db()->errno === 1062 ? "An account with this email already exists. Log in instead." : "Could not create the account. Please try again.";
    }
}

site_header("Sign up", "signup.php");
?>

<main class="wrap auth-wrap">
    <div class="panel auth-card">
        <h1>Create your seller account</h1>
        <p class="hint">One profile for all your cars. Buyers reach you through these contact details.</p>
<?php if ($errors): ?>
        <ul class="form-errors">
<?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
            <div class="field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" maxlength="60" required autocomplete="name" placeholder="e.g. Janez Novak" value="<?= htmlspecialchars($old["name"]) ?>">
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="120" required autocomplete="email" placeholder="name@example.com" value="<?= htmlspecialchars($old["email"]) ?>">
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" maxlength="25" required autocomplete="tel" placeholder="+386 40 123 456" value="<?= htmlspecialchars($old["phone"]) ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password" placeholder="At least 8 characters">
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" maxlength="80" autocomplete="address-level2" placeholder="e.g. Ljubljana" value="<?= htmlspecialchars($old["location"]) ?>">
                </div>
                <div class="field">
                    <label for="country">Country</label>
                    <select id="country" name="country">
<?php foreach (COUNTRIES as $c): ?>
                        <option<?= $old["country"] === $c ? " selected" : "" ?>><?= htmlspecialchars($c) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn accent big">Create account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="login.php?next=<?= urlencode($next) ?>">Log in</a></p>
    </div>
</main>

<?php site_footer(); ?>
