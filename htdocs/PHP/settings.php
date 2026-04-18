<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$success = "";
$error = "";

if($_SERVER["REQUEST_METHOD"] === "POST") {
    if(isset($_POST["hourly_rate"])) {
        $rate = floatval($_POST["hourly_rate"]);
        if($rate > 0) {
            $stmt = $conn->prepare("UPDATE settings SET value=? WHERE key_name='hourly_rate'");
            $stmt->bind_param("s", $rate);
            $stmt->execute();
            $success = "Hourly rate updated to £" . number_format($rate, 2) . "/hr.";
        } else {
            $error = "Please enter a valid rate greater than £0.";
        }
    }
    if(isset($_POST["ff_discount"])) {
        $disc = floatval($_POST["ff_discount"]);
        if($disc >= 0 && $disc <= 100) {
            $stmt = $conn->prepare("UPDATE settings SET value=? WHERE key_name='ff_discount'");
            $stmt->bind_param("s", $disc);
            $stmt->execute();
            $success = "Friends & family discount updated to " . number_format($disc, 1) . "%.";
        } else {
            $error = "Discount must be between 0 and 100.";
        }
    }
}

$current_rate = $conn->query("SELECT value FROM settings WHERE key_name='hourly_rate'")->fetch_assoc()["value"] ?? 50;
$current_disc = $conn->query("SELECT value FROM settings WHERE key_name='ff_discount'")->fetch_assoc()["value"] ?? 10;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — Garage System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{ --steel:#1a1f2e; --steel-mid:#242938; --steel-light:#2e3447; --accent:#f59e0b; --green:#10b981; --red:#ef4444; --text:#e2e8f0; --text-muted:#7c8a9e; --border:rgba(255,255,255,0.07); }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}
    .page-header{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:20px 28px;}
    .page-header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;margin:0;}
    .wrap{max-width:560px;margin:36px auto;padding:0 24px;display:flex;flex-direction:column;gap:20px;}

    .card{background:var(--steel-mid);border:1px solid var(--border);border-radius:10px;overflow:hidden;}
    .card-header{padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .card-title{font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);}
    .card-badge{font-family:'Barlow Condensed',sans-serif;font-size:1.4rem;font-weight:700;color:var(--accent);}
    .card-body{padding:24px;}

    .field-label{display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;}
    .input-row{display:flex;align-items:center;background:var(--steel-light);border:1px solid var(--border);border-radius:8px;overflow:hidden;transition:border-color 0.2s;}
    .input-row:focus-within{border-color:var(--accent);}
    .input-pre,.input-suf{padding:0 14px;height:48px;display:flex;align-items:center;font-size:0.9rem;color:var(--accent);font-weight:600;white-space:nowrap;}
    .input-pre{background:rgba(245,158,11,0.1);border-right:1px solid var(--border);}
    .input-suf{color:var(--text-muted);font-weight:400;border-left:1px solid var(--border);font-size:0.8rem;}
    .num-input{background:transparent;border:none;outline:none;color:#fff;font-family:'Barlow Condensed',sans-serif;font-size:1.3rem;font-weight:600;padding:0 16px;height:48px;flex:1;width:100%;}
    .num-input::placeholder{color:var(--text-muted);}
    .help{font-size:0.8rem;color:var(--text-muted);margin-top:8px;}
    .save-btn{margin-top:20px;width:100%;padding:13px;background:var(--accent);color:#000;border:none;border-radius:8px;font-family:'Barlow',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:background 0.15s;}
    .save-btn:hover{background:#d97706;}

    .alert{padding:12px 16px;border-radius:8px;font-size:0.88rem;font-weight:500;margin-bottom:4px;}
    .alert-success{background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:var(--green);}
    .alert-error{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:var(--red);}

    .discount-preview{margin-top:14px;background:var(--steel-light);border:1px solid var(--border);border-radius:8px;padding:14px 16px;}
    .dp-title{font-size:0.72rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px;}
    .dp-row{display:flex;justify-content:space-between;font-size:0.88rem;padding:4px 0;color:var(--text-muted);}
    .dp-row span:last-child{color:#fff;font-weight:600;}
    .dp-row.highlight span:last-child{color:var(--green);}
</style>
</head>
<body>
<div class="page-header"><h1>Settings</h1></div>
<div class="wrap">

    <?php if($success): ?>
        <div class="alert alert-success">✓ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-error">⚠ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Hourly Rate -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Labour Rate</span>
            <span class="card-badge">£<?php echo number_format($current_rate, 2); ?>/hr</span>
        </div>
        <div class="card-body">
            <form method="POST">
        <?php csrf_field(); ?>
                <label class="field-label">Hourly Rate</label>
                <div class="input-row">
                    <span class="input-pre">£</span>
                    <input class="num-input" type="number" name="hourly_rate" step="0.50" min="1" placeholder="<?php echo number_format($current_rate, 2); ?>" required>
                    <span class="input-suf">per hour</span>
                </div>
                <p class="help">Applies to all labour calculations across jobs, invoices, and the dashboard.</p>
                <button class="save-btn" type="submit">Save Rate</button>
            </form>
        </div>
    </div>

    <!-- Friends & Family Discount -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Friends &amp; Family Discount</span>
            <span class="card-badge"><?php echo number_format($current_disc, 1); ?>% off</span>
        </div>
        <div class="card-body">
            <form method="POST">
        <?php csrf_field(); ?>
                <label class="field-label">Discount Percentage</label>
                <div class="input-row">
                    <input class="num-input" type="number" name="ff_discount" step="0.5" min="0" max="100" placeholder="<?php echo number_format($current_disc, 1); ?>" style="padding-left:16px;" required>
                    <span class="input-suf">% discount</span>
                </div>
                <p class="help">Applied per invoice when generating it — toggle it on or off for each job.</p>
                <div class="discount-preview">
                    <div class="dp-title">Example on a £200.00 invoice</div>
                    <div class="dp-row"><span>Subtotal</span><span>£200.00</span></div>
                    <div class="dp-row"><span id="discLabel">Discount (<?php echo number_format($current_disc,1); ?>%)</span><span id="discAmount">-£<?php echo number_format(200 * $current_disc / 100, 2); ?></span></div>
                    <div class="dp-row highlight"><span>Total Due</span><span id="discTotal">£<?php echo number_format(200 - (200 * $current_disc / 100), 2); ?></span></div>
                </div>
                <button class="save-btn" type="submit">Save Discount</button>
            </form>
        </div>
    </div>

</div>
<script>
document.querySelector('[name="ff_discount"]').addEventListener('input', function() {
    const pct = parseFloat(this.value) || 0;
    const disc = 200 * pct / 100;
    document.getElementById('discLabel').textContent = 'Discount (' + pct.toFixed(1) + '%)';
    document.getElementById('discAmount').textContent = '-£' + disc.toFixed(2);
    document.getElementById('discTotal').textContent = '£' + (200 - disc).toFixed(2);
});
</script>
</body>
</html>
