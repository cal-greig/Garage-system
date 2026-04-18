<?php
include "config.php";
include "navbar.php";
include "get_rate.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }

$name = trim($_GET["name"] ?? "");
if($name === "") { header("Location: search.php"); exit(); }

$hourly_rate = get_hourly_rate($conn);

// ── All jobs for this customer ────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT j.id, j.job_date, j.job_type, j.vehicle, j.registration, j.status,
           j.payment_status,
           COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id=j.id),0) as total_hours,
           COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id=j.id),0) as parts_cost
    FROM jobs j
    WHERE j.customer_name = ? AND (j.deleted=0 OR j.deleted IS NULL)
    ORDER BY j.job_date DESC
");
$stmt->bind_param("s", $name);
$stmt->execute();
$jobs_result = $stmt->get_result();
$jobs = [];
while($r = $jobs_result->fetch_assoc()) $jobs[] = $r;

// ── All quotes for this customer ──────────────────────────────────────────────
$quotes = [];
$tbl = $conn->query("SHOW TABLES LIKE 'quotes'");
if($tbl && $tbl->num_rows > 0) {
    $stmt2 = $conn->prepare("
        SELECT id, created_at, job_type, vehicle, registration, status,
               COALESCE((SELECT SUM(hours*?) FROM quote_labour WHERE quote_id=quotes.id),0) as labour_cost,
               COALESCE((SELECT SUM(quantity*price) FROM quote_parts WHERE quote_id=quotes.id),0) as parts_cost
        FROM quotes
        WHERE customer_name = ? AND (deleted=0 OR deleted IS NULL)
        ORDER BY created_at DESC
    ");
    $stmt2->bind_param("ds", $hourly_rate, $name);
    $stmt2->execute();
    $qr = $stmt2->get_result();
    while($r = $qr->fetch_assoc()) $quotes[] = $r;
}

// ── Totals ────────────────────────────────────────────────────────────────────
$total_spent    = 0;
$total_unpaid   = 0;
$vehicles       = [];

foreach($jobs as $j) {
    $job_total = ($j["total_hours"] * $hourly_rate) + $j["parts_cost"];
    if($j["status"] === "complete") {
        $total_spent += $job_total;
        if(($j["payment_status"] ?? "unpaid") === "unpaid") {
            $total_unpaid += $job_total;
        }
    }
    if($j["vehicle"] && !in_array($j["vehicle"], $vehicles)) {
        $vehicles[] = $j["vehicle"];
    }
}

$first_seen = !empty($jobs) ? end($jobs)["job_date"] : null;
$last_seen  = !empty($jobs) ? $jobs[0]["job_date"]   : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($name); ?> — Customer History</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{--steel:#1a1f2e;--steel-mid:#242938;--steel-light:#2e3447;--accent:#f59e0b;--green:#10b981;--red:#ef4444;--orange:#f97316;--text:#e2e8f0;--text-muted:#7c8a9e;--border:rgba(255,255,255,0.07);}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}

    .page-header{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:20px 28px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
    .page-header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;}
    .customer-initial{width:44px;height:44px;border-radius:50%;background:rgba(245,158,11,0.2);color:#f59e0b;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.4rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .vehicle-tag{background:var(--steel-light);border:1px solid var(--border);border-radius:4px;padding:3px 9px;font-size:0.78rem;color:var(--text-muted);font-weight:600;}

    .content{max-width:1100px;padding:24px 28px;}

    /* Stats row */
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
    .stat-card{background:var(--steel-mid);border:1px solid var(--border);border-radius:10px;padding:18px 20px;}
    .stat-label{font-size:0.72rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;}
    .stat-value{font-family:'Barlow Condensed',sans-serif;font-size:2rem;font-weight:700;line-height:1;}

    /* Panel */
    .panel{background:var(--steel-mid);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:20px;}
    .panel-header{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .panel-title{font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);}
    .panel-count{font-family:'Barlow Condensed',sans-serif;font-size:1.2rem;font-weight:700;color:var(--accent);}
    .empty-state{padding:28px 20px;text-align:center;color:var(--text-muted);font-size:0.9rem;}

    /* Job rows */
    .job-row{display:flex;align-items:center;padding:13px 20px;border-bottom:1px solid var(--border);gap:14px;transition:background 0.15s;}
    .job-row:last-child{border-bottom:none;}
    .job-row:hover{background:var(--steel-light);}

    .job-date-col{font-family:'Barlow Condensed',sans-serif;font-size:0.85rem;font-weight:700;color:var(--text-muted);background:var(--steel-light);padding:4px 8px;border-radius:4px;white-space:nowrap;flex-shrink:0;min-width:62px;text-align:center;}
    .job-info{flex:1;min-width:0;}
    .job-type{font-weight:700;font-size:0.93rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .job-sub{font-size:0.78rem;color:var(--text-muted);margin-top:2px;}
    .job-total{font-family:'Barlow Condensed',sans-serif;font-size:1.1rem;font-weight:700;color:#fff;white-space:nowrap;flex-shrink:0;}
    .job-total.unpaid{color:var(--red);}

    .status-badge{font-size:0.7rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:3px 8px;border-radius:4px;flex-shrink:0;}
    .s-ns{background:rgba(239,68,68,0.12);color:#ef4444;border:1px solid rgba(239,68,68,0.3);}
    .s-ip{background:rgba(249,115,22,0.12);color:#f97316;border:1px solid rgba(249,115,22,0.3);}
    .s-cp{background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.3);}

    .pay-badge{font-size:0.68rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:2px 7px;border-radius:3px;flex-shrink:0;}
    .pay-paid{background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.3);}
    .pay-unpaid{background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.25);}

    .row-link{color:var(--text-muted);font-size:0.78rem;text-decoration:none;border:1px solid var(--border);padding:3px 8px;border-radius:4px;transition:all 0.15s;flex-shrink:0;}
    .row-link:hover{color:var(--accent);border-color:var(--accent);}

    /* Quote rows */
    .quote-row{display:flex;align-items:center;padding:13px 20px;border-bottom:1px solid var(--border);gap:14px;transition:background 0.15s;}
    .quote-row:last-child{border-bottom:none;}
    .quote-row:hover{background:var(--steel-light);}
    .q-pending{background:rgba(245,158,11,0.1);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);}
    .q-accepted{background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.3);}
    .q-declined{background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.25);}

    .unpaid-banner{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;}
    .unpaid-label{font-size:0.85rem;color:#ef4444;}
    .unpaid-amount{font-family:'Barlow Condensed',sans-serif;font-size:1.6rem;font-weight:700;color:#ef4444;}

    .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);font-size:0.88rem;text-decoration:none;transition:color 0.15s;margin-top:4px;}
    .back-link:hover{color:var(--accent);}

    @media(max-width:700px){.stats-row{grid-template-columns:1fr 1fr;}}
</style>
</head>
<body>

<div class="page-header">
    <div class="customer-initial"><?php echo strtoupper(substr($name,0,1)); ?></div>
    <div>
        <h1><?php echo htmlspecialchars($name); ?></h1>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
            <?php foreach($vehicles as $v): ?>
                <span class="vehicle-tag">🚗 <?php echo htmlspecialchars($v); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="content">

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total Jobs</div>
            <div class="stat-value" style="color:var(--accent);"><?php echo count($jobs); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Spent</div>
            <div class="stat-value" style="color:var(--green);">£<?php echo number_format($total_spent,0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Outstanding</div>
            <div class="stat-value" style="color:<?php echo $total_unpaid > 0 ? 'var(--red)' : 'var(--text-muted)'; ?>;">
                £<?php echo number_format($total_unpaid,0); ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Customer Since</div>
            <div class="stat-value" style="color:var(--text-muted);font-size:1.3rem;">
                <?php echo $first_seen ? date("M Y", strtotime($first_seen)) : "—"; ?>
            </div>
        </div>
    </div>

    <!-- Outstanding balance banner -->
    <?php if($total_unpaid > 0): ?>
    <div class="unpaid-banner">
        <div>
            <div class="unpaid-label">⚠ Outstanding balance — <?php echo count(array_filter($jobs, fn($j) => $j['status']==='complete' && ($j['payment_status']??'unpaid')==='unpaid')); ?> unpaid invoice<?php echo count(array_filter($jobs, fn($j) => $j['status']==='complete' && ($j['payment_status']??'unpaid')==='unpaid')) !== 1 ? 's' : ''; ?></div>
        </div>
        <div class="unpaid-amount">£<?php echo number_format($total_unpaid,2); ?></div>
    </div>
    <?php endif; ?>

    <!-- Jobs -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Jobs</span>
            <span class="panel-count"><?php echo count($jobs); ?></span>
        </div>
        <?php if(empty($jobs)): ?>
            <div class="empty-state">No jobs found for this customer.</div>
        <?php else: ?>
            <?php foreach($jobs as $j):
                $job_total = ($j["total_hours"] * $hourly_rate) + $j["parts_cost"];
                $paid      = ($j["payment_status"] ?? "unpaid") === "paid";
                $s_class   = match($j["status"]) { "in progress"=>"s-ip","complete"=>"s-cp",default=>"s-ns" };
                $s_label   = match($j["status"]) { "in progress"=>"In Progress","complete"=>"Complete",default=>"Not Started" };
            ?>
            <div class="job-row">
                <div class="job-date-col"><?php echo date("d M", strtotime($j["job_date"])); ?><br><span style="font-size:0.7rem;"><?php echo date("Y", strtotime($j["job_date"])); ?></span></div>
                <div class="job-info">
                    <div class="job-type"><?php echo htmlspecialchars($j["job_type"]); ?></div>
                    <div class="job-sub"><?php echo htmlspecialchars($j["vehicle"]); ?> · <?php echo htmlspecialchars($j["registration"]); ?></div>
                </div>
                <span class="status-badge <?php echo $s_class; ?>"><?php echo $s_label; ?></span>
                <?php if($j["status"] === "complete"): ?>
                    <span class="pay-badge <?php echo $paid ? 'pay-paid' : 'pay-unpaid'; ?>"><?php echo $paid ? 'Paid' : 'Unpaid'; ?></span>
                <?php endif; ?>
                <span class="job-total <?php echo !$paid && $j['status']==='complete' ? 'unpaid' : ''; ?>">
                    £<?php echo number_format($job_total, 2); ?>
                </span>
                <a class="row-link" href="view_job.php?id=<?php echo $j["id"]; ?>">View →</a>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Quotes -->
    <?php if(!empty($quotes)): ?>
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Quotes</span>
            <span class="panel-count"><?php echo count($quotes); ?></span>
        </div>
        <?php foreach($quotes as $qt):
            $qt_total = $qt["labour_cost"] + $qt["parts_cost"];
            $q_class  = match($qt["status"]) { "accepted"=>"q-accepted","declined"=>"q-declined",default=>"q-pending" };
        ?>
        <div class="quote-row">
            <div class="job-date-col"><?php echo date("d M", strtotime($qt["created_at"])); ?><br><span style="font-size:0.7rem;"><?php echo date("Y", strtotime($qt["created_at"])); ?></span></div>
            <div class="job-info">
                <div class="job-type"><?php echo htmlspecialchars($qt["job_type"] ?: "Quote"); ?></div>
                <div class="job-sub"><?php echo htmlspecialchars($qt["vehicle"]); ?> · <?php echo htmlspecialchars($qt["registration"]); ?></div>
            </div>
            <span class="status-badge <?php echo $q_class; ?>"><?php echo ucfirst($qt["status"]); ?></span>
            <?php if($qt_total > 0): ?>
                <span class="job-total">£<?php echo number_format($qt_total,2); ?></span>
            <?php endif; ?>
            <a class="row-link" href="view_quote.php?id=<?php echo $qt["id"]; ?>">View →</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <a class="back-link" href="javascript:history.back()">← Back</a>
</div>

</body>
</html>
