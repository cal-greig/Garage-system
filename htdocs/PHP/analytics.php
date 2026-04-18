<?php
include "config.php";
include "navbar.php";
include "get_rate.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$hourly_rate = get_hourly_rate($conn);
$today       = date("Y-m-d");
$this_month  = date("Y-m");
$this_week_start = date("Y-m-d", strtotime("monday this week"));

// ─── Hours logged today per mechanic ─────────────────────────────────────────
$mech_hours = $conn->query("
    SELECT u.username, u.id as user_id,
        COALESCE(SUM(CASE WHEN j.job_date = '$today' THEN jt.hours ELSE 0 END), 0) as hours_today,
        COALESCE(SUM(CASE WHEN j.job_date >= '$this_week_start' THEN jt.hours ELSE 0 END), 0) as hours_week,
        COALESCE(SUM(CASE WHEN DATE_FORMAT(j.job_date,'%Y-%m') = '$this_month' THEN jt.hours ELSE 0 END), 0) as hours_month,
        COUNT(DISTINCT CASE WHEN j.job_date = '$today' THEN j.id END) as jobs_today
    FROM users u
    LEFT JOIN job_time jt ON jt.logged_by = u.id
    LEFT JOIN jobs j ON jt.job_id = j.id AND (j.deleted=0 OR j.deleted IS NULL)
    GROUP BY u.id, u.username
    ORDER BY hours_today DESC, u.username ASC
");

// Fallback if logged_by column doesn't exist — just show all users with zero hours
$mech_rows = [];
if($mech_hours) {
    while($r = $mech_hours->fetch_assoc()) $mech_rows[] = $r;
}
if(empty($mech_rows)) {
    $users = $conn->query("SELECT id, username FROM users ORDER BY username");
    while($u = $users->fetch_assoc()) {
        $mech_rows[] = ['username'=>$u['username'],'user_id'=>$u['id'],'hours_today'=>0,'hours_week'=>0,'hours_month'=>0,'jobs_today'=>0];
    }
}

// ─── Upcoming jobs next 7 days ────────────────────────────────────────────────
$upcoming = $conn->query("
    SELECT id, job_type, customer_name, vehicle, registration, job_date, status
    FROM jobs
    WHERE job_date > '$today'
      AND job_date <= DATE_ADD('$today', INTERVAL 7 DAY)
      AND (deleted=0 OR deleted IS NULL)
    ORDER BY job_date ASC
");
$upcoming_jobs = [];
while($r = $upcoming->fetch_assoc()) $upcoming_jobs[] = $r;

// ─── Parts used today ─────────────────────────────────────────────────────────
$parts_today = $conn->query("
    SELECT jp.part_name, SUM(jp.quantity) as total_qty, SUM(jp.quantity * jp.price) as total_cost
    FROM job_parts jp
    JOIN jobs j ON jp.job_id = j.id
    WHERE j.job_date = '$today' AND (j.deleted=0 OR j.deleted IS NULL)
    GROUP BY jp.part_name
    ORDER BY total_qty DESC
");
$parts_today_rows = [];
while($r = $parts_today->fetch_assoc()) $parts_today_rows[] = $r;

// ─── Jobs waiting for parts ───────────────────────────────────────────────────
// A job is "waiting for parts" if status = 'in progress' or 'not started' and has parts
// with inventory_id linked to items that are out of stock — OR if job has a "waiting" note
// We use: jobs with status != complete that have ANY part linked to inventory where qty=0
$waiting_parts = $conn->query("
    SELECT DISTINCT j.id, j.job_type, j.customer_name, j.vehicle, j.registration, j.job_date, j.status,
        GROUP_CONCAT(DISTINCT i.part_name ORDER BY i.part_name SEPARATOR ', ') as missing_parts
    FROM jobs j
    JOIN job_parts jp ON jp.job_id = j.id
    JOIN inventory i ON jp.inventory_id = i.id AND i.quantity = 0
    WHERE j.status != 'complete' AND (j.deleted=0 OR j.deleted IS NULL)
    GROUP BY j.id
    ORDER BY j.job_date ASC
");
$waiting_rows = [];
while($r = $waiting_parts->fetch_assoc()) $waiting_rows[] = $r;

// ─── Revenue this week / this month ──────────────────────────────────────────
$rev = $conn->query("
    SELECT
        SUM(CASE WHEN j.job_date >= '$this_week_start' THEN
            (COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id=j.id),0) * $hourly_rate)
            + COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id=j.id),0)
        ELSE 0 END) as week_revenue,
        SUM(CASE WHEN DATE_FORMAT(j.job_date,'%Y-%m') = '$this_month' THEN
            (COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id=j.id),0) * $hourly_rate)
            + COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id=j.id),0)
        ELSE 0 END) as month_revenue,
        SUM(CASE WHEN j.job_date >= '$this_week_start' AND j.payment_status='paid' THEN
            (COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id=j.id),0) * $hourly_rate)
            + COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id=j.id),0)
        ELSE 0 END) as week_paid,
        SUM(CASE WHEN DATE_FORMAT(j.job_date,'%Y-%m') = '$this_month' AND j.payment_status='paid' THEN
            (COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id=j.id),0) * $hourly_rate)
            + COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id=j.id),0)
        ELSE 0 END) as month_paid
    FROM jobs j
    WHERE j.status='complete' AND (j.deleted=0 OR j.deleted IS NULL)
