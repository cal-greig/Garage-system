<?php
include "config.php";

if(isset($_SESSION["user"])) {
    header("Location: dashboard.php"); exit;
}

$error   = "";
$locked  = false;
$lockout_minutes = 15;
$max_attempts    = 5;

// ─── Rate limiting via session ────────────────────────────────────────────────
// Tracks attempts per browser session — lightweight, no extra DB table needed
if(!isset($_SESSION["login_attempts"]))  $_SESSION["login_attempts"]  = 0;
if(!isset($_SESSION["login_locked_until"])) $_SESSION["login_locked_until"] = 0;

$now = time();
if($_SESSION["login_locked_until"] > $now) {
    $locked        = true;
    $remaining_min = ceil(($_SESSION["login_locked_until"] - $now) / 60);
    $error = "Too many failed attempts. Please wait {$remaining_min} minute" . ($remaining_min > 1 ? "s" : "") . " before trying again.";
}

// ─── Generate CSRF token ──────────────────────────────────────────────────────
if(empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if(!$locked && $_SERVER["REQUEST_METHOD"] === "POST") {

    // Verify CSRF token
    if(!hash_equals($_SESSION["csrf_token"] ?? "", $_POST["csrf_token"] ?? "")) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        // Basic input sanity — no empty fields
        if($username === "" || $password === "") {
            $error = "Please enter your username and password.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            // Always run password_verify even if user not found —
            // prevents timing attacks revealing valid usernames
            $hash_to_check = $user ? $user["password_hash"] : '$2y$10$invalidhashpadding000000000000000000000000000000000000000';
            $valid = password_verify($password, $hash_to_check);

            if($user && $valid) {
                // Successful login — reset counters, regenerate session ID
                $_SESSION["login_attempts"]    = 0;
                $_SESSION["login_locked_until"] = 0;
                session_regenerate_id(true); // prevents session fixation

                $_SESSION["user"]                 = $user["username"];
                $_SESSION["user_id"]              = $user["id"];
                $_SESSION["role"]                 = $user["role"];
                $_SESSION["must_change_password"] = $user["must_change_password"];

                // Rotate CSRF token on login
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                if($user["must_change_password"]) {
                    header("Location: change_password.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;

            } else {
                // Failed attempt
                $_SESSION["login_attempts"]++;
                if($_SESSION["login_attempts"] >= $max_attempts) {
                    $_SESSION["login_locked_until"] = $now + ($lockout_minutes * 60);
                    $_SESSION["login_attempts"]     = 0;
                    $locked = true;
                    $error  = "Too many failed attempts. Please wait {$lockout_minutes} minutes before trying again.";
                } else {
                    $remaining = $max_attempts - $_SESSION["login_attempts"];
                    $error = "Invalid username or password. " . $remaining . " attempt" . ($remaining > 1 ? "s" : "") . " remaining.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Garage System — Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body{ font-family:'Barlow',sans-serif; }
        .card{ background:#242938; border:1px solid rgba(255,255,255,0.07); border-radius:12px; }
        .form-control{ background:#2e3447; border-color:rgba(255,255,255,0.07); color:#e2e8f0; }
        .form-control:focus{ background:#2e3447; border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,0.12); color:#e2e8f0; }
        .form-control::placeholder{ color:#7c8a9e; }
        .form-label{ font-size:0.75rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:#7c8a9e; }
        .btn-login{ background:#f59e0b; border:none; color:#000; font-weight:700; font-size:0.95rem; padding:12px; }
        .btn-login:hover{ background:#d97706; color:#000; }
        .btn-login:disabled{ background:#555; color:#999; cursor:not-allowed; }
        h4{ font-family:'Barlow Condensed',sans-serif; font-size:1.5rem; letter-spacing:0.04em; text-transform:uppercase; color:#fff; }
        .attempts-bar{ height:3px; background:rgba(255,255,255,0.08); border-radius:2px; margin-top:8px; }
        .attempts-fill{ height:100%; border-radius:2px; background:#ef4444; transition:width 0.3s; }
    </style>
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="card p-4 shadow" style="width:100%;max-width:380px;">
    <h4 class="mb-1 text-center">Your Garage Name</h4>
    <p class="text-center mb-4" style="color:#7c8a9e;font-size:0.85rem;">Sign in to your account</p>

    <?php if($error): ?>
        <div class="alert <?php echo $locked ? 'alert-danger' : 'alert-warning'; ?> py-2 text-center" style="font-size:0.88rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" <?php echo $locked ? 'onsubmit="return false;"' : ''; ?>>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" required autofocus
                   <?php echo $locked ? 'disabled' : ''; ?>
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required
                   <?php echo $locked ? 'disabled' : ''; ?>>
        </div>
        <?php if(!$locked && $_SESSION["login_attempts"] > 0): ?>
            <div class="attempts-bar mb-3">
                <div class="attempts-fill" style="width:<?php echo ($_SESSION['login_attempts'] / $max_attempts) * 100; ?>%"></div>
            </div>
        <?php endif; ?>
        <button class="btn btn-login w-100" <?php echo $locked ? 'disabled' : ''; ?>>
            <?php echo $locked ? '🔒 Account Locked' : 'Sign In'; ?>
        </button>
    </form>
</div>
</body>
</html>
