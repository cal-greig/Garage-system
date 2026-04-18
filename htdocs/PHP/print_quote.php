<?php
include "config.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
include "get_rate.php";
$hourly_rate = get_hourly_rate($conn);

$id = intval($_GET["id"]);
$stmt = $conn->prepare("SELECT * FROM quotes WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
if(!$quote) { echo "Quote not found."; exit(); }

$labour_rows = $conn->query("SELECT *, hours*$hourly_rate as cost FROM quote_labour WHERE quote_id=$id");
$parts_rows  = $conn->query("SELECT *, quantity*price as line_total FROM quote_parts WHERE quote_id=$id");
$tasks_rows  = $conn->query("SELECT * FROM quote_tasks WHERE quote_id=$id");

$labour_entries = [];
while($r = $labour_rows->fetch_assoc()) $labour_entries[] = $r;
$parts_entries = [];
while($r = $parts_rows->fetch_assoc()) $parts_entries[] = $r;
$tasks_entries = [];
while($r = $tasks_rows->fetch_assoc()) $tasks_entries[] = $r;

$total_hours   = array_sum(array_column($labour_entries, "hours"));
$labour_total  = $total_hours * $hourly_rate;
$parts_total   = array_sum(array_map(fn($p) => $p["quantity"] * $p["price"], $parts_entries));
$grand_total   = $labour_total + $parts_total;

$quote_number  = "QUO-" . str_pad($id, 4, "0", STR_PAD_LEFT);
$quote_date    = date("d F Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $quote_number; ?> — Quote</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
    body { margin:0; background:#1c1c1c; font-family:'DM Sans',sans-serif; color:#111; }
    .screen-bar { background:#1c1c1c; padding:16px 32px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
    .print-btn { background:#f59e0b; color:#000; border:none; font-family:'DM Sans',sans-serif; font-weight:600; font-size:0.88rem; padding:9px 20px; border-radius:6px; cursor:pointer; transition:background 0.15s; }
    .print-btn:hover { background:#d97706; }
    .back-link { color:#666; text-decoration:none; font-size:0.85rem; }
    .back-link:hover { color:#aaa; }
    .screen-note { color:#555; font-size:0.8rem; margin-left:auto; }
    .quote-badge { background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid rgba(245,158,11,0.3); border-radius:4px; font-size:0.75rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:4px 10px; }

    .invoice-wrap { max-width:820px; margin:0 auto 60px; padding:0 24px; }
    .invoice { background:#fff; border-radius:4px; overflow:hidden; box-shadow:0 8px 48px rgba(0,0,0,0.5); }
    .inv-header { background:#111; color:#fff; padding:40px 48px; display:flex; justify-content:space-between; align-items:flex-start; }
    .garage-name { font-size:1.6rem; font-weight:600; letter-spacing:-0.02em; color:#fff; margin-bottom:8px; }
    .garage-contact { font-size:0.82rem; color:#888; line-height:1.7; font-family:'DM Mono',monospace; }
    .inv-meta { text-align:right; }
    .inv-number { font-family:'DM Mono',monospace; font-size:1.3rem; font-weight:500; color:#f59e0b; letter-spacing:0.04em; margin-bottom:6px; }
    .inv-label { font-size:0.72rem; letter-spacing:0.12em; text-transform:uppercase; color:#555; margin-bottom:2px; }
    .inv-value { font-size:0.88rem; color:#ccc; font-family:'DM Mono',monospace; }
    .inv-meta-row { margin-bottom:10px; }
    .quote-stamp { margin-top:14px; display:inline-block; border:2px solid #f59e0b; color:#f59e0b; font-size:0.8rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; padding:5px 12px; border-radius:3px; }

    .bill-strip { background:#f8f8f8; border-bottom:1px solid #e8e8e8; padding:24px 48px; display:flex; gap:60px; flex-wrap:wrap; }
    .bill-label { font-size:0.68rem; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; color:#999; margin-bottom:6px; }
    .bill-name { font-size:1rem; font-weight:600; color:#111; margin-bottom:3px; }
    .bill-detail { font-size:0.85rem; color:#555; line-height:1.6; }

    .inv-body { padding:36px 48px; }
    .section-title { font-size:0.68rem; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; color:#999; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid #ebebeb; }
    table { width:100%; border-collapse:collapse; margin-bottom:28px; font-size:0.88rem; }
    thead th { font-size:0.68rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:#999; text-align:left; padding:0 0 8px; border-bottom:1px solid #ebebeb; }
    thead th.right { text-align:right; }
    tbody td { padding:10px 0; border-bottom:1px solid #f2f2f2; color:#333; vertical-align:top; }
    tbody td.right { text-align:right; font-family:'DM Mono',monospace; font-size:0.85rem; color:#111; }
    tbody td.muted { color:#888; font-size:0.82rem; }
    tbody tr:last-child td { border-bottom:none; }

    .totals-block { margin-left:auto; width:280px; margin-bottom:36px; }
    .total-row { display:flex; justify-content:space-between; padding:7px 0; font-size:0.88rem; border-bottom:1px solid #f2f2f2; color:#555; }
    .total-row:last-child { border-bottom:none; }
    .total-row.grand { font-size:1.05rem; font-weight:600; color:#111; margin-top:4px; padding-top:10px; border-top:2px solid #111; border-bottom:none; }
    .total-row span:last-child { font-family:'DM Mono',monospace; font-weight:500; }
    .total-row.grand span:last-child { color:#f59e0b; font-size:1.2rem; }

    .tasks-list { list-style:none; padding:0; margin:0 0 28px; }
    .tasks-list li { padding:7px 0; border-bottom:1px solid #f2f2f2; font-size:0.88rem; color:#444; display:flex; align-items:center; gap:10px; }
    .tasks-list li:last-child { border-bottom:none; }
    .tasks-list li::before { content:''; width:6px; height:6px; background:#f59e0b; border-radius:50%; flex-shrink:0; }

    .validity-note { background:#fffbea; border:1px solid #fde68a; border-radius:6px; padding:12px 16px; font-size:0.82rem; color:#92400e; margin-bottom:28px; }

    .inv-footer { background:#f8f8f8; border-top:1px solid #e8e8e8; padding:20px 48px; display:flex; justify-content:space-between; align-items:center; }
    .footer-note { font-size:0.8rem; color:#999; }
    .footer-total-label { font-size:0.72rem; letter-spacing:0.1em; text-transform:uppercase; color:#999; margin-bottom:2px; text-align:right; }
    .footer-total-value { font-family:'DM Mono',monospace; font-size:1.4rem; font-weight:500; color:#111; }
    .empty-section { font-size:0.85rem; color:#bbb; padding:8px 0 20px; font-style:italic; }

    @media print {
        body { background:#fff; }
        .screen-bar { display:none; }
        .invoice-wrap { margin:0; padding:0; max-width:100%; }
        .invoice { box-shadow:none; border-radius:0; }
    }
</style>
</head>
<body>

<div class="screen-bar">
    <a class="back-link" href="view_quote.php?id=<?php echo $id; ?>">← Back to Quote</a>
    <button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    <span class="screen-note">Use your browser's print dialog and choose "Save as PDF"</span>
</div>

<div class="invoice-wrap">
<div class="invoice">

    <div class="inv-header">
        <div>
            <div class="garage-name">Your Garage Name</div>
            <div class="garage-contact">📞 07700 000000</div>
        </div>
        <div class="inv-meta">
            <div class="inv-number"><?php echo $quote_number; ?></div>
            <div class="inv-meta-row">
                <div class="inv-label">Quote Date</div>
                <div class="inv-value"><?php echo $quote_date; ?></div>
            </div>
            <div class="inv-meta-row">
                <div class="inv-label">Valid For</div>
                <div class="inv-value">30 days</div>
            </div>
            <div class="inv-meta-row">
                <div class="inv-label">Status</div>
                <div class="inv-value"><?php echo ucfirst($quote["status"]); ?></div>
            </div>
            <div class="quote-stamp">Estimate</div>
        </div>
    </div>

    <div class="bill-strip">
        <div>
            <div class="bill-label">Prepared For</div>
            <div class="bill-name"><?php echo htmlspecialchars($quote["customer_name"]); ?></div>
            <?php if($quote["customer_phone"]): ?>
                <div class="bill-detail"><?php echo htmlspecialchars($quote["customer_phone"]); ?></div>
            <?php endif; ?>
        </div>
        <div>
            <div class="bill-label">Vehicle</div>
            <div class="bill-name"><?php echo htmlspecialchars($quote["vehicle"]); ?></div>
            <div class="bill-detail"><?php echo htmlspecialchars($quote["registration"]); ?></div>
        </div>
        <div>
            <div class="bill-label">Job Type</div>
            <div class="bill-name"><?php echo htmlspecialchars($quote["job_type"]); ?></div>
        </div>
    </div>

    <div class="inv-body">

        <div class="validity-note">
            ⚠ This is an estimate only. Final costs may vary depending on the condition of the vehicle once work begins. This quote is valid for 30 days from the date above.
        </div>

        <?php if(!empty($tasks_entries)): ?>
        <div class="section-title">Work Included</div>
        <ul class="tasks-list">
            <?php foreach($tasks_entries as $t): ?>
                <li><?php echo htmlspecialchars($t["task"]); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div class="section-title">Labour</div>
        <?php if(empty($labour_entries)): ?>
            <div class="empty-section">No labour entries added.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Description</th><th class="right">Hours</th><th class="right">Rate</th><th class="right">Amount</th></tr></thead>
            <tbody>
            <?php foreach($labour_entries as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row["description"]); ?></td>
                    <td class="right"><?php echo number_format($row["hours"],2); ?></td>
                    <td class="right muted">£<?php echo number_format($hourly_rate,2); ?>/hr</td>
                    <td class="right">£<?php echo number_format($row["cost"],2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="section-title">Parts &amp; Materials</div>
        <?php if(empty($parts_entries)): ?>
            <div class="empty-section">No parts added.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Part</th><th class="right">Qty</th><th class="right">Unit Price</th><th class="right">Amount</th></tr></thead>
            <tbody>
            <?php foreach($parts_entries as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row["part_name"]); ?></td>
                    <td class="right"><?php echo $row["quantity"]; ?></td>
                    <td class="right muted">£<?php echo number_format($row["price"],2); ?></td>
                    <td class="right">£<?php echo number_format($row["line_total"],2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="totals-block">
            <div class="total-row"><span>Labour (<?php echo number_format($total_hours,2); ?> hrs)</span><span>£<?php echo number_format($labour_total,2); ?></span></div>
            <div class="total-row"><span>Parts &amp; Materials</span><span>£<?php echo number_format($parts_total,2); ?></span></div>
            <div class="total-row grand"><span>Estimated Total</span><span>£<?php echo number_format($grand_total,2); ?></span></div>
        </div>

        <?php if($quote["description"]): ?>
        <div class="section-title">Notes</div>
        <p style="font-size:0.88rem;color:#555;line-height:1.7;margin:0 0 28px;"><?php echo nl2br(htmlspecialchars($quote["description"])); ?></p>
        <?php endif; ?>

    </div>

    <div class="inv-footer">
        <div class="footer-note">
            Thank you for considering Your Garage Name.<br>
            Please contact us to go ahead or if you have any questions.
        </div>
        <div>
            <div class="footer-total-label">Estimated Total</div>
            <div class="footer-total-value">£<?php echo number_format($grand_total,2); ?></div>
        </div>
    </div>

</div>
</div>
</body>
</html>
