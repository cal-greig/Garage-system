<?php
include "config.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$error = "";

if($_SERVER["REQUEST_METHOD"] === "POST") {
    $new  = $_POST["new_password"];
    $conf = $_POST["confirm_password"];

    if(strlen($new) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif($new !== $conf) {
        $error = "Passwords don't match.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $uid  = $_SESSION["user_id"];
        $stmt = $conn->prepare("UPDATE users SET password_hash=?, must_change_password=0 WHERE id=?");
        $stmt->bind_param("si", $hash, $uid);
        $stmt->execute();
        $_SESSION["must_change_password"] = 0;
        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Your Password — Garage System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{ --steel:#1a1f2e; --steel-mid:#242938; --steel-light:#2e3447; --accent:#f59e0b; --red:#ef4444; --green:#10b981; --text:#e2e8f0; --text-muted:#7c8a9e; --border:rgba(255,255,255,0.07); }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;}
    .card{background:var(--steel-mid);border:1px solid var(--border);border-radius:12px;padding:36px 32px;width:100%;max-width:420px;box-shadow:0 8px 40px rgba(0,0,0,0.4);}
    .card-icon{font-size:2rem;margin-bottom:14px;}
    .card-title{font-family:'Barlow Condensed',sans-serif;font-size:1.6rem;font-weight:700;letter-spacing:0.03em;color:#fff;margin-bottom:6px;}
    .card-subtitle{font-size:0.88rem;color:var(--text-muted);margin-bottom:28px;line-height:1.6;}
    .field-group{margin-bottom:18px;}
    .field-label{display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:7px;}
    .field-input{width:100%;background:var(--steel-light);border:1px solid var(--border);border-radius:7px;color:var(--text);font-family:'Barlow',sans-serif;font-size:0.95rem;padding:11px 14px;transition:border-color 0.15s;}
    .field-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(245,158,11,0.12);}
    .submit-btn{width:100%;padding:13px;background:var(--accent);color:#000;border:none;border-radius:8px;font-family:'Barlow',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:background 0.15s;margin-top:8px;}
    .submit-btn:hover{background:#d97706;}
    .alert-error{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:var(--red);padding:10px 14px;border-radius:7px;font-size:0.88rem;margin-bottom:18px;}
    .strength-bar{height:4px;border-radius:2px;margin-top:6px;background:var(--steel-light);overflow:hidden;}
    .strength-fill{height:100%;width:0;border-radius:2px;transition:width 0.3s,background 0.3s;}
    .strength-label{font-size:0.75rem;color:var(--text-muted);margin-top:4px;}
</style>
</head>
<body>
<div class="card">
    <div class="card-icon">🔐</div>
    <div class="card-title">Set Your Password</div>
    <div class="card-subtitle">You're logged in with a temporary password. Please choose a new one before continuing.</div>

    <?php if($error): ?>
        <div class="alert-error">⚠ <?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_field(); ?>
        <div class="field-group">
            <label class="field-label">New Password</label>
            <input class="field-input" type="password" name="new_password" id="newPass" placeholder="At least 6 characters" required oninput="checkStrength(this.value)">
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            <div class="strength-label" id="strengthLabel"></div>
        </div>
        <div class="field-group">
            <label class="field-label">Confirm Password</label>
            <input class="field-input" type="password" name="confirm_password" placeholder="Repeat your new password" required>
        </div>
        <button class="submit-btn" type="submit">Set Password & Continue</button>
    </form>
</div>
<script>
function checkStrength(val) {
    const fill = document.getElementById("strengthFill");
    const label = document.getElementById("strengthLabel");
    let score = 0;
    if(val.length >= 6) score++;
    if(val.length >= 10) score++;
    if(/[A-Z]/.test(val)) score++;
    if(/[0-9]/.test(val)) score++;
    if(/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        {w:"0%", c:"transparent", t:""},
        {w:"25%", c:"#ef4444", t:"Weak"},
        {w:"50%", c:"#f97316", t:"Fair"},
        {w:"75%", c:"#f59e0b", t:"Good"},
        {w:"100%", c:"#10b981", t:"Strong"}
    ];
    const l = levels[Math.min(score, 4)];
    fill.style.width = l.w;
    fill.style.background = l.c;
    label.textContent = l.t;
}
</script>
</body>
</html>
