<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }

$filter = $_GET["status"] ?? "all";

$where = "WHERE (deleted=0 OR deleted IS NULL)";
if($filter !== "all") $where .= " AND status='" . $conn->real_escape_string($filter) . "'";
$quotes = $conn->query("SELECT * FROM quotes $where ORDER BY created_at DESC");
$rows = [];
while($r = $quotes->fetch_assoc()) $rows[] = $r;

// Counts for tabs
$counts = [];
foreach(["all","pending","accepted","declined"] as $s) {
    $w = "WHERE (deleted=0 OR deleted IS NULL)";
    if($s !== "all") $w .= " AND status='$s'";
    $counts[$s] = $conn->query("SELECT COUNT(*) as c FROM quotes $w")->fetch_assoc()["c"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quotes — Garage System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{ --steel:#1a1f2e; --steel-mid:#242938; --steel-light:#2e3447; --accent:#f59e0b; --green:#10b981; --red:#ef4444; --orange:#f97316; --text:#e2e8f0; --text-muted:#7c8a9e; --border:rgba(255,255,255,0.07); }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}
    .page-header{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:20px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
    .page-header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;}
    .new-btn{padding:9px 20px;background:var(--accent);color:#000;border:none;border-radius:7px;font-family:'Barlow',sans-serif;font-weight:700;font-size:0.88rem;text-decoration:none;cursor:pointer;transition:background 0.15s;}
    .new-btn:hover{background:#d97706;}
    .content{max-width:1100px;margin:0 auto;padding:28px;}
    .tabs{display:flex;gap:4px;margin-bottom:20px;background:var(--steel-mid);border:1px solid var(--border);border-radius:8px;padding:4px;width:fit-content;}
    .tab{padding:7px 16px;border-radius:6px;font-size:0.82rem;font-weight:600;text-decoration:none;color:var(--text-muted);transition:all 0.15s;display:flex;align-items:center;gap:6px;}
    .tab:hover{color:var(--text);}
    .tab.active{background:var(--steel-light);color:#fff;}
    .tab-count{font-size:0.72rem;background:rgba(255,255,255,0.08);padding:1px 6px;border-radius:10px;}
    .panel{background:var(--steel-mid);border:1px solid var(--border);border-radius:10px;overflow:hidden;}
    .quote-row{display:flex;align-items:center;padding:15px 20px;border-bottom:1px solid var(--border);gap:14px;transition:background 0.15s;}
    .quote-row:last-child{border-bottom:none;}
    .quote-row:hover{background:var(--steel-light);}
    .quote-num{font-family:'Barlow Condensed',sans-serif;font-size:0.9rem;font-weight:700;color:var(--accent);width:80px;flex-shrink:0;}
    .quote-info{flex:1;min-width:0;}
    .quote-customer{font-weight:700;color:#fff;font-size:0.95rem;}
    .quote-detail{font-size:0.8rem;color:var(--text-muted);margin-top:2px;}
    .quote-date{font-size:0.8rem;color:var(--text-muted);flex-shrink:0;text-align:right;}
    .quote-total{font-family:'Barlow Condensed',sans-serif;font-size:1.05rem;font-weight:700;color:#fff;flex-shrink:0;width:80px;text-align:right;}
    .status-badge{font-size:0.7rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 9px;border-radius:4px;flex-shrink:0;}
    .badge-pending{background:rgba(245,158,11,0.15);color:var(--accent);border:1px solid rgba(245,158,11,0.3);}
    .badge-accepted{background:rgba(16,185,129,0.15);color:var(--green);border:1px solid rgba(16,185,129,0.3);}
    .badge-declined{background:rgba(239,68,68,0.12);color:var(--red);border:1px solid rgba(239,68,68,0.3);}
    .view-link{color:var(--text-muted);font-size:0.8rem;text-decoration:none;border:1px solid var(--border);padding:4px 10px;border-radius:4px;transition:all 0.15s;flex-shrink:0;}
    .view-link:hover{color:var(--accent);border-color:var(--accent);}
    .empty-state{padding:48px 20px;text-align:center;color:var(--text-muted);font-size:0.9rem;}
    .empty-icon{font-size:2.5rem;margin-bottom:10px;}
</style>
</head>
<body>
<div class="page-header">
    <h1>Quotes</h1>
    <a class="new-btn" href="add_quote.php">+ New Quote</a>
    <a class="new-btn" href="deleted_quotes.php" style="background:transparent;border:1px solid var(--border);color:var(--text-muted);">🗑 Deleted</a>
</div>
<div class="content">
    <div class="tabs">
        <?php foreach(["all"=>"All","pending"=>"Pending","accepted"=>"Accepted","declined"=>"Declined"] as $key=>$label): ?>
            <a class="tab <?php echo $filter===$key?'active':''; ?>" href="quotes.php?status=<?php echo $key; ?>">
                <?php echo $label; ?> <span class="tab-count"><?php echo $counts[$key]; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="panel">
        <?php if(empty($rows)): ?>
            <div class="empty-state"><div class="empty-icon">📋</div>No quotes found.</div>
        <?php else: ?>
            <?php foreach($rows as $q): ?>
                <?php
                    $badge = "badge-pending";
                    if($q["status"]==="accepted") $badge = "badge-accepted";
                    if($q["status"]==="declined") $badge = "badge-declined";
                    $date = date("d M Y", strtotime($q["created_at"]));
                ?>
                <div class="quote-row">
                    <span class="quote-num">QUO-<?php echo str_pad($q["id"],4,"0",STR_PAD_LEFT); ?></span>
                    <div class="quote-info">
                        <div class="quote-customer"><?php echo htmlspecialchars($q["customer_name"]); ?></div>
                        <div class="quote-detail"><?php echo htmlspecialchars($q["vehicle"]); ?> · <?php echo htmlspecialchars($q["registration"]); ?> · <?php echo htmlspecialchars($q["job_type"]); ?></div>
                    </div>
                    <span class="status-badge <?php echo $badge; ?>"><?php echo ucfirst($q["status"]); ?></span>
                    <span class="quote-total">£<?php echo number_format($q["total_amount"], 2); ?></span>
                    <span class="quote-date"><?php echo $date; ?></span>
                    <a class="view-link" href="view_quote.php?id=<?php echo $q["id"]; ?>">View →</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body></html>
