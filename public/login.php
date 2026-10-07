<?php
require __DIR__ . "/inc.php";

if (current_user()) {
    header("Location: account.php");
    exit;
}

$next = safe_next($_GET["next"] ?? $_POST["next"] ?? null);
$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = mb_strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    if (!csrf_ok()) {
        $error = "Your session expired. Please try again.";
    } else {
        $stmt = db()->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        // the same message whether the email or the password is wrong
        if ($row && password_verify($password, $row["password_hash"])) {
            log_in((int)$row["id"]);
            header("Location: " . $next);
            exit;
        }
        $error = "Wrong email or password.";
    }
}

site_header("Log in", "login.php");
?>

<main class="wrap auth-wrap">
    <div class="panel auth-card">
        <h1>Log in</h1>
        <p class="hint">Log in to sell cars and manage your profile.</p>
<?php if ($error): ?>
        <ul class="form-errors"><li><?= htmlspecialchars($error) ?></li></ul>
<?php endif; ?>
        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="120" required autocomplete="email" placeholder="name@example.com" value="<?= htmlspecialchars($email) ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Your password">
            </div>
            <button type="submit" class="btn accent big">Log in</button>
        </form>
        <p class="auth-switch">New here? <a href="signup.php?next=<?= urlencode($next) ?>">Create a seller account</a></p>
    </div>
</main>

<?php site_footer(); ?>
