<?php
require __DIR__ . "/inc.php";

// Log out only from our own form (POST with the CSRF token), never from a plain link
if ($_SERVER["REQUEST_METHOD"] === "POST" && csrf_ok()) {
    $_SESSION = [];
    session_destroy();
}
header("Location: index.php");
exit;
