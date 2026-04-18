<?php
include "config.php";
include "navbar.php";
include "get_rate.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$id = intval($_GET["id"]);
$hourly_rate = get_hourly_rate($conn);

$stmt = $conn->prepare("SELECT * FROM quotes WHERE id=? AND (deleted=0 OR deleted IS NULL)");
$stmt->bind_param("i", $id);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
if(!$quote) {
    echo "<div style='padding:40px;color:#aaa;font-family:sans-serif;'>Quote not found or has been deleted. <a href='quotes.php' style='color:#f59e0b;'>← Back to Quotes</a></div>";
    exit();
}

// Handle delete
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["delete_quote"])) {
    $stmt_d = $conn->prepare("UPDATE quotes SET deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?");
    $stmt_d->bind_param("si", $_SESSION["user"], $id);
    $stmt_d->execute();
    header("Location: quotes.php"); exit;
}

// Handle status change
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["status"])) {
    $ns = $_POST["status"];
    if(in_array($ns, ["pending","accepted","declined"])) {
        $stmt2 = $conn->prepare("UPDATE quotes SET status=? WHERE id=?");
        $stmt2->bind_param("si", $ns, $id);
        $stmt2->execute();
    }
    header("Location: view_quote.php?id=$id");
    exit;
}

// Handle convert to job
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["convert_job"])) {
    $date = $_POST["job_date"];
    $stmt3 = $conn->prepare("INSERT INTO jobs (job_date,customer_name,vehicle,registration,job_type,description,status) VALUES (?,?,?,?,?,?,'not started')");
    $stmt3->bind_param("ssssss", $date, $quote["customer_name"], $quote["vehicle"], $quote["registration"], $quote["job_type"], $quote["description"]);
    $stmt3->execute();
    $new_job_id = $conn->insert_id;

    // Copy labour entries
    $labour = $conn->query("SELECT * FROM quote_labour WHERE quote_id=$id");
    while($l = $labour->fetch_assoc()) {
        $sl = $conn->prepare("INSERT INTO job_time (job_id,hours,description) VALUES (?,?,?)");
        $sl->bind_param("ids", $new_job_id, $l["hours"], $l["description"]);
        $sl->execute();
    }
    // Copy parts
    $parts = $conn->query("SELECT * FROM quote_parts WHERE quote_id=$id");
    while($p = $parts->fetch_assoc()) {
        $sp = $conn->prepare("INSERT INTO job_parts (job_id,part_name,quantity,price) VALUES (?,?,?,?)");
        $sp->bind_param("isid", $new_job_id, $p["part_name"], $p["quantity"], $p["price"]);
        $sp->execute();
    }
    // Copy tasks
    $tasks = $conn->query("SELECT * FROM quote_tasks WHERE quote_id=$id");
    while($t = $tasks->fetch_assoc()) {
        $st = $conn->prepare("INSERT INTO job_tasks (job_id,task) VALUES (?,?)");
        $st->bind_param("is", $new_job_id, $t["task"]);
        $st->execute();
    }
    // Mark quote as accepted
    $conn->query("UPDATE quotes SET status='accepted', converted_job_id=$new_job_id WHERE id=$id");
    header("Location: view_job.php?id=$new_job_id&from_quote=$id");
    exit;
}

// Fetch line items
$labour_rows = $conn->query("SELECT * FROM quote_labour WHERE quote_id=$id");
$labour_entries = [];
while($r = $labour_rows->fetch_assoc()) $labour_entries[] = $r;

$parts_rows = $conn->query("SELECT * FROM quote_parts WHERE quote_id=$id");
$parts_entries = [];
while($r = $parts_rows->fetch_assoc()) $parts_entries[] = $r;

$tasks_rows = $conn->query("SELECT * FROM quote_tasks WHERE quote_id=$id");
$tasks_entries = [];
while($r = $tasks_rows->fetch_assoc()) $tasks_entries[] = $r;

// Recalculate total
$total_hours = array_sum(array_column($labour_entries, "hours"));
$labour_total = $total_hours * $hourly_rate;
$parts_total = array_sum(array_map(fn($p) => $p["quantity"] * $p["price"], $parts_entries));
$grand_total = $labour_total + $parts_total;

// Update stored total
$conn->query("UPDATE quotes SET total_amount=$grand_total WHERE id=$id");

