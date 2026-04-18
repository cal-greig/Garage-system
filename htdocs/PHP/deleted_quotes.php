<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

// Handle restore
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["restore_id"])) {
    $rid = intval($_POST["restore_id"]);
    $conn->query("UPDATE quotes SET deleted=0, deleted_at=NULL, deleted_by=NULL WHERE id=$rid");
    header("Location: deleted_quotes.php"); exit;
}

$rows = [];
$result = $conn->query("SELECT * FROM quotes WHERE deleted=1 ORDER BY deleted_at DESC");
if($result) while($r = $result->fetch_assoc()) $rows[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deleted Quotes — Garage System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{--steel:#1a1f2e;--steel-mid:#242938;--steel-light:#2e3447;--accent:#f59e0b;--green:#10b981;--red:#ef4444;--text:#e2e8f0;--text-muted:#7c8a9e;--border:rgba(255,255,255,0.07);}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}
    .page-header{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:20px 28px;}
    .page-header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;}
    .content{max-width:900px;margin:0 auto;padding:28px;}
    .panel{background:var(--steel-mid);border:1px solid var(--border);border-radius:10px;overflow:hidden;}
    .quote-row{display:flex;align-items:center;padding:15px 20px;border-bottom:1px solid var(--border);gap:14px;}
    .quote-row:last-child{border-bottom:none;}
    .quote-num{font-family:'Barlow Condensed',sans-serif;font-size:0.9rem;font-weight:700;color:var(--accent);width:90px;flex-shrink:0;}
    .quote-info{flex:1;min-width:0;}
    .quote-customer{font-weight:700;color:#fff;font-size:0.95rem;}
    .quote-detail{font-size:0.8rem;color:var(--text-muted);margin-top:2px;}
    .deleted-meta{font-size:0.78rem;color:var(--red);flex-shrink:0;text-align:right;}
    .restore-btn{padding:7px 14px;background:rgba(16,185,129,0.12);color:var(--green);border:1px solid rgba(16,185,129,0.3);border-radius:6px;font-family:'Barlow',sans-serif;font-size:0.82rem;font-weight:600;cursor:pointer;transition:all 0.15s;flex-shrink:0;}
    .restore-btn:hover{background:var(--green);color:#fff;}
    .empty-state{padding:48px 20px;text-align:center;color:var(--text-muted);font-size:0.9rem;}
    .empty-icon{font-size:2.5rem;margin-bottom:10px;}
    .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);font-size:0.88rem;text-decoration:none;margin-top:20px;transition:color 0.15s;}
    .back-link:hover{color:var(--accent);}
</style>
</head>
<body>
<div class="page-header"><h1>Deleted Quotes</h1></div>
<div class="content">
    <div class="panel">
        <?php if(empty($rows)): ?>
            <div class="empty-state"><div class="empty-icon">✅</div>No deleted quotes.</div>
        <?php else: ?>
            <?php foreach($rows as $q):
                $num = "QUO-" . str_pad($q["id"], 4, "0", STR_PAD_LEFT);
                $deleted_at = $q["deleted_at"] ? date("d M Y, H:i", strtotime($q["deleted_at"])) : "Unknown";
            ?>
            <div class="quote-row">
                <span class="quote-num"><?php echo $num; ?></span>
                <div class="quote-info">
                    <div class="quote-customer"><?php echo htmlspecialchars($q["customer_name"]); ?></div>
                    <div class="quote-detail">
                        <?php echo htmlspecialchars($q["vehicle"]); ?>
                        <?php if($q["registration"]): ?> · <?php echo htmlspecialchars($q["registration"]); ?><?php endif; ?>
                        · <?php echo htmlspecialchars($q["job_type"]); ?>
                    </div>
                </div>
                <div class="deleted-meta">
                    Deleted <?php echo $deleted_at; ?><br>
                    by <?php echo htmlspecialchars($q["deleted_by"] ?? "unknown"); ?>
                </div>
                <form method="POST" style="margin:0;">
        <?php csrf_field(); ?>
                    <input type="hidden" name="restore_id" value="<?php echo $q["id"]; ?>">
                    <button class="restore-btn" type="submit">↩ Restore</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <a class="back-link" href="quotes.php">← Back to Quotes</a>
</div>
</body>
</html>
