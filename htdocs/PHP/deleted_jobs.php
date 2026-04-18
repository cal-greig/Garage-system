<?php
include "config.php";
include "navbar.php";
include "get_rate.php";

if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$hourly_rate = get_hourly_rate($conn);

$deleted = $conn->query("
    SELECT j.*,
        COALESCE((SELECT SUM(hours) FROM job_time WHERE job_id = j.id), 0) as total_hours,
        COALESCE((SELECT SUM(quantity*price) FROM job_parts WHERE job_id = j.id), 0) as parts_cost
    FROM jobs j
    WHERE j.deleted = 1
    ORDER BY j.deleted_at DESC
");

$rows = [];
while($r = $deleted->fetch_assoc()) $rows[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deleted Jobs — Garage System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --steel: #1a1f2e; --steel-mid: #242938; --steel-light: #2e3447;
        --accent: #f59e0b; --green: #10b981; --red: #ef4444;
        --text: #e2e8f0; --text-muted: #7c8a9e; --border: rgba(255,255,255,0.07);
    }
    body { font-family: 'Barlow', sans-serif; background: var(--steel); color: var(--text); min-height: 100vh; }

    .page-header {
        background: var(--steel-mid); border-bottom: 1px solid var(--border);
        padding: 20px 28px; display: flex; align-items: center; gap: 14px;
    }
    .page-header h1 {
        font-family: 'Barlow Condensed', sans-serif; font-size: 1.8rem;
        font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #fff; margin: 0;
    }
    .count-badge {
        background: rgba(239,68,68,0.15); color: var(--red);
        border: 1px solid rgba(239,68,68,0.3);
        font-size: 0.78rem; font-weight: 600; letter-spacing: 0.08em;
        text-transform: uppercase; padding: 4px 10px; border-radius: 4px;
    }

    .content { max-width: 1100px; margin: 0 auto; padding: 28px; }

    .panel { background: var(--steel-mid); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }

    .panel-header {
        padding: 14px 20px; border-bottom: 1px solid var(--border);
    }
    .panel-title {
        font-family: 'Barlow Condensed', sans-serif; font-size: 0.9rem;
        font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted);
    }

    .job-row {
        display: flex; align-items: center; padding: 16px 20px;
        border-bottom: 1px solid var(--border); gap: 14px;
        transition: background 0.15s;
    }
    .job-row:last-child { border-bottom: none; }
    .job-row:hover { background: var(--steel-light); }

    .job-main { flex: 1; min-width: 0; }
    .job-type { font-weight: 700; font-size: 0.95rem; color: #fff; }
    .job-customer { font-size: 0.82rem; color: var(--text-muted); margin-top: 3px; }
    .job-meta { font-size: 0.78rem; color: var(--text-muted); margin-top: 4px; }

    .deleted-info { text-align: right; flex-shrink: 0; }
    .deleted-when { font-size: 0.78rem; color: var(--text-muted); }
    .deleted-by {
        font-size: 0.72rem; font-weight: 600; letter-spacing: 0.08em;
        text-transform: uppercase; color: var(--red);
        background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);
        padding: 2px 7px; border-radius: 3px; margin-bottom: 4px; display: inline-block;
    }

    .job-value {
        font-family: 'Barlow Condensed', sans-serif; font-size: 1.05rem;
        font-weight: 700; color: var(--accent); white-space: nowrap; flex-shrink: 0;
    }

    .restore-btn {
        background: rgba(16,185,129,0.12); color: var(--green);
        border: 1px solid rgba(16,185,129,0.3);
        font-family: 'Barlow', sans-serif; font-size: 0.78rem; font-weight: 600;
        padding: 6px 14px; border-radius: 6px; cursor: pointer;
        transition: all 0.15s; white-space: nowrap; flex-shrink: 0;
    }
    .restore-btn:hover { background: var(--green); color: #fff; border-color: var(--green); }

    .empty-state { padding: 48px 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem; }
    .empty-icon { font-size: 2.5rem; margin-bottom: 10px; }
</style>
</head>
<body>

<div class="page-header">
    <h1>Deleted Jobs</h1>
    <span class="count-badge"><?php echo count($rows); ?> deleted</span>
</div>

<div class="content">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">All deleted jobs — click Restore to recover a job back to the calendar</span>
        </div>

        <?php if(empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-icon">🗑</div>
                No deleted jobs. Any jobs you delete will appear here.
            </div>
        <?php else: ?>
            <?php foreach($rows as $job): ?>
                <?php
                    $labour = $job["total_hours"] * $hourly_rate;
                    $total_val = $labour + $job["parts_cost"];
                    $deleted_at = date("d M Y, H:i", strtotime($job["deleted_at"]));
                    $job_date = date("d M Y", strtotime($job["job_date"]));
                ?>
                <div class="job-row">
                    <div class="job-main">
                        <div class="job-type"><?php echo htmlspecialchars($job["job_type"]); ?> — <?php echo htmlspecialchars($job["customer_name"]); ?></div>
                        <div class="job-customer"><?php echo htmlspecialchars($job["vehicle"]); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($job["registration"]); ?></div>
                        <div class="job-meta">Job date: <?php echo $job_date; ?> &nbsp;·&nbsp; Status was: <?php echo ucfirst($job["status"]); ?></div>
                    </div>
                    <span class="job-value">£<?php echo number_format($total_val, 2); ?></span>
                    <div class="deleted-info">
                        <div class="deleted-by">Deleted by <?php echo htmlspecialchars($job["deleted_by"]); ?></div>
                        <div class="deleted-when"><?php echo $deleted_at; ?></div>
                    </div>
                    <form method="POST" action="restore_job.php" style="margin:0;">
        <?php csrf_field(); ?>
                        <input type="hidden" name="job_id" value="<?php echo $job["id"]; ?>">
                        <button class="restore-btn" type="submit">↩ Restore</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
