<?php
// ─── Session hardening ────────────────────────────────────────────────────────
if(session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,           // session cookie — expires when browser closes
        'path'     => '/',
        'secure'   => true,        // HTTPS only
        'httponly' => true,        // JS cannot read the session cookie
        'samesite' => 'Strict',    // blocks CSRF via cross-site requests
    ]);
    session_start();
}

// ─── DB credentials ───────────────────────────────────────────────────────────
// Replace these values with your own database credentials before deploying.
// It is recommended to store credentials in environment variables or a
// config file outside the web root rather than hardcoding them here.
$host = "your_db_host";
$db   = "your_db_name";
$user = "your_db_user";
$pass = "your_db_password";

$conn = new mysqli($host, $user, $pass, $db);

if($conn->connect_error) {
    // Never expose connection details to the browser
    error_log("DB connect failed: " . $conn->connect_error);
    die("A system error occurred. Please try again later.");
}

// Force UTF-8 — prevents charset-based injection tricks
$conn->set_charset("utf8mb4");

// ─── Security headers — sent on every page load ───────────────────────────────
// Prevent browsers rendering pages inside iframes (clickjacking)
header("X-Frame-Options: DENY");
// Stop IE/Chrome from sniffing MIME types
header("X-Content-Type-Options: nosniff");
// Block reflected XSS in older browsers
header("X-XSS-Protection: 1; mode=block");
// Restrict what the page can load — no inline scripts, no external resources
header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'self';");
// Don't send the referrer header when leaving the site
header("Referrer-Policy: strict-origin-when-cross-origin");

// ─── CSRF helpers ─────────────────────────────────────────────────────────────
function csrf_token(): string {
    if(empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_field(): void {
    echo '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void {
    if($_SERVER["REQUEST_METHOD"] !== "POST") return;
    $token = $_POST["csrf_token"] ?? "";
    if(!hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
        http_response_code(403);
        die("Invalid request token. Please go back and try again.");
    }
}
