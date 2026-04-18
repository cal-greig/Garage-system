<?php
include "config.php";
include "navbar.php";
include "get_rate.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$id = intval($_GET["id"]);
$hourly_rate = get_hourly_rate($conn);

$stmt = $conn->prepare("SELECT * FROM jobs WHERE id=? AND (deleted=0 OR deleted IS NULL)");
$stmt->bind_param("i", $id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if(!$job) {
    echo "<div style='padding:40px;color:#aaa;font-family:sans-serif;'>Job not found or has been deleted. <a href='dashboard.php' style='color:#f59e0b;'>← Back to Dashboard</a></div>";
    exit();
}

$hours = $conn->query("SELECT SUM(hours) as total FROM job_time WHERE job_id=$id")->fetch_assoc()["total"] ?? 0;

// Look up assigned mechanic name
$assigned_name = null;
if(!empty($job["assigned_to"])) {
    $amech = $conn->prepare("SELECT username FROM users WHERE id=?");
    $amech->bind_param("i", $job["assigned_to"]);
    $amech->execute();
    $ar = $amech->get_result()->fetch_assoc();
    $assigned_name = $ar["username"] ?? null;
}
$labour = $hours * $hourly_rate;
$parts_total = $conn->query("SELECT SUM(quantity*price) as total FROM job_parts WHERE job_id=$id")->fetch_assoc()["total"] ?? 0;
$total = $labour + $parts_total;

$parts_rows = $conn->query("SELECT jp.part_name, jp.quantity, jp.price, jp.quantity*jp.price as line_total, jp.inventory_id, i.part_name as inv_name FROM job_parts jp LEFT JOIN inventory i ON jp.inventory_id = i.id WHERE jp.job_id=$id");
$time_rows  = $conn->query("SELECT description, hours FROM job_time WHERE job_id=$id");
$task_rows  = $conn->query("SELECT task FROM job_tasks WHERE job_id=$id");

$stock_deducted = (bool)($job["stock_deducted"] ?? false);
$linked_parts_count = $conn->query("SELECT COUNT(*) as n FROM job_parts WHERE job_id=$id AND inventory_id IS NOT NULL AND inventory_id > 0")->fetch_assoc()["n"] ?? 0;
$flash_msg = $_GET["msg"] ?? "";

$status_colour = ["not started" => "#ef4444", "in progress" => "#f97316", "complete" => "#10b981"];
$sc = $status_colour[$job["status"]] ?? "#7c8a9e";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($job["job_type"]); ?> — Garage System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --steel:#1a1f2e; --steel-mid:#242938; --steel-light:#2e3447;
        --accent:#f59e0b; --green:#10b981; --red:#ef4444; --orange:#f97316;
        --text:#e2e8f0; --text-muted:#7c8a9e; --border:rgba(255,255,255,0.07);
    }
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}

    .page-header{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:20px 28px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
    .page-header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;}
    .status-badge{font-size:0.75rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:4px 10px;border-radius:4px;background:rgba(255,255,255,0.05);border:1px solid;color:<?php echo $sc;?>;border-color:<?php echo $sc;?>44;}

    .content{max-width:960px;margin:0 auto;padding:28px;}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
    .panel{background:var(--steel-mid);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:20px;}
    .panel-header{padding:13px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .panel-title{font-family:'Barlow Condensed',sans-serif;font-size:0.9rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);}
    .panel-body{padding:16px 20px;}

    .detail-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:0.9rem;}
    .detail-row:last-child{border-bottom:none;}
    .detail-label{color:var(--text-muted);}
    .detail-value{font-weight:600;color:#fff;}

    table{width:100%;border-collapse:collapse;font-size:0.88rem;}
    thead th{font-size:0.68rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);text-align:left;padding:0 0 8px;border-bottom:1px solid var(--border);}
    thead th.right{text-align:right;}
    tbody td{padding:9px 0;border-bottom:1px solid rgba(255,255,255,0.04);color:var(--text);}
    tbody td.right{text-align:right;color:var(--accent);font-weight:600;}
    tbody tr:last-child td{border-bottom:none;}

    .total-row{display:flex;justify-content:space-between;padding:8px 0;font-size:0.9rem;color:var(--text-muted);border-bottom:1px solid var(--border);}
    .total-row.grand{font-size:1.1rem;font-weight:700;color:#fff;border-bottom:none;margin-top:4px;}
    .total-row span:last-child{font-weight:600;color:var(--accent);}

    .task-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.88rem;}
    .task-item:last-child{border-bottom:none;}
    .task-dot{width:7px;height:7px;background:var(--accent);border-radius:50%;flex-shrink:0;}

    .actions{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;}
    .action-btn{padding:9px 18px;border-radius:7px;font-family:'Barlow',sans-serif;font-size:0.85rem;font-weight:600;text-decoration:none;border:1px solid var(--border);color:var(--text);background:var(--steel-mid);transition:all 0.15s;cursor:pointer;}
    .action-btn:hover{border-color:var(--accent);color:var(--accent);}
    .action-btn.primary{background:var(--accent);color:#000;border-color:var(--accent);}
    .action-btn.primary:hover{background:#d97706;}
    .action-btn.danger{background:rgba(239,68,68,0.1);color:var(--red);border-color:rgba(239,68,68,0.3);}
    .action-btn.danger:hover{background:var(--red);color:#fff;}

    .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);font-size:0.88rem;text-decoration:none;transition:color 0.15s;margin-top:8px;}
    .back-link:hover{color:var(--accent);}

    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;}
    .modal-overlay.active{display:flex;}
    .modal-box{background:#242938;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:32px 28px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.5);}
    .modal-icon{font-size:2.2rem;margin-bottom:12px;}
    .modal-title{font-size:1.1rem;font-weight:700;color:#fff;margin-bottom:8px;}
    .modal-desc{font-size:0.88rem;color:#7c8a9e;margin-bottom:24px;line-height:1.6;}
    .modal-actions{display:flex;gap:10px;}
    .btn-cancel{flex:1;padding:11px;background:rgba(255,255,255,0.07);color:#e2e8f0;border:1px solid rgba(255,255,255,0.1);border-radius:7px;font-weight:600;font-size:0.88rem;cursor:pointer;transition:background 0.15s;}
    .btn-cancel:hover{background:rgba(255,255,255,0.12);}
    .btn-confirm-delete{flex:1;padding:11px;background:#ef4444;color:#fff;border:none;border-radius:7px;font-weight:700;font-size:0.88rem;cursor:pointer;transition:background 0.15s;}
    .btn-confirm-delete:hover{background:#dc2626;}

    @media(max-width:700px){.grid-2{grid-template-columns:1fr;}}
</style>
</head>
<body>

<div class="page-header">
    <h1><?php echo htmlspecialchars($job["job_type"]); ?></h1>
    <span class="status-badge"><?php echo ucfirst($job["status"]); ?></span>
</div>

<div class="content">

    <!-- Action buttons -->
    <div class="actions">
        <a class="action-btn" href="add_time.php?id=<?php echo $id;?>">+ Add Labour</a>
        <a class="action-btn" href="add_part.php?id=<?php echo $id;?>">+ Add Part</a>
        <a class="action-btn" href="add_task.php?id=<?php echo $id;?>">+ Add Task</a>
        <a class="action-btn" href="edit_job.php?id=<?php echo $id;?>">✏ Edit Job</a>
        <a class="action-btn primary" href="invoice.php?id=<?php echo $id;?>">🧾 Invoice</a>
        <?php if($linked_parts_count > 0): ?>
            <?php if($stock_deducted): ?>
                <span class="action-btn" style="opacity:0.5;cursor:default;">✓ Stock Deducted</span>
            <?php else: ?>
                <button class="action-btn" style="background:rgba(16,185,129,0.12);color:#10b981;border-color:rgba(16,185,129,0.3);"
                        onclick="document.getElementById('deductModal').classList.add('active')">
                    📦 Deduct Stock
                </button>
            <?php endif; ?>
        <?php endif; ?>
        <button class="action-btn danger" onclick="document.getElementById('deleteModal').classList.add('active')">🗑 Delete</button>
    </div>

    <?php if($flash_msg === 'stock_deducted'): ?>
        <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#10b981;font-size:0.88rem;">
            ✓ Stock successfully deducted from inventory.
        </div>
    <?php elseif($flash_msg === 'already_deducted'): ?>
        <div style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#f59e0b;font-size:0.88rem;">
            Stock has already been deducted for this job.
        </div>
    <?php elseif($flash_msg === 'deduct_error'): ?>
        <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#ef4444;font-size:0.88rem;">
            Something went wrong. Please try again.
        </div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Job Details -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Job Details</span></div>
            <div class="panel-body">
                <div class="detail-row"><span class="detail-label">Customer</span><span class="detail-value"><a href="customer.php?name=<?php echo urlencode($job['customer_name']); ?>" style="color:var(--accent);text-decoration:none;"><?php echo htmlspecialchars($job["customer_name"]); ?></a></span></div>
                <div class="detail-row"><span class="detail-label">Vehicle</span><span class="detail-value"><?php echo htmlspecialchars($job["vehicle"]); ?></span></div>
                <div class="detail-row"><span class="detail-label">Registration</span><span class="detail-value"><?php echo htmlspecialchars($job["registration"]); ?></span></div>
                <div class="detail-row"><span class="detail-label">Date</span><span class="detail-value"><?php echo date("d M Y", strtotime($job["job_date"])); ?></span></div>
                <?php if($job["description"]): ?>
                <div class="detail-row"><span class="detail-label">Notes</span><span class="detail-value" style="text-align:right;max-width:60%;"><?php echo htmlspecialchars($job["description"]); ?></span></div>
                <?php endif; ?>
                <?php if($assigned_name): ?>
                <div class="detail-row">
                    <span class="detail-label">Assigned To</span>
                    <span class="detail-value" style="display:flex;align-items:center;gap:8px;">
                        <span style="width:22px;height:22px;border-radius:50%;background:rgba(245,158,11,0.2);color:#f59e0b;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:0.8rem;display:inline-flex;align-items:center;justify-content:center;"><?php echo strtoupper(substr($assigned_name,0,1)); ?></span>
                        <?php echo htmlspecialchars($assigned_name); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Financials -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Cost Summary</span></div>
            <div class="panel-body">
                <div class="total-row"><span>Labour (<?php echo number_format($hours,2); ?> hrs @ £<?php echo number_format($hourly_rate,2); ?>)</span><span>£<?php echo number_format($labour,2); ?></span></div>
                <div class="total-row"><span>Parts & Materials</span><span>£<?php echo number_format($parts_total,2); ?></span></div>
                <div class="total-row grand"><span>Total</span><span>£<?php echo number_format($total,2); ?></span></div>
            </div>
        </div>
    </div>

    <!-- Tasks -->
    <?php $tasks = []; while($r = $task_rows->fetch_assoc()) $tasks[] = $r; ?>
    <?php if(!empty($tasks)): ?>
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Tasks</span></div>
        <div class="panel-body">
            <?php foreach($tasks as $t): ?>
                <div class="task-item"><span class="task-dot"></span><?php echo htmlspecialchars($t["task"]); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Labour entries -->
    <?php $time_entries = []; while($r = $time_rows->fetch_assoc()) $time_entries[] = $r; ?>
    <?php if(!empty($time_entries)): ?>
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Labour Entries</span></div>
        <div class="panel-body">
            <table>
                <thead><tr><th>Description</th><th class="right">Hours</th><th class="right">Cost</th></tr></thead>
                <tbody>
                <?php foreach($time_entries as $t): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($t["description"]); ?></td>
                        <td class="right"><?php echo number_format($t["hours"],2); ?></td>
                        <td class="right">£<?php echo number_format($t["hours"]*$hourly_rate,2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Parts entries -->
    <?php $parts_entries = []; while($r = $parts_rows->fetch_assoc()) $parts_entries[] = $r; ?>
    <?php if(!empty($parts_entries)): ?>
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Parts & Materials</span>
            <?php if($stock_deducted): ?>
                <span style="font-size:0.75rem;color:#10b981;font-weight:600;">✓ Stock deducted</span>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <table>
                <thead><tr><th>Part</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Total</th></tr></thead>
                <tbody>
                <?php foreach($parts_entries as $p): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($p["part_name"]); ?>
                            <?php if($p["inventory_id"]): ?>
                                <span style="font-size:0.7rem;background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);padding:1px 6px;border-radius:3px;margin-left:6px;">INVENTORY</span>
                            <?php endif; ?>
                        </td>
                        <td class="right"><?php echo $p["quantity"]; ?></td>
                        <td class="right">£<?php echo number_format($p["price"],2); ?></td>
                        <td class="right">£<?php echo number_format($p["line_total"],2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <a class="back-link" href="dashboard.php">← Back to Dashboard</a>
</div>

<!-- Delete confirmation modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">⚠️</div>
        <div class="modal-title">Delete this job?</div>
        <div class="modal-desc">
            This will remove <strong style="color:#fff;"><?php echo htmlspecialchars($job["job_type"]); ?></strong>
            for <strong style="color:#fff;"><?php echo htmlspecialchars($job["customer_name"]); ?></strong>
            from the calendar. You can recover it any time from <strong style="color:#fff;">Deleted Jobs</strong>.
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="document.getElementById('deleteModal').classList.remove('active')">Cancel</button>
            <form method="POST" action="delete_job.php" style="flex:1;margin:0;">
        <?php csrf_field(); ?>
                <input type="hidden" name="job_id" value="<?php echo $id; ?>">
                <button class="btn-confirm-delete" type="submit" style="width:100%;">Yes, Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Deduct stock confirmation modal -->
<div class="modal-overlay" id="deductModal">
    <div class="modal-box">
        <div class="modal-icon">📦</div>
        <div class="modal-title">Deduct stock from inventory?</div>
        <div class="modal-desc">
            This will reduce inventory quantities for all parts on this job that are linked to inventory.
            This action can only be done <strong style="color:#fff;">once per job</strong> and cannot be undone.
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="document.getElementById('deductModal').classList.remove('active')">Cancel</button>
            <form method="POST" action="deduct_stock.php" style="flex:1;margin:0;">
                <?php csrf_field(); ?>
                <input type="hidden" name="job_id" value="<?php echo $id; ?>">
                <button type="submit" style="width:100%;padding:11px;background:#10b981;color:#fff;border:none;border-radius:7px;font-weight:700;font-size:0.88rem;cursor:pointer;">
                    Yes, Deduct Stock
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
