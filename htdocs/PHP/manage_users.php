<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

// Admin only
if(($_SESSION["role"] ?? "admin") !== "admin") {
    header("Location: dashboard.php"); exit();
}

$success = "";
$error = "";

// Create new user
if($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {

    if($_POST["action"] === "create") {
        $username  = trim($_POST["username"]);
        $temp_pass = trim($_POST["temp_password"]);
        $role      = $_POST["role"] === "admin" ? "admin" : "mechanic";

        if(empty($username) || empty($temp_pass)) {
            $error = "Username and temporary password are required.";
        } else {
            // Check username not taken
            $check = $conn->prepare("SELECT id FROM users WHERE username=?");
            $check->bind_param("s", $username);
            $check->execute();
            if($check->get_result()->num_rows > 0) {
                $error = "That username is already taken.";
            } else {
                $hash = password_hash($temp_pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, must_change_password) VALUES (?,?,?,1)");
                $stmt->bind_param("sss", $username, $hash, $role);
                $stmt->execute();
                $success = "Account created for <strong>" . htmlspecialchars($username) . "</strong>. Temporary password: <code>" . htmlspecialchars($temp_pass) . "</code> — share this with them securely.";
            }
        }
    }

    if($_POST["action"] === "reset") {
        $uid       = intval($_POST["user_id"]);
        $temp_pass = trim($_POST["temp_password"]);
        if(!empty($temp_pass)) {
            $hash = password_hash($temp_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash=?, must_change_password=1 WHERE id=?");
            $stmt->bind_param("si", $hash, $uid);
            $stmt->execute();
            $success = "Password reset. New temporary password: <code>$temp_pass</code> — share this with them securely.";
        }
    }

    if($_POST["action"] === "delete") {
        $uid = intval($_POST["user_id"]);
        // Don't allow deleting yourself
        if($_SESSION["user_id"] != $uid) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $success = "User removed.";
        } else {
            $error = "You can't delete your own account.";
        }
    }
}

// Fetch all users
$users_result = $conn->query("SELECT id, username, role, must_change_password, created_at FROM users ORDER BY role ASC, username ASC");
$users = [];
while($r = $users_result->fetch_assoc()) $users[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users — Garage System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --steel:#1a1f2e; --steel-mid:#242938; --steel-light:#2e3447;
        --accent:#f59e0b; --green:#10b981; --red:#ef4444;
        --text:#e2e8f0; --text-muted:#7c8a9e; --border:rgba(255,255,255,0.07);
    }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}

    .page-header{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:20px 28px;}
    .page-header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;}

    .content{max-width:900px;margin:0 auto;padding:28px;}

    .panel{background:var(--steel-mid);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:24px;}
    .panel-header{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .panel-title{font-family:'Barlow Condensed',sans-serif;font-size:0.9rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);}
    .panel-body{padding:24px;}

    .field-group{margin-bottom:18px;}
    .field-label{display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:7px;}
    .field-input,.field-select{width:100%;background:var(--steel-light);border:1px solid var(--border);border-radius:7px;color:var(--text);font-family:'Barlow',sans-serif;font-size:0.95rem;padding:11px 14px;transition:border-color 0.15s;}
    .field-input:focus,.field-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(245,158,11,0.12);}
    .field-select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%237c8a9e' d='M6 8L0 0h12z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;appearance:none;cursor:pointer;}
    .field-select option{background:var(--steel-mid);}
    .form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}

    .submit-btn{padding:11px 24px;background:var(--accent);color:#000;border:none;border-radius:7px;font-family:'Barlow',sans-serif;font-size:0.9rem;font-weight:700;cursor:pointer;transition:background 0.15s;}
    .submit-btn:hover{background:#d97706;}

    .alert{padding:12px 16px;border-radius:8px;font-size:0.88rem;font-weight:500;margin-bottom:20px;}
    .alert-success{background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:var(--green);}
    .alert-error{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:var(--red);}

    .user-row{display:flex;align-items:center;padding:14px 20px;border-bottom:1px solid var(--border);gap:14px;}
    .user-row:last-child{border-bottom:none;}
    .user-avatar{width:36px;height:36px;border-radius:50%;background:var(--steel-light);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;color:var(--accent);flex-shrink:0;text-transform:uppercase;}
    .user-info{flex:1;}
    .user-name{font-weight:700;color:#fff;font-size:0.95rem;}
    .user-meta{font-size:0.8rem;color:var(--text-muted);margin-top:2px;}
    .role-badge{font-size:0.7rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 8px;border-radius:4px;flex-shrink:0;}
    .role-admin{background:rgba(245,158,11,0.15);color:var(--accent);border:1px solid rgba(245,158,11,0.3);}
    .role-mechanic{background:rgba(99,102,241,0.15);color:#818cf8;border:1px solid rgba(99,102,241,0.3);}
    .temp-badge{font-size:0.7rem;font-weight:600;background:rgba(239,68,68,0.12);color:var(--red);border:1px solid rgba(239,68,68,0.25);padding:3px 8px;border-radius:4px;flex-shrink:0;}
    .user-actions{display:flex;gap:8px;flex-shrink:0;}

    .icon-btn{padding:6px 12px;border-radius:6px;font-size:0.78rem;font-weight:600;border:1px solid var(--border);background:var(--steel-light);color:var(--text-muted);cursor:pointer;transition:all 0.15s;font-family:'Barlow',sans-serif;}
    .icon-btn:hover{border-color:var(--accent);color:var(--accent);}
    .icon-btn.danger:hover{border-color:var(--red);color:var(--red);background:rgba(239,68,68,0.08);}

    /* Reset modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;}
    .modal-overlay.active{display:flex;}
    .modal-box{background:#242938;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:28px;max-width:400px;width:90%;}
    .modal-title{font-size:1.05rem;font-weight:700;color:#fff;margin-bottom:16px;}
    .modal-actions{display:flex;gap:10px;margin-top:20px;}
    .btn-cancel{flex:1;padding:10px;background:rgba(255,255,255,0.07);color:#e2e8f0;border:1px solid rgba(255,255,255,0.1);border-radius:7px;font-weight:600;font-size:0.88rem;cursor:pointer;}
    .btn-cancel:hover{background:rgba(255,255,255,0.12);}

    @media(max-width:600px){.form-grid-2{grid-template-columns:1fr;}.user-actions{flex-direction:column;}}
</style>
</head>
<body>

<div class="page-header"><h1>Manage Users</h1></div>

<div class="content">

    <?php if($success): ?>
        <div class="alert alert-success">✓ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-error">⚠ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Create user -->
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Create New Account</span></div>
        <div class="panel-body">
            <form method="POST">
        <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="form-grid-2">
                    <div class="field-group">
                        <label class="field-label">Username</label>
                        <input class="field-input" name="username" placeholder="e.g. john" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Role</label>
                        <select class="field-select" name="role">
                            <option value="mechanic">Mechanic</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">Temporary Password</label>
                    <input class="field-input" name="temp_password" placeholder="They'll be asked to change this on first login" required>
                </div>
                <button class="submit-btn" type="submit">Create Account</button>
            </form>
        </div>
    </div>

    <!-- User list -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">All Users</span>
            <span style="font-family:'Barlow Condensed',sans-serif;font-size:1.1rem;font-weight:700;color:var(--accent);"><?php echo count($users); ?></span>
        </div>
        <?php foreach($users as $u): ?>
            <div class="user-row">
                <div class="user-avatar"><?php echo mb_substr($u["username"], 0, 1); ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($u["username"]); ?></div>
                    <div class="user-meta">Created <?php echo $u["created_at"] ? date("d M Y", strtotime($u["created_at"])) : "—"; ?></div>
                </div>
                <span class="role-badge <?php echo $u['role'] === 'admin' ? 'role-admin' : 'role-mechanic'; ?>"><?php echo ucfirst($u["role"]); ?></span>
                <?php if($u["must_change_password"]): ?>
                    <span class="temp-badge">Temp password</span>
                <?php endif; ?>
                <div class="user-actions">
                    <button class="icon-btn" onclick="openReset(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>')">Reset Password</button>
                    <?php if($_SESSION["user_id"] != $u["id"]): ?>
                        <form method="POST" style="margin:0;" class="delete-user-form" data-username="<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button class="icon-btn danger" type="submit">Remove</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Reset password modal -->
<div class="modal-overlay" id="resetModal">
    <div class="modal-box">
        <div class="modal-title">Reset password for <span id="resetName" style="color:var(--accent);"></span></div>
        <form method="POST">
        <?php csrf_field(); ?>
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="user_id" id="resetUserId">
            <div class="field-group">
                <label class="field-label">New Temporary Password</label>
                <input class="field-input" name="temp_password" id="resetPass" placeholder="Enter a temporary password" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('resetModal').classList.remove('active')">Cancel</button>
                <button class="submit-btn" type="submit" style="flex:1;">Set Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReset(id, name) {
    document.getElementById("resetUserId").value = id;
    document.getElementById("resetName").textContent = name;
    document.getElementById("resetPass").value = "";
    document.getElementById("resetModal").classList.add("active");
}

document.querySelectorAll('.delete-user-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if(!confirm('Remove user "' + this.dataset.username + '"? This cannot be undone.')) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>