")->fetch_assoc();

$week_rev   = floatval($rev["week_revenue"]  ?? 0);
$month_rev  = floatval($rev["month_revenue"] ?? 0);
$week_paid  = floatval($rev["week_paid"]     ?? 0);
$month_paid = floatval($rev["month_paid"]    ?? 0);

// ─── Today's total hours across all mechanics ────────────────────────────────
$total_hours_today = array_sum(array_column($mech_rows, 'hours_today'));
$total_hours_week  = array_sum(array_column($mech_rows, 'hours_week'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics — Garage System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --steel:#1a1f2e; --steel-mid:#242938; --steel-light:#2e3447;
        --accent:#f59e0b; --green:#10b981; --red:#ef4444; --orange:#f97316;
        --blue:#3b82f6;
        --text:#e2e8f0; --text-muted:#7c8a9e; --border:rgba(255,255,255,0.07);
    }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}

    .page-header{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:20px 28px;display:flex;align-items:center;gap:16px;}
    .page-header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;}
    .today-badge{background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);font-size:0.78rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;padding:4px 10px;border-radius:4px;}

    .analytics-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:24px 28px;max-width:1400px;}
    .full-width{grid-column:1 / -1;}

    .panel{background:var(--steel-mid);border:1px solid var(--border);border-radius:10px;overflow:hidden;}
    .panel-header{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .panel-title{font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);}
    .panel-count{font-family:'Barlow Condensed',sans-serif;font-size:1.3rem;font-weight:700;color:var(--accent);}
    .empty-state{padding:28px 20px;text-align:center;color:var(--text-muted);font-size:0.9rem;}

    /* Revenue cards */
    .rev-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;padding:20px;}
    .rev-card{background:var(--steel-light);border-radius:8px;padding:16px 18px;}
    .rev-label{font-size:0.72rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;}
    .rev-value{font-family:'Barlow Condensed',sans-serif;font-size:2rem;font-weight:700;line-height:1;}
    .rev-sub{font-size:0.78rem;color:var(--text-muted);margin-top:4px;}

    /* Mechanic table */
    .mech-table{width:100%;border-collapse:collapse;}
    .mech-table th{font-size:0.68rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);text-align:left;padding:10px 20px;border-bottom:1px solid var(--border);}
    .mech-table th.right{text-align:right;}
    .mech-table td{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.9rem;vertical-align:middle;}
    .mech-table td.right{text-align:right;}
    .mech-table tr:last-child td{border-bottom:none;}
    .mech-table tr:hover td{background:var(--steel-light);}

    .mech-avatar{width:32px;height:32px;border-radius:50%;background:rgba(245,158,11,0.2);color:#f59e0b;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1rem;display:inline-flex;align-items:center;justify-content:center;margin-right:10px;flex-shrink:0;}
    .mech-name-wrap{display:flex;align-items:center;}
    .hours-bar-wrap{display:flex;align-items:center;gap:10px;}
    .hours-bar{flex:1;height:6px;background:rgba(255,255,255,0.08);border-radius:3px;min-width:60px;}
    .hours-bar-fill{height:100%;border-radius:3px;background:var(--accent);transition:width 0.4s;}
    .hours-num{font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:700;color:var(--accent);white-space:nowrap;min-width:44px;text-align:right;}
    .hours-zero{color:var(--text-muted);}

    /* Upcoming jobs */
    .upcoming-row{display:flex;align-items:center;padding:12px 20px;border-bottom:1px solid var(--border);gap:12px;}
    .upcoming-row:last-child{border-bottom:none;}
    .upcoming-row:hover{background:var(--steel-light);}
    .day-badge{font-family:'Barlow Condensed',sans-serif;font-size:0.85rem;font-weight:700;color:#fff;background:var(--steel-light);padding:4px 10px;border-radius:5px;white-space:nowrap;flex-shrink:0;text-align:center;min-width:58px;}
    .day-badge.today-bg{background:rgba(245,158,11,0.2);color:var(--accent);}
    .upcoming-info{flex:1;min-width:0;}
    .upcoming-type{font-weight:700;font-size:0.92rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .upcoming-sub{font-size:0.8rem;color:var(--text-muted);margin-top:2px;}
    .status-pill{font-size:0.7rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:3px 8px;border-radius:4px;flex-shrink:0;}
    .pill-ns{background:rgba(239,68,68,0.12);color:#ef4444;border:1px solid rgba(239,68,68,0.3);}
    .pill-ip{background:rgba(249,115,22,0.12);color:#f97316;border:1px solid rgba(249,115,22,0.3);}
    .pill-cp{background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.3);}
    .job-link{color:var(--text-muted);font-size:0.8rem;text-decoration:none;border:1px solid var(--border);padding:3px 9px;border-radius:4px;transition:all 0.15s;flex-shrink:0;}
    .job-link:hover{color:var(--accent);border-color:var(--accent);}

    /* Parts today */
    .parts-row{display:flex;align-items:center;padding:11px 20px;border-bottom:1px solid var(--border);gap:12px;font-size:0.88rem;}
    .parts-row:last-child{border-bottom:none;}
    .parts-name{flex:1;font-weight:600;color:#fff;}
    .parts-qty{font-family:'Barlow Condensed',sans-serif;font-weight:700;color:var(--accent);font-size:1rem;}
    .parts-cost{color:var(--text-muted);font-size:0.83rem;}

    /* Waiting for parts */
    .waiting-row{padding:13px 20px;border-bottom:1px solid var(--border);}
    .waiting-row:last-child{border-bottom:none;}
    .waiting-row:hover{background:var(--steel-light);}
    .waiting-top{display:flex;align-items:center;gap:10px;margin-bottom:4px;}
    .waiting-job{font-weight:700;font-size:0.92rem;color:#fff;flex:1;}
    .waiting-parts-list{font-size:0.8rem;color:#f97316;margin-top:2px;}
    .waiting-meta{font-size:0.8rem;color:var(--text-muted);}

    @media(max-width:900px){
        .analytics-grid{grid-template-columns:1fr;}
        .full-width{grid-column:1;}
        .rev-grid{grid-template-columns:1fr 1fr;}
    }
</style>
</head>
<body>

<div class="page-header">
    <h1>Analytics</h1>
    <span class="today-badge"><?php echo date("l, d M Y"); ?></span>
</div>

<div class="analytics-grid">

    <!-- ── REVENUE ──────────────────────────────────────────────────────── -->
    <div class="panel full-width">
        <div class="panel-header">
            <span class="panel-title">Revenue</span>
            <span style="font-size:0.8rem;color:var(--text-muted);">Completed jobs only · Rate: £<?php echo number_format($hourly_rate,2); ?>/hr</span>
        </div>
        <div class="rev-grid">
            <div class="rev-card">
                <div class="rev-label">This Week — Total</div>
                <div class="rev-value" style="color:var(--accent);">£<?php echo number_format($week_rev,2); ?></div>
                <div class="rev-sub">All completed jobs</div>
            </div>
            <div class="rev-card">
                <div class="rev-label">This Week — Paid</div>
                <div class="rev-value" style="color:var(--green);">£<?php echo number_format($week_paid,2); ?></div>
                <div class="rev-sub">Marked as paid</div>
            </div>
            <div class="rev-card">
                <div class="rev-label"><?php echo date("F"); ?> — Total</div>
                <div class="rev-value" style="color:var(--accent);">£<?php echo number_format($month_rev,2); ?></div>
                <div class="rev-sub">All completed jobs</div>
            </div>
            <div class="rev-card">
                <div class="rev-label"><?php echo date("F"); ?> — Paid</div>
                <div class="rev-value" style="color:var(--green);">£<?php echo number_format($month_paid,2); ?></div>
                <div class="rev-sub">Marked as paid</div>
            </div>
        </div>
    </div>

    <!-- ── MECHANIC HOURS ───────────────────────────────────────────────── -->
    <div class="panel full-width">
        <div class="panel-header">
            <span class="panel-title">Mechanic Hours</span>
            <span style="font-size:0.8rem;color:var(--text-muted);">
                Today: <strong style="color:var(--accent);"><?php echo number_format($total_hours_today,1); ?>h</strong>
                &nbsp;·&nbsp; This week: <strong style="color:var(--accent);"><?php echo number_format($total_hours_week,1); ?>h</strong>
            </span>
        </div>
        <?php if(empty($mech_rows)): ?>
            <div class="empty-state">No mechanics found.</div>
        <?php else: ?>
            <?php $max_today = max(array_column($mech_rows,'hours_today') ?: [1]); ?>
            <table class="mech-table">
                <thead>
                    <tr>
                        <th>Mechanic</th>
                        <th>Jobs Today</th>
                        <th>Hours Today</th>
                        <th class="right">This Week</th>
                        <th class="right">This Month</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($mech_rows as $m): ?>
                    <?php $pct = $max_today > 0 ? ($m['hours_today'] / $max_today * 100) : 0; ?>
                    <tr>
                        <td>
                            <div class="mech-name-wrap">
                                <div class="mech-avatar"><?php echo strtoupper(substr($m['username'],0,1)); ?></div>
                                <?php echo htmlspecialchars($m['username']); ?>
                            </div>
                        </td>
                        <td><?php echo intval($m['jobs_today']) ?: '<span style="color:var(--text-muted);">—</span>'; ?></td>
                        <td>
                            <div class="hours-bar-wrap">
                                <div class="hours-bar">
                                    <div class="hours-bar-fill" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                                <span class="hours-num <?php echo $m['hours_today'] == 0 ? 'hours-zero' : ''; ?>">
                                    <?php echo $m['hours_today'] > 0 ? number_format($m['hours_today'],1).'h' : '0h'; ?>
                                </span>
                            </div>
                        </td>
                        <td class="right" style="font-family:'Barlow Condensed',sans-serif;font-weight:700;color:var(--text-muted);">
                            <?php echo $m['hours_week'] > 0 ? number_format($m['hours_week'],1).'h' : '—'; ?>
                        </td>
                        <td class="right" style="font-family:'Barlow Condensed',sans-serif;font-weight:700;color:var(--text-muted);">
                            <?php echo $m['hours_month'] > 0 ? number_format($m['hours_month'],1).'h' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- ── UPCOMING JOBS ────────────────────────────────────────────────── -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Upcoming — Next 7 Days</span>
            <span class="panel-count"><?php echo count($upcoming_jobs); ?></span>
        </div>
        <?php if(empty($upcoming_jobs)): ?>
            <div class="empty-state">No jobs scheduled in the next 7 days.</div>
        <?php else: ?>
            <?php foreach($upcoming_jobs as $j): ?>
                <?php
                    $jdate = strtotime($j['job_date']);
                    $day_label = date('D d M', $jdate);
                    $diff = (strtotime($j['job_date']) - strtotime($today)) / 86400;
                    if($diff == 1) $day_label = 'Tomorrow';
                    $pill = match($j['status']) {
                        'in progress' => 'pill-ip',
                        'complete'    => 'pill-cp',
                        default       => 'pill-ns'
                    };
                    $status_short = match($j['status']) {
                        'in progress' => 'In Progress',
                        'complete'    => 'Complete',
                        default       => 'Not Started'
                    };
                ?>
                <div class="upcoming-row">
                    <div class="day-badge"><?php echo $day_label; ?></div>
                    <div class="upcoming-info">
                        <div class="upcoming-type"><?php echo htmlspecialchars($j['job_type']); ?></div>
                        <div class="upcoming-sub"><?php echo htmlspecialchars($j['customer_name']); ?> · <?php echo htmlspecialchars($j['registration']); ?></div>
                    </div>
                    <span class="status-pill <?php echo $pill; ?>"><?php echo $status_short; ?></span>
                    <a class="job-link" href="view_job.php?id=<?php echo $j['id']; ?>">View →</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── JOBS WAITING FOR PARTS ───────────────────────────────────────── -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Waiting for Parts</span>
            <span class="panel-count" style="color:<?php echo count($waiting_rows) > 0 ? 'var(--orange)' : 'var(--text-muted)'; ?>">
                <?php echo count($waiting_rows); ?>
            </span>
        </div>
        <?php if(empty($waiting_rows)): ?>
            <div class="empty-state">No jobs waiting on out-of-stock parts. ✓</div>
        <?php else: ?>
            <?php foreach($waiting_rows as $w): ?>
                <div class="waiting-row">
                    <div class="waiting-top">
                        <span class="waiting-job"><?php echo htmlspecialchars($w['job_type']); ?> — <?php echo htmlspecialchars($w['customer_name']); ?></span>
                        <a class="job-link" href="view_job.php?id=<?php echo $w['id']; ?>">View →</a>
                    </div>
                    <div class="waiting-meta"><?php echo htmlspecialchars($w['vehicle']); ?> · <?php echo htmlspecialchars($w['registration']); ?> · <?php echo date('d M', strtotime($w['job_date'])); ?></div>
                    <div class="waiting-parts-list">⚠ Out of stock: <?php echo htmlspecialchars($w['missing_parts']); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── PARTS USED TODAY ──────────────────────────────────────────────── -->
    <div class="panel full-width">
        <div class="panel-header">
            <span class="panel-title">Parts Used Today</span>
            <span class="panel-count"><?php echo count($parts_today_rows); ?></span>
        </div>
        <?php if(empty($parts_today_rows)): ?>
            <div class="empty-state">No parts recorded on today's jobs.</div>
        <?php else: ?>
            <?php foreach($parts_today_rows as $p): ?>
                <div class="parts-row">
                    <div class="parts-name"><?php echo htmlspecialchars($p['part_name']); ?></div>
                    <span class="parts-qty"><?php echo $p['total_qty']; ?>×</span>
                    <span class="parts-cost">£<?php echo number_format($p['total_cost'],2); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
