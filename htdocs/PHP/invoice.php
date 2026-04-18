<?php
include "config.php";
if(!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}
include "get_rate.php";
$hourly_rate = get_hourly_rate($conn);
$ff_discount = get_ff_discount($conn);

$id = intval($_GET["id"]);
$apply_discount = isset($_GET["discount"]) && $_GET["discount"] == "1";

// Job details
$stmt = $conn->prepare("SELECT * FROM jobs WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if(!$job) { echo "Job not found."; exit(); }

// Labour entries
$labour_rows = $conn->query("SELECT description, hours, hours*$hourly_rate as cost FROM job_time WHERE job_id=$id");

// Parts entries
$parts_rows = $conn->query("SELECT part_name, quantity, price, quantity*price as line_total FROM job_parts WHERE job_id=$id");

// Tasks
$tasks_rows = $conn->query("SELECT task FROM job_tasks WHERE job_id=$id");

// Totals
$total_hours = $conn->query("SELECT COALESCE(SUM(hours),0) as t FROM job_time WHERE job_id=$id")->fetch_assoc()["t"];
$labour_total = $total_hours * $hourly_rate;
$parts_total = $conn->query("SELECT COALESCE(SUM(quantity*price),0) as t FROM job_parts WHERE job_id=$id")->fetch_assoc()["t"];
$grand_total = $labour_total + $parts_total;
$discount_amount = $apply_discount ? round($grand_total * ($ff_discount / 100), 2) : 0;
$final_total = $grand_total - $discount_amount;

$invoice_number = "INV-" . str_pad($id, 4, "0", STR_PAD_LEFT);
$invoice_date = date("d F Y");
$job_date_fmt = date("d F Y", strtotime($job["job_date"]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $invoice_number; ?> — Invoice</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
    /* ── Screen chrome ── */
    body {
        margin: 0;
        background: #1c1c1c;
        font-family: 'DM Sans', sans-serif;
        color: #111;
    }

    .screen-bar {
        background: #1c1c1c;
        padding: 16px 32px;
        display: flex;
        align-items: center;
        gap: 12px;
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .print-btn {
        background: #f59e0b;
        color: #000;
        border: none;
        font-family: 'DM Sans', sans-serif;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 9px 20px;
        border-radius: 6px;
        cursor: pointer;
        letter-spacing: 0.02em;
        transition: background 0.15s;
    }

    .print-btn:hover { background: #d97706; }

    .back-link {
        color: #666;
        text-decoration: none;
        font-size: 0.85rem;
        transition: color 0.15s;
    }

    .back-link:hover { color: #aaa; }

    .screen-note {
        color: #555;
        font-size: 0.8rem;
        margin-left: auto;
    }

    /* ── Invoice paper ── */
    .invoice-wrap {
        max-width: 820px;
        margin: 0 auto 60px;
        padding: 0 24px;
    }

    .invoice {
        background: #fff;
        border-radius: 4px;
        overflow: hidden;
        box-shadow: 0 8px 48px rgba(0,0,0,0.5);
    }

    /* Header band */
    .inv-header {
        background: #111;
        color: #fff;
        padding: 40px 48px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .garage-name {
        font-size: 1.6rem;
        font-weight: 600;
        letter-spacing: -0.02em;
        color: #fff;
        margin-bottom: 8px;
    }

    .garage-contact {
        font-size: 0.82rem;
        color: #888;
        line-height: 1.7;
        font-family: 'DM Mono', monospace;
    }

    .inv-meta {
        text-align: right;
    }

    .inv-number {
        font-family: 'DM Mono', monospace;
        font-size: 1.3rem;
        font-weight: 500;
        color: #f59e0b;
        letter-spacing: 0.04em;
        margin-bottom: 6px;
    }

    .inv-label {
        font-size: 0.72rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #555;
        margin-bottom: 2px;
    }

    .inv-value {
        font-size: 0.88rem;
        color: #ccc;
        font-family: 'DM Mono', monospace;
    }

    .inv-meta-row { margin-bottom: 10px; }

    /* Bill to strip */
    .bill-strip {
        background: #f8f8f8;
        border-bottom: 1px solid #e8e8e8;
        padding: 24px 48px;
        display: flex;
        gap: 60px;
    }

    .bill-block { }

    .bill-label {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #999;
        margin-bottom: 6px;
    }

    .bill-name {
        font-size: 1rem;
        font-weight: 600;
        color: #111;
        margin-bottom: 3px;
    }

    .bill-detail {
        font-size: 0.85rem;
        color: #555;
        line-height: 1.6;
    }

    /* Body */
    .inv-body { padding: 36px 48px; }

    /* Section title */
    .section-title {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #999;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid #ebebeb;
    }

    /* Line item table */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 28px;
        font-size: 0.88rem;
    }

    thead th {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #999;
        text-align: left;
        padding: 0 0 8px;
        border-bottom: 1px solid #ebebeb;
    }

    thead th.right { text-align: right; }

    tbody td {
        padding: 10px 0;
        border-bottom: 1px solid #f2f2f2;
        color: #333;
        vertical-align: top;
    }

    tbody td.right {
        text-align: right;
        font-family: 'DM Mono', monospace;
        font-size: 0.85rem;
        color: #111;
    }

    tbody td.muted { color: #888; font-size: 0.82rem; }

    tbody tr:last-child td { border-bottom: none; }

    /* Totals block */
    .totals-block {
        margin-left: auto;
        width: 280px;
        margin-bottom: 36px;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 7px 0;
        font-size: 0.88rem;
        border-bottom: 1px solid #f2f2f2;
        color: #555;
    }

    .total-row:last-child { border-bottom: none; }

    .total-row.grand {
        font-size: 1.05rem;
        font-weight: 600;
        color: #111;
        margin-top: 4px;
        padding-top: 10px;
        border-top: 2px solid #111;
        border-bottom: none;
    }

    .total-row span:last-child {
        font-family: 'DM Mono', monospace;
        font-weight: 500;
    }

    .total-row.grand span:last-child {
        color: #f59e0b;
        font-size: 1.2rem;
    }

    /* Tasks section */
    .tasks-list {
        list-style: none;
        padding: 0;
        margin: 0 0 28px;
    }

    .tasks-list li {
        padding: 7px 0;
        border-bottom: 1px solid #f2f2f2;
        font-size: 0.88rem;
        color: #444;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .tasks-list li:last-child { border-bottom: none; }

    .tasks-list li::before {
        content: '';
        width: 6px;
        height: 6px;
        background: #f59e0b;
        border-radius: 50%;
        flex-shrink: 0;
    }

    /* Footer */
    .inv-footer {
        background: #f8f8f8;
        border-top: 1px solid #e8e8e8;
        padding: 20px 48px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .footer-note {
        font-size: 0.8rem;
        color: #999;
    }

    .footer-total-label {
        font-size: 0.72rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #999;
        margin-bottom: 2px;
        text-align: right;
    }

    .footer-total-value {
        font-family: 'DM Mono', monospace;
        font-size: 1.4rem;
        font-weight: 500;
        color: #111;
    }

    .empty-section {
        font-size: 0.85rem;
        color: #bbb;
        padding: 8px 0 20px;
        font-style: italic;
    }

    /* ── Print styles ── */
    @media print {
        body { background: #fff; }
        .screen-bar { display: none; }
        .invoice-wrap { margin: 0; padding: 0; max-width: 100%; }
        .invoice { box-shadow: none; border-radius: 0; }
    }
</style>
</head>
<body>

<!-- Screen-only toolbar -->
<div class="screen-bar">
    <a class="back-link" href="view_job.php?id=<?php echo $id; ?>">← Back to Job</a>
    <button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    <?php if($apply_discount): ?>
        <a href="invoice.php?id=<?php echo $id; ?>" style="padding:9px 16px;background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3);border-radius:6px;font-size:0.85rem;font-weight:600;text-decoration:none;">✓ F&F Discount Applied — Remove</a>
    <?php else: ?>
        <a href="invoice.php?id=<?php echo $id; ?>&discount=1" style="padding:9px 16px;background:rgba(245,158,11,0.12);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);border-radius:6px;font-size:0.85rem;font-weight:600;text-decoration:none;">🏷 Apply F&F Discount (<?php echo number_format($ff_discount,1); ?>% off)</a>
    <?php endif; ?>
    <span class="screen-note">Use your browser's print dialog and choose "Save as PDF"</span>
</div>

<div class="invoice-wrap">
<div class="invoice">

    <!-- Header -->
    <div class="inv-header">
        <div>
            <div class="garage-name">Your Garage Name</div>
            <div class="garage-contact">
                📞 07700 000000 <br>
            </div>
        </div>
        <div class="inv-meta">
            <div class="inv-number"><?php echo $invoice_number; ?></div>
            <div class="inv-meta-row">
                <div class="inv-label">Invoice Date</div>
                <div class="inv-value"><?php echo $invoice_date; ?></div>
            </div>
            <div class="inv-meta-row">
                <div class="inv-label">Job Date</div>
                <div class="inv-value"><?php echo $job_date_fmt; ?></div>
            </div>
            <div class="inv-meta-row">
                <div class="inv-label">Status</div>
                <div class="inv-value"><?php echo ucfirst($job["payment_status"] ?? "Unpaid"); ?></div>
            </div>
        </div>
    </div>

    <!-- Bill To -->
    <div class="bill-strip">
        <div class="bill-block">
            <div class="bill-label">Bill To</div>
            <div class="bill-name"><?php echo htmlspecialchars($job["customer_name"]); ?></div>
        </div>
        <div class="bill-block">
            <div class="bill-label">Vehicle</div>
            <div class="bill-name"><?php echo htmlspecialchars($job["vehicle"]); ?></div>
            <div class="bill-detail"><?php echo htmlspecialchars($job["registration"]); ?></div>
        </div>
        <div class="bill-block">
            <div class="bill-label">Job Type</div>
            <div class="bill-name"><?php echo htmlspecialchars($job["job_type"]); ?></div>
        </div>
    </div>

    <!-- Body -->
    <div class="inv-body">

        <!-- Tasks completed -->
        <?php $tasks = []; while($t = $tasks_rows->fetch_assoc()) $tasks[] = $t; ?>
        <?php if(!empty($tasks)): ?>
        <div class="section-title">Work Completed</div>
        <ul class="tasks-list">
            <?php foreach($tasks as $t): ?>
                <li><?php echo htmlspecialchars($t["task"]); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- Labour -->
        <div class="section-title">Labour</div>
        <?php $labour_entries = []; while($r = $labour_rows->fetch_assoc()) $labour_entries[] = $r; ?>
        <?php if(empty($labour_entries)): ?>
            <div class="empty-section">No labour entries recorded.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Hours</th>
                    <th class="right">Rate</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($labour_entries as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row["description"]); ?></td>
                    <td class="right"><?php echo number_format($row["hours"], 2); ?></td>
                    <td class="right muted">£<?php echo number_format($hourly_rate, 2); ?>/hr</td>
                    <td class="right">£<?php echo number_format($row["cost"], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Parts -->
        <div class="section-title">Parts & Materials</div>
        <?php $parts_entries = []; while($r = $parts_rows->fetch_assoc()) $parts_entries[] = $r; ?>
        <?php if(empty($parts_entries)): ?>
            <div class="empty-section">No parts recorded.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Part</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($parts_entries as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row["part_name"]); ?></td>
                    <td class="right"><?php echo $row["quantity"]; ?></td>
                    <td class="right muted">£<?php echo number_format($row["price"], 2); ?></td>
                    <td class="right">£<?php echo number_format($row["line_total"], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Totals -->
        <div class="totals-block">
            <div class="total-row">
                <span>Labour (<?php echo number_format($total_hours, 2); ?> hrs)</span>
                <span>£<?php echo number_format($labour_total, 2); ?></span>
            </div>
            <div class="total-row">
                <span>Parts & Materials</span>
                <span>£<?php echo number_format($parts_total, 2); ?></span>
            </div>
            <?php if($apply_discount): ?>
            <div class="total-row" style="color:#10b981;">
                <span>Friends &amp; Family Discount (<?php echo number_format($ff_discount, 1); ?>%)</span>
                <span>-£<?php echo number_format($discount_amount, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row grand">
                <span>Total Due</span>
                <span>£<?php echo number_format($final_total, 2); ?></span>
            </div>
        </div>

        <!-- Description / notes -->
        <?php if(!empty($job["description"])): ?>
        <div class="section-title">Job Notes</div>
        <p style="font-size:0.88rem;color:#555;line-height:1.7;margin:0 0 28px;">
            <?php echo nl2br(htmlspecialchars($job["description"])); ?>
        </p>
        <?php endif; ?>

    </div>

    <!-- Footer -->
    <div class="inv-footer">
        <div class="footer-note">
            Thank you for your custom.<br>
            Please make payment at your earliest convenience.
        </div>
        <div>
            <div class="footer-total-label">Amount Due</div>
            <div class="footer-total-value">£<?php echo number_format($final_total, 2); ?></div>
        </div>
    </div>

</div>
</div>

</body>
</html>
