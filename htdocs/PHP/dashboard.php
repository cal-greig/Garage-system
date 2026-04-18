<?php
include "config.php";
include "navbar.php";
include "get_rate.php";

if(!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}
csrf_verify();

$hourly_rate = get_hourly_rate($conn);

// Check which columns exist so we degrade gracefully if migration hasn't been run
$cols = [];
$col_result = $conn->query("SHOW COLUMNS FROM jobs");
while($c = $col_result->fetch_assoc()) $cols[] = $c["Field"];
$has_deleted  = in_array("deleted", $cols);
$has_payment  = in_array("payment_status", $cols);

$deleted_filter  = $has_deleted  ? "AND (j.deleted=0 OR j.deleted IS NULL)" : "";
$deleted_filter2 = $has_deleted  ? "AND (deleted=0 OR deleted IS NULL)"     : "";

// Today's jobs
$today = date("Y-m-d");
$this_week_start = date("Y-m-d", strtotime("monday this week"));
$this_month      = date("Y-m");

$today_result = $conn->query("
    SELECT j.id, j.job_type, j.customer_name, j.vehicle, j.registration, j.status,
        COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id = j.id), 0) as total_hours
    FROM jobs j
    WHERE j.job_date = '$today' $deleted_filter
    ORDER BY j.status ASC
");

// Last 7 days completed jobs
$week_result = $conn->query("
    SELECT j.id, j.job_type, j.customer_name, j.vehicle, j.registration, j.job_date,
        COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id = j.id), 0) as total_hours,
        COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id = j.id), 0) as parts_cost
    FROM jobs j
    WHERE j.status = 'complete'
    $deleted_filter
    AND j.job_date >= DATE_SUB('$today', INTERVAL 7 DAY)
    ORDER BY j.job_date DESC
");

// Invoice summary stats
if($has_payment) {
    $stats = $conn->query("
        SELECT
            COUNT(*) as total_jobs,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_jobs,
            SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_jobs
        FROM jobs
        WHERE status = 'complete' $deleted_filter2
    ")->fetch_assoc();
} else {
    $stats = ["total_jobs" => 0, "paid_jobs" => 0, "unpaid_jobs" => 0];
}

// Outstanding invoices
$payment_col = $has_payment ? "j.payment_status" : "'unpaid' as payment_status";
$invoices_result = $conn->query("
    SELECT j.id, j.job_type, j.customer_name, j.vehicle, j.registration, j.job_date, $payment_col,
        COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id = j.id), 0) * $hourly_rate as labour_cost,
        COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id = j.id), 0) as parts_cost
    FROM jobs j
    WHERE j.status = 'complete' $deleted_filter
    ORDER BY j.job_date DESC
");

// Handle payment status toggle
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["toggle_payment"])) {
    $job_id = intval($_POST["job_id"]);
    $new_status = $_POST["new_status"];
    if(in_array($new_status, ["paid", "unpaid"])) {
        $stmt = $conn->prepare("UPDATE jobs SET payment_status=? WHERE id=?");
        $stmt->bind_param("si", $new_status, $job_id);
        $stmt->execute();
    }
    header("Location: dashboard.php");
    exit();
}

// ─── Analytics summary data ───────────────────────────────────────────────────
// Upcoming jobs count
$upcoming_count = $conn->query("
    SELECT COUNT(*) as n FROM jobs
    WHERE job_date > '$today' AND job_date <= DATE_ADD('$today', INTERVAL 7 DAY)
      AND (deleted=0 OR deleted IS NULL)
")->fetch_assoc()["n"] ?? 0;

// Parts used today count
$parts_today_count = $conn->query("
    SELECT COUNT(DISTINCT jp.part_name) as n
    FROM job_parts jp JOIN jobs j ON jp.job_id=j.id
    WHERE j.job_date='$today' AND (j.deleted=0 OR j.deleted IS NULL)
")->fetch_assoc()["n"] ?? 0;

// Jobs waiting for parts (linked inventory items out of stock)
$waiting_count = $conn->query("
    SELECT COUNT(DISTINCT j.id) as n FROM jobs j
    JOIN job_parts jp ON jp.job_id=j.id
    JOIN inventory i ON jp.inventory_id=i.id AND i.quantity=0
    WHERE j.status != 'complete' AND (j.deleted=0 OR j.deleted IS NULL)
")->fetch_assoc()["n"] ?? 0;

// Revenue this week & month
$rev_data = $conn->query("
    SELECT
        SUM(CASE WHEN j.job_date >= '$this_week_start' THEN
            (COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id=j.id),0) * $hourly_rate)
            + COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id=j.id),0)
        ELSE 0 END) as week_rev,
        SUM(CASE WHEN DATE_FORMAT(j.job_date,'%Y-%m')='$this_month' THEN
            (COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id=j.id),0) * $hourly_rate)
            + COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id=j.id),0)
        ELSE 0 END) as month_rev
    FROM jobs j WHERE j.status='complete' $deleted_filter2
