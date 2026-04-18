<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$id = intval($_GET["id"]);
$error = "";

if($_SERVER["REQUEST_METHOD"]==="POST") {
    $stmt = $conn->prepare("UPDATE inventory SET part_name=?, part_number=?, cost_price=?, low_stock_threshold=?, notes=? WHERE id=?");
    $stmt->bind_param("ssidsi",
        $_POST["part_name"],
        $_POST["part_number"],
        floatval($_POST["cost_price"]),
        intval($_POST["low_stock_threshold"]),
        $_POST["notes"],
        $id
    );
    if($stmt->execute()) {
        header("Location: inventory.php"); exit;
    } else {
        $error = "Failed to update: " . $stmt->error;
    }
}

$stmt2 = $conn->prepare("SELECT * FROM inventory WHERE id=?");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$part = $stmt2->get_result()->fetch_assoc();
if(!$part) { echo "Part not found."; exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Part — Garage System</title>
<?php include "form_style.php"; ?>
</head>
<body>
<div class="page-header"><h1>Edit Part</h1></div>
<div class="form-wrap">
<div class="form-card">
    <?php if($error): ?>
        <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#ef4444;border-radius:8px;padding:12px 16px;font-size:0.88rem;margin-bottom:20px;">⚠ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <?php csrf_field(); ?>
        <div class="field-group">
            <label class="field-label">Part Name</label>
            <input class="field-input" name="part_name" value="<?php echo htmlspecialchars($part['part_name']); ?>" required>
        </div>
        <div class="field-group">
            <label class="field-label">Part Number <span style="font-weight:400;text-transform:none;font-size:0.7rem;opacity:0.6;">(optional)</span></label>
            <input class="field-input" name="part_number" value="<?php echo htmlspecialchars($part['part_number'] ?? ''); ?>">
        </div>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Cost Price (£)</label>
                <input class="field-input" type="number" step="0.01" min="0" name="cost_price" value="<?php echo htmlspecialchars($part['cost_price']); ?>" required>
            </div>
            <div class="field-group">
                <label class="field-label">Low Stock Warning Threshold</label>
                <input class="field-input" type="number" min="0" name="low_stock_threshold" value="<?php echo htmlspecialchars($part['low_stock_threshold']); ?>" required>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Notes <span style="font-weight:400;text-transform:none;font-size:0.7rem;opacity:0.6;">(optional)</span></label>
            <textarea class="field-textarea" name="notes"><?php echo htmlspecialchars($part['notes'] ?? ''); ?></textarea>
        </div>
        <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:7px;padding:12px 14px;font-size:0.82rem;color:var(--text-muted,#7c8a9e);margin-bottom:20px;">
            ℹ Current stock is <strong style="color:#fff;"><?php echo intval($part['quantity']); ?> units</strong>. To change stock quantity use the <strong style="color:#fff;">± Stock</strong> button on the inventory page.
        </div>
        <button class="submit-btn" type="submit">Save Changes</button>
    </form>
</div>
<a class="back-link" href="inventory.php">← Back to Inventory</a>
</div>
</body>
</html>