$status_colours = ["pending"=>"#f59e0b","accepted"=>"#10b981","declined"=>"#ef4444"];
$sc = $status_colours[$quote["status"]] ?? "#7c8a9e";
$quote_num = "QUO-" . str_pad($id, 4, "0", STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $quote_num; ?> — Garage System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{--steel:#1a1f2e;--steel-mid:#242938;--steel-light:#2e3447;--accent:#f59e0b;--green:#10b981;--red:#ef4444;--orange:#f97316;--text:#e2e8f0;--text-muted:#7c8a9e;--border:rgba(255,255,255,0.07);}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}
    .page-header{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:20px 28px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
    .page-header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;}
    .quote-num{font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:700;color:var(--accent);}
    .status-badge{font-size:0.75rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:4px 10px;border-radius:4px;background:rgba(255,255,255,0.05);border:1px solid;color:<?php echo $sc;?>;border-color:<?php echo $sc;?>44;}
    .content{max-width:960px;margin:0 auto;padding:28px;}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
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
    .action-btn.primary:hover{background:#d97706;color:#000;}
    .action-btn.success{background:rgba(16,185,129,0.12);color:var(--green);border-color:rgba(16,185,129,0.3);}
    .action-btn.success:hover{background:var(--green);color:#fff;}
    .action-btn.danger{background:rgba(239,68,68,0.1);color:var(--red);border-color:rgba(239,68,68,0.3);}
    .action-btn.danger:hover{background:var(--red);color:#fff;}
    .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);font-size:0.88rem;text-decoration:none;transition:color 0.15s;margin-top:8px;}
    .back-link:hover{color:var(--accent);}
    .empty-note{font-size:0.85rem;color:var(--text-muted);font-style:italic;padding:8px 0;}
    /* Convert modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;}
    .modal-overlay.active{display:flex;}
    .modal-box{background:#242938;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:28px;max-width:420px;width:90%;}
    .modal-title{font-size:1.05rem;font-weight:700;color:#fff;margin-bottom:6px;}
    .modal-desc{font-size:0.85rem;color:var(--text-muted);margin-bottom:20px;line-height:1.6;}
    .modal-actions{display:flex;gap:10px;margin-top:20px;}
    .btn-cancel{flex:1;padding:10px;background:rgba(255,255,255,0.07);color:#e2e8f0;border:1px solid rgba(255,255,255,0.1);border-radius:7px;font-weight:600;font-size:0.88rem;cursor:pointer;}
    .field-label{display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:7px;margin-top:16px;}
    .field-input{width:100%;background:var(--steel-light);border:1px solid var(--border);border-radius:7px;color:var(--text);font-family:'Barlow',sans-serif;font-size:0.95rem;padding:11px 14px;}
    .field-input:focus{outline:none;border-color:var(--accent);}
    @media(max-width:700px){.grid-2{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="page-header">
    <h1><?php echo htmlspecialchars($quote["job_type"]); ?></h1>
    <span class="quote-num"><?php echo $quote_num; ?></span>
    <span class="status-badge"><?php echo ucfirst($quote["status"]); ?></span>
</div>
<div class="content">

    <!-- Actions -->
    <div class="actions">
        <a class="action-btn" href="add_quote_labour.php?id=<?php echo $id; ?>">+ Add Labour</a>
        <a class="action-btn" href="add_quote_part.php?id=<?php echo $id; ?>">+ Add Part</a>
        <a class="action-btn" href="add_quote_task.php?id=<?php echo $id; ?>">+ Add Task</a>
        <a class="action-btn primary" href="print_quote.php?id=<?php echo $id; ?>">🖨 Print Quote</a>
        <?php if($quote["status"] !== "accepted"): ?>
            <button class="action-btn success" onclick="document.getElementById('convertModal').classList.add('active')">✓ Convert to Job</button>
        <?php else: ?>
            <a class="action-btn success" href="view_job.php?id=<?php echo $quote["converted_job_id"]; ?>">→ View Job</a>
        <?php endif; ?>
        <?php if($quote["status"]==="pending"): ?>
            <form method="POST" style="margin:0;">
        <?php csrf_field(); ?>
                <input type="hidden" name="status" value="declined">
                <button class="action-btn danger" type="submit">✗ Mark Declined</button>
            </form>
        <?php endif; ?>
        <?php if($quote["status"]==="declined"): ?>
            <form method="POST" style="margin:0;">
        <?php csrf_field(); ?>
                <input type="hidden" name="status" value="pending">
                <button class="action-btn" type="submit">↩ Reopen</button>
            </form>
        <?php endif; ?>
        <button class="action-btn danger" onclick="document.getElementById('deleteModal').classList.add('active')" style="margin-left:auto;">🗑 Delete Quote</button>
    </div>

    <div class="grid-2">
        <!-- Customer details -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Customer</span></div>
            <div class="panel-body">
                <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><?php echo htmlspecialchars($quote["customer_name"]); ?></span></div>
                <?php if($quote["customer_phone"]): ?>
                <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?php echo htmlspecialchars($quote["customer_phone"]); ?></span></div>
                <?php endif; ?>
                <?php if($quote["customer_email"]): ?>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($quote["customer_email"]); ?></span></div>
                <?php endif; ?>
                <?php if($quote["contact_source"]): ?>
                <div class="detail-row"><span class="detail-label">Contact via</span><span class="detail-value"><?php echo htmlspecialchars($quote["contact_source"]); ?></span></div>
                <?php endif; ?>
                <div class="detail-row"><span class="detail-label">Vehicle</span><span class="detail-value"><?php echo htmlspecialchars($quote["vehicle"]); ?></span></div>
                <div class="detail-row"><span class="detail-label">Registration</span><span class="detail-value"><?php echo htmlspecialchars($quote["registration"]); ?></span></div>
                <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value"><?php echo date("d M Y", strtotime($quote["created_at"])); ?></span></div>
            </div>
        </div>
        <!-- Cost summary -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Cost Summary</span></div>
            <div class="panel-body">
                <div class="total-row"><span>Labour (<?php echo number_format($total_hours,2); ?> hrs @ £<?php echo number_format($hourly_rate,2); ?>)</span><span>£<?php echo number_format($labour_total,2); ?></span></div>
                <div class="total-row"><span>Parts & Materials</span><span>£<?php echo number_format($parts_total,2); ?></span></div>
                <div class="total-row grand"><span>Estimated Total</span><span>£<?php echo number_format($grand_total,2); ?></span></div>
            </div>
        </div>
    </div>

    <!-- Tasks -->
    <?php if(!empty($tasks_entries)): ?>
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Work Included</span></div>
        <div class="panel-body">
            <?php foreach($tasks_entries as $t): ?>
                <div class="task-item"><span class="task-dot"></span><?php echo htmlspecialchars($t["task"]); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Labour -->
    <?php if(!empty($labour_entries)): ?>
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Labour</span></div>
        <div class="panel-body">
            <table>
                <thead><tr><th>Description</th><th class="right">Hours</th><th class="right">Cost</th></tr></thead>
                <tbody>
                <?php foreach($labour_entries as $l): ?>
                    <tr><td><?php echo htmlspecialchars($l["description"]); ?></td><td class="right"><?php echo number_format($l["hours"],2); ?></td><td class="right">£<?php echo number_format($l["hours"]*$hourly_rate,2); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Parts -->
    <?php if(!empty($parts_entries)): ?>
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Parts & Materials</span></div>
        <div class="panel-body">
            <table>
                <thead><tr><th>Part</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Total</th></tr></thead>
                <tbody>
                <?php foreach($parts_entries as $p): ?>
                    <tr><td><?php echo htmlspecialchars($p["part_name"]); ?></td><td class="right"><?php echo $p["quantity"]; ?></td><td class="right">£<?php echo number_format($p["price"],2); ?></td><td class="right">£<?php echo number_format($p["quantity"]*$p["price"],2); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if($quote["description"]): ?>
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Notes</span></div>
        <div class="panel-body" style="font-size:0.9rem;color:var(--text-muted);line-height:1.7;"><?php echo nl2br(htmlspecialchars($quote["description"])); ?></div>
    </div>
    <?php endif; ?>

    <a class="back-link" href="quotes.php">← Back to Quotes</a>
</div>

<!-- Convert to Job modal -->
<div class="modal-overlay" id="convertModal">
    <div class="modal-box">
        <div class="modal-title">📅 Convert to Job</div>
        <div class="modal-desc">This will create a new job on the calendar with all the labour, parts, and tasks from this quote copied across. What date should the job be booked for?</div>
        <form method="POST">
        <?php csrf_field(); ?>
            <input type="hidden" name="convert_job" value="1">
            <label class="field-label">Job Date</label>
            <input class="field-input" type="date" name="job_date" value="<?php echo date('Y-m-d'); ?>" required>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('convertModal').classList.remove('active')">Cancel</button>
                <button class="action-btn success" type="submit" style="flex:1;text-align:center;">Create Job →</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-title">🗑 Delete Quote</div>
        <div class="modal-desc">Are you sure you want to delete <strong><?php echo htmlspecialchars($quote_num); ?></strong> for <strong><?php echo htmlspecialchars($quote["customer_name"]); ?></strong>? It can be restored from Deleted Quotes if needed.</div>
        <form method="POST">
        <?php csrf_field(); ?>
            <input type="hidden" name="delete_quote" value="1">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('deleteModal').classList.remove('active')">Cancel</button>
                <button class="action-btn danger" type="submit" style="flex:1;text-align:center;">Delete Quote</button>
            </div>
        </form>
    </div>
</div>
</body></html>
