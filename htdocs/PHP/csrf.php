<?php
// ─── CSRF helpers ─────────────────────────────────────────────────────────────
// Include this in any page that has a POST form.
// Usage:
//   In form:      <?php csrf_field(); ?>
//   On POST:      csrf_verify();

function csrf_token(): string {
    if(empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

// Outputs a hidden input — call inside every <form>
function csrf_field(): void {
    echo '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// Call at the top of any POST handler — dies with 403 if token missing/wrong
function csrf_verify(): void {
    if($_SERVER["REQUEST_METHOD"] !== "POST") return;
    $token = $_POST["csrf_token"] ?? "";
    if(!hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
        http_response_code(403);
        die("Invalid request token. Please go back and try again.");
    }
}