")->fetch_assoc();
$dash_week_rev  = floatval($rev_data["week_rev"]  ?? 0);
$dash_month_rev = floatval($rev_data["month_rev"] ?? 0);

// Mechanic hours today (summary)
$mech_today = $conn->query("
    SELECT u.username,
        COALESCE(SUM(CASE WHEN j.job_date='$today' THEN jt.hours ELSE 0 END),0) as hours_today
    FROM users u
    LEFT JOIN job_time jt ON jt.logged_by=u.id
    LEFT JOIN jobs j ON jt.job_id=j.id AND (j.deleted=0 OR j.deleted IS NULL)
    GROUP BY u.id, u.username
    HAVING hours_today > 0
    ORDER BY hours_today DESC
");
$mech_today_rows = [];
if($mech_today) while($r = $mech_today->fetch_assoc()) $mech_today_rows[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Garage System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --steel: #1a1f2e;
        --steel-mid: #242938;
        --steel-light: #2e3447;
        --accent: #f59e0b;
        --accent-dim: rgba(245,158,11,0.15);
        --green: #10b981;
        --green-dim: rgba(16,185,129,0.15);
        --red: #ef4444;
        --red-dim: rgba(239,68,68,0.12);
        --orange: #f97316;
        --text: #e2e8f0;
        --text-muted: #7c8a9e;
        --border: rgba(255,255,255,0.07);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Barlow', sans-serif;
        background: var(--steel);
        color: var(--text);
        min-height: 100vh;
    }

    .page-header {
        background: var(--steel-mid);
        border-bottom: 1px solid var(--border);
        padding: 20px 28px;
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .page-header h1 {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 1.8rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #fff;
    }

    .today-badge {
        background: var(--accent-dim);
        color: var(--accent);
        border: 1px solid rgba(245,158,11,0.3);
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 4px;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto auto;
        gap: 20px;
        padding: 24px 28px;
        max-width: 1400px;
    }

    .panel {
        background: var(--steel-mid);
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
    }

    .panel-header {
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .panel-title {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .panel-count {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--accent);
    }

    /* Today's Jobs */
    .job-row {
        display: flex;
        align-items: center;
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        gap: 14px;
        transition: background 0.15s;
    }

    .job-row:last-child { border-bottom: none; }
    .job-row:hover { background: var(--steel-light); }

    .status-pip {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .pip-notstarted { background: var(--red); box-shadow: 0 0 6px var(--red); }
    .pip-inprogress { background: var(--orange); box-shadow: 0 0 6px var(--orange); }
    .pip-complete { background: var(--green); box-shadow: 0 0 6px var(--green); }

    .job-info { flex: 1; min-width: 0; }

    .job-type {
        font-weight: 700;
        font-size: 0.95rem;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .job-customer {
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .job-hours {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 1rem;
        font-weight: 600;
        color: var(--accent);
        white-space: nowrap;
        flex-shrink: 0;
    }

    .job-link {
        color: var(--text-muted);
        font-size: 0.8rem;
        text-decoration: none;
        border: 1px solid var(--border);
        padding: 3px 9px;
        border-radius: 4px;
        transition: all 0.15s;
        flex-shrink: 0;
    }

    .job-link:hover {
        color: var(--accent);
        border-color: var(--accent);
    }

    /* Stats row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        grid-column: 1 / -1;
    }

    .stat-card {
        background: var(--steel-mid);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 20px 24px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .stat-label {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .stat-value {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 2.4rem;
        font-weight: 700;
        line-height: 1;
    }

    .stat-value.green { color: var(--green); }
    .stat-value.red { color: var(--red); }
    .stat-value.amber { color: var(--accent); }

    /* Recent completed */
    .recent-row {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        border-bottom: 1px solid var(--border);
        gap: 12px;
    }

    .recent-row:last-child { border-bottom: none; }

    .recent-date {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-muted);
        width: 56px;
        flex-shrink: 0;
        text-align: center;
        background: var(--steel-light);
        padding: 4px 6px;
        border-radius: 4px;
    }

    .recent-total {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--green);
        white-space: nowrap;
        flex-shrink: 0;
    }

    /* Invoices */
    .invoice-row {
        display: flex;
        align-items: center;
        padding: 13px 20px;
        border-bottom: 1px solid var(--border);
        gap: 12px;
    }

    .invoice-row:last-child { border-bottom: none; }
    .invoice-row:hover { background: var(--steel-light); }

    .payment-badge {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 4px;
        flex-shrink: 0;
    }

    .badge-paid {
        background: var(--green-dim);
        color: var(--green);
        border: 1px solid rgba(16,185,129,0.3);
    }

    .badge-unpaid {
        background: var(--red-dim);
        color: var(--red);
        border: 1px solid rgba(239,68,68,0.3);
    }

    .invoice-total {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 1.1rem;
        font-weight: 700;
        color: #fff;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .toggle-btn {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.15s;
        flex-shrink: 0;
    }

    .toggle-btn.mark-paid {
        background: var(--green-dim);
        color: var(--green);
        border: 1px solid rgba(16,185,129,0.3);
    }

    .toggle-btn.mark-paid:hover { background: var(--green); color: #fff; }

    .toggle-btn.mark-unpaid {
        background: var(--red-dim);
        color: var(--red);
        border: 1px solid rgba(239,68,68,0.3);
    }

    .toggle-btn.mark-unpaid:hover { background: var(--red); color: #fff; }

    .empty-state {
        padding: 32px 20px;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    /* Full width panels */
    .full-width { grid-column: 1 / -1; }

    /* Analytics summary strip */
    .analytics-strip { display:grid; grid-template-columns: repeat(5, 1fr); gap:16px; grid-column: 1 / -1; }
    .anl-card { background:var(--steel-mid); border:1px solid var(--border); border-radius:10px; padding:16px 18px; display:flex; flex-direction:column; gap:4px; text-decoration:none; transition:border-color 0.15s; }
    .anl-card:hover { border-color:rgba(245,158,11,0.4); }
    .anl-label { font-size:0.7rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-muted); }
    .anl-value { font-family:'Barlow Condensed',sans-serif; font-size:1.9rem; font-weight:700; line-height:1; }
    .anl-sub { font-size:0.75rem; color:var(--text-muted); }

    /* Mechanic summary */
    .mech-summary { display:flex; flex-direction:column; gap:0; }
    .mech-row-sm { display:flex; align-items:center; padding:10px 20px; border-bottom:1px solid var(--border); gap:10px; }
    .mech-row-sm:last-child { border-bottom:none; }
    .mech-av-sm { width:26px; height:26px; border-radius:50%; background:rgba(245,158,11,0.2); color:#f59e0b; font-family:'Barlow Condensed',sans-serif; font-weight:700; font-size:0.85rem; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
    .mech-name-sm { flex:1; font-size:0.88rem; font-weight:600; color:#fff; }
    .mech-bar-wrap { display:flex; align-items:center; gap:8px; min-width:120px; }
    .mech-bar { flex:1; height:5px; background:rgba(255,255,255,0.08); border-radius:3px; }
    .mech-bar-fill { height:100%; border-radius:3px; background:var(--accent); }
    .mech-hrs-sm { font-family:'Barlow Condensed',sans-serif; font-size:0.95rem; font-weight:700; color:var(--accent); min-width:36px; text-align:right; }

    @media (max-width: 900px) {
        .dashboard-grid { grid-template-columns: 1fr; }
        .stats-row { grid-template-columns: 1fr; }
        .analytics-strip { grid-template-columns: 1fr 1fr; }
        .full-width { grid-column: 1; }
    }
</style>
</head>
<body>

<div class="page-header">
    <h1>Dashboard</h1>
    <span class="today-badge"><?php echo date("l, d M Y"); ?></span>
</div>

<?php if(!$has_payment || !$has_deleted): ?>
<div style="background:rgba(245,158,11,0.12);border-bottom:1px solid rgba(245,158,11,0.3);padding:12px 28px;font-size:0.88rem;color:#f59e0b;">
    ⚠ <strong>Database migration required</strong> — Payment tracking and job deletion won't work until you run the SQL in <code>add_payment_status.sql</code> via phpMyAdmin.
</div>
<?php endif; ?>

<div class="dashboard-grid">

    <!-- STATS ROW -->
    <?php
        $paid = intval($stats["paid_jobs"] ?? 0);
        $unpaid = intval($stats["unpaid_jobs"] ?? 0);
        $total = intval($stats["total_jobs"] ?? 0);
    ?>
    <div class="stats-row">
        <div class="stat-card">
            <span class="stat-label">Completed Jobs</span>
            <span class="stat-value amber"><?php echo $total; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Paid</span>
            <span class="stat-value green"><?php echo $paid; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Awaiting Payment</span>
            <span class="stat-value red"><?php echo $unpaid; ?></span>
        </div>
    </div>

    <!-- ANALYTICS SUMMARY STRIP -->
    <div class="analytics-strip">
        <a class="anl-card" href="analytics.php">
            <span class="anl-label">This Week</span>
            <span class="anl-value" style="color:var(--accent);">£<?php echo number_format($dash_week_rev,0); ?></span>
            <span class="anl-sub">Revenue</span>
        </a>
        <a class="anl-card" href="analytics.php">
            <span class="anl-label"><?php echo date("F"); ?></span>
            <span class="anl-value" style="color:var(--accent);">£<?php echo number_format($dash_month_rev,0); ?></span>
            <span class="anl-sub">Revenue</span>
        </a>
        <a class="anl-card" href="analytics.php">
            <span class="anl-label">Upcoming</span>
            <span class="anl-value" style="color:var(--blue,#3b82f6);"><?php echo $upcoming_count; ?></span>
            <span class="anl-sub">Jobs in next 7 days</span>
        </a>
        <a class="anl-card" href="analytics.php">
            <span class="anl-label">Parts Today</span>
            <span class="anl-value" style="color:var(--text-muted);"><?php echo $parts_today_count; ?></span>
            <span class="anl-sub">Distinct parts used</span>
        </a>
        <a class="anl-card" href="analytics.php" style="border-color:<?php echo $waiting_count > 0 ? 'rgba(249,115,22,0.4)' : 'var(--border)'; ?>">
            <span class="anl-label">Waiting Parts</span>
            <span class="anl-value" style="color:<?php echo $waiting_count > 0 ? 'var(--orange)' : 'var(--text-muted)'; ?>;"><?php echo $waiting_count; ?></span>
            <span class="anl-sub">Jobs blocked</span>
        </a>
    </div>

    <!-- MECHANIC HOURS TODAY -->
    <div class="panel full-width">
        <div class="panel-header">
            <span class="panel-title">Mechanic Hours Today</span>
            <a href="analytics.php" style="font-size:0.8rem;color:var(--text-muted);text-decoration:none;">Full Analytics →</a>
        </div>
        <?php if(empty($mech_today_rows)): ?>
            <div class="empty-state" style="padding:18px 20px;">No hours logged today yet.</div>
        <?php else: ?>
            <?php $max_h = max(array_column($mech_today_rows,'hours_today') ?: [1]); ?>
            <div class="mech-summary">
            <?php foreach($mech_today_rows as $m): ?>
                <?php $pct = $max_h > 0 ? ($m['hours_today']/$max_h*100) : 0; ?>
                <div class="mech-row-sm">
                    <div class="mech-av-sm"><?php echo strtoupper(substr($m['username'],0,1)); ?></div>
                    <div class="mech-name-sm"><?php echo htmlspecialchars($m['username']); ?></div>
                    <div class="mech-bar-wrap">
                        <div class="mech-bar"><div class="mech-bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                        <span class="mech-hrs-sm"><?php echo number_format($m['hours_today'],1); ?>h</span>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TODAY'S JOBS -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Today's Jobs</span>
            <?php
                $today_jobs = [];
                while($row = $today_result->fetch_assoc()) $today_jobs[] = $row;
                echo '<span class="panel-count">'.count($today_jobs).'</span>';
            ?>
        </div>
        <?php if(empty($today_jobs)): ?>
            <div class="empty-state">No jobs scheduled for today.</div>
        <?php else: ?>
            <?php foreach($today_jobs as $job): ?>
                <?php
                    $pip = "pip-notstarted";
                    if($job["status"] == "in progress") $pip = "pip-inprogress";
                    if($job["status"] == "complete") $pip = "pip-complete";
                    $hrs = $job["total_hours"];
                ?>
                <div class="job-row">
                    <div class="status-pip <?php echo $pip; ?>"></div>
                    <div class="job-info">
                        <div class="job-type"><?php echo htmlspecialchars($job["job_type"]); ?></div>
                        <div class="job-customer"><?php echo htmlspecialchars($job["customer_name"]); ?></div>
                    </div>
                    <?php if($hrs > 0): ?>
                        <span class="job-hours"><?php echo number_format($hrs, 1); ?>h</span>
                    <?php endif; ?>
                    <a class="job-link" href="view_job.php?id=<?php echo $job["id"]; ?>">View →</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- RECENTLY COMPLETED -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Completed — Last 7 Days</span>
        </div>
        <?php
            $week_jobs = [];
            while($row = $week_result->fetch_assoc()) $week_jobs[] = $row;
        ?>
        <?php if(empty($week_jobs)): ?>
            <div class="empty-state">No completed jobs in the last 7 days.</div>
        <?php else: ?>
            <?php foreach($week_jobs as $job): ?>
                <?php
                    $labour = $job["total_hours"] * $hourly_rate;
                    $total_cost = $labour + $job["parts_cost"];
                    $date_fmt = date("d M", strtotime($job["job_date"]));
                ?>
                <div class="recent-row">
                    <span class="recent-date"><?php echo $date_fmt; ?></span>
                    <div class="job-info">
                        <div class="job-type"><?php echo htmlspecialchars($job["job_type"]); ?></div>
                        <div class="job-customer"><?php echo htmlspecialchars($job["customer_name"]); ?></div>
                    </div>
                    <span class="recent-total">£<?php echo number_format($total_cost, 2); ?></span>
                    <a class="job-link" href="view_job.php?id=<?php echo $job["id"]; ?>">View →</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- INVOICES PANEL -->
    <div class="panel full-width">
        <div class="panel-header">
            <span class="panel-title">Invoices</span>
            <span style="font-size:0.8rem;color:var(--text-muted);">Completed jobs only</span>
        </div>
        <?php
            $invoices = [];
            while($row = $invoices_result->fetch_assoc()) $invoices[] = $row;
        ?>
        <?php if(empty($invoices)): ?>
            <div class="empty-state">No completed jobs yet.</div>
        <?php else: ?>
            <?php foreach($invoices as $inv): ?>
                <?php
                    $total_inv = $inv["labour_cost"] + $inv["parts_cost"];
                    $paid_status = $inv["payment_status"] ?? "unpaid";
                    $date_fmt = date("d M Y", strtotime($inv["job_date"]));
                ?>
                <div class="invoice-row">
                    <span class="payment-badge <?php echo $paid_status == 'paid' ? 'badge-paid' : 'badge-unpaid'; ?>">
                        <?php echo ucfirst($paid_status); ?>
                    </span>
                    <div class="job-info">
                        <div class="job-type"><?php echo htmlspecialchars($inv["job_type"]); ?> — <?php echo htmlspecialchars($inv["customer_name"]); ?></div>
                        <div class="job-customer"><?php echo htmlspecialchars($inv["vehicle"]); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($inv["registration"]); ?> &nbsp;·&nbsp; <?php echo $date_fmt; ?></div>
                    </div>
                    <span class="invoice-total">£<?php echo number_format($total_inv, 2); ?></span>
                    <form method="POST" style="margin:0;">
        <?php csrf_field(); ?>
                        <input type="hidden" name="job_id" value="<?php echo $inv["id"]; ?>">
                        <input type="hidden" name="toggle_payment" value="1">
                        <?php if($paid_status == "unpaid"): ?>
                            <input type="hidden" name="new_status" value="paid">
                            <button class="toggle-btn mark-paid">Mark Paid</button>
                        <?php else: ?>
                            <input type="hidden" name="new_status" value="unpaid">
                            <button class="toggle-btn mark-unpaid">Mark Unpaid</button>
                        <?php endif; ?>
                    </form>
                    <a class="job-link" href="view_job.php?id=<?php echo $inv["id"]; ?>">View →</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
