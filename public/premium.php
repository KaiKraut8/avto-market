<?php
require __DIR__ . "/inc.php";

$user = current_user();

// Buy premium for the logged-in seller's account (simulated purchase)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$user) {
        header("Location: login.php?next=premium.php");
        exit;
    }
    $ok = csrf_ok() && buy_account_premium(db(), (int)$user["id"], ($_POST["billing"] ?? "monthly") === "yearly" ? "yearly" : "monthly");
    header("Location: premium.php?" . ($ok ? "bought=1" : "failed=1"));
    exit;
}

$yearly = premium_yearly_price();

site_header("Premium", "premium.php");
?>

<main class="wrap premium-page">
    <div class="premium-head">
        <span class="premium-hero-crown" aria-hidden="true">&#9813;</span>
        <h1>Premium <span>seller account</span></h1>
        <p class="lede">Every car you list is shown first on All cars and the home page, in gold.</p>
    </div>

<?php if (isset($_GET["bought"]) && $user && $user["is_premium"]): ?>
    <div class="notice">&#9813; Your account is now premium (<?= $user["premium_plan"] === "yearly" ? "yearly" : "monthly" ?>, until <?= htmlspecialchars(substr($user["premium_until"], 0, 10)) ?>). <a href="list.php">See your cars at the top</a>.</div>
<?php elseif (isset($_GET["failed"])): ?>
    <div class="notice error">Premium couldn't be activated. Your account may already be premium.</div>
<?php endif; ?>

<?php if ($user && $user["is_premium"]): ?>
    <div class="panel premium-status">
        <h2>&#9813; You're a premium seller</h2>
        <p><?= $user["premium_plan"] === "yearly" ? "Yearly" : "Monthly" ?> plan, active until <b><?= htmlspecialchars(substr($user["premium_until"], 0, 10)) ?></b>.</p>
        <a class="btn gold" href="edit.php">+ Sell a car</a>
    </div>
<?php else: ?>
    <form method="post" class="panel buy-box">
        <?= csrf_field() ?>
        <fieldset class="plan-pick billing-pick">
            <legend>Billing</legend>
            <label class="plan plan-premium">
                <input type="radio" name="billing" value="monthly" checked>
                <span class="plan-card">
                    <b>Monthly</b>
                    <span class="plan-price"><?= fmt_eur(PREMIUM_MONTHLY) ?> <i>/ month</i></span>
                    <small>Cancel any time.</small>
                </span>
            </label>
            <label class="plan plan-premium">
                <input type="radio" name="billing" value="yearly">
                <span class="plan-card">
                    <b>Yearly</b>
                    <span class="plan-price"><?= fmt_eur($yearly) ?> <i>/ year</i></span>
                    <small><s><?= fmt_eur(PREMIUM_MONTHLY * 12) ?></s> if paid monthly &middot; about <?= fmt_eur($yearly / 12) ?> a month</small>
                    <span class="save-badge">Save <?= PREMIUM_YEARLY_SAVING ?>%</span>
                </span>
            </label>
        </fieldset>
        <div class="buy-total"><span>Total</span><b id="buy-total"><?= fmt_eur(PREMIUM_MONTHLY) ?></b></div>
<?php if ($user): ?>
        <button type="submit" class="btn gold big">&#9813; Buy premium</button>
        <p class="hint demo-note">Demo: premium is switched on right away and no payment is taken.</p>
<?php else: ?>
        <a class="btn gold big" href="signup.php?next=premium.php">Create a seller account</a>
        <p class="hint demo-note">Premium is for seller accounts. Already have one? <a href="login.php?next=premium.php">Log in</a>.</p>
<?php endif; ?>
    </form>
<?php endif; ?>
</main>

<script>
// Show the total for the chosen billing period
document.querySelectorAll('input[name="billing"]').forEach((r) => r.addEventListener("change", () => {
    document.getElementById("buy-total").textContent = r.value === "yearly"
        ? <?= json_encode(fmt_eur($yearly)) ?> : <?= json_encode(fmt_eur(PREMIUM_MONTHLY)) ?>;
}));
</script>

<?php site_footer(); ?>
