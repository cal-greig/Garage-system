<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$error = "";
if($_SERVER["REQUEST_METHOD"]==="POST") {
    $stmt = $conn->prepare("INSERT INTO inventory (part_name, part_number, quantity, cost_price, low_stock_threshold, notes) VALUES (?,?,?,?,?,?)");
    if(!$stmt) {
        $error = "Database error: " . $conn->error . " — make sure you have run inventory_setup.sql in phpMyAdmin.";
    } else {
        $stmt->bind_param("ssidis",
            $_POST["part_name"],
            $_POST["part_number"],
            intval($_POST["quantity"]),
            floatval($_POST["cost_price"]),
            intval($_POST["low_stock_threshold"]),
            $_POST["notes"]
        );
        if($stmt->execute()) {
            header("Location: inventory.php"); exit;
        } else {
            $error = "Failed to save: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Part — Garage System</title>
<?php include "form_style.php"; ?>
</head>
<body>
<div class="page-header"><h1>Add Part to Inventory</h1></div>
<div class="form-wrap">
<div class="form-card">
    <?php if($error): ?>
        <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#ef4444;border-radius:8px;padding:12px 16px;font-size:0.88rem;margin-bottom:20px;">⚠ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <?php csrf_field(); ?>
        <div class="field-group">
            <label class="field-label">Part Name</label>
            <input class="field-input" name="part_name" placeholder="e.g. Brake Pads (Front) — Mintex" value="<?php echo htmlspecialchars($_POST['part_name'] ?? ''); ?>" required>
        </div>
        <div class="field-group">
            <label class="field-label">Part Number <span style="font-weight:400;text-transform:none;font-size:0.7rem;opacity:0.6;">(optional)</span></label>
            <input class="field-input" name="part_number" placeholder="e.g. MDB1234" value="<?php echo htmlspecialchars($_POST['part_number'] ?? ''); ?>">
        </div>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Current Stock (qty)</label>
                <input class="field-input" type="number" min="0" name="quantity" placeholder="0" value="<?php echo htmlspecialchars($_POST['quantity'] ?? '0'); ?>" required>
            </div>
            <div class="field-group">
                <label class="field-label">Cost Price (£)</label>
                <input class="field-input" type="number" step="0.01" min="0" name="cost_price" placeholder="0.00" value="<?php echo htmlspecialchars($_POST['cost_price'] ?? ''); ?>" required>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Low Stock Warning — alert when qty drops to or below</label>
            <input class="field-input" type="number" min="0" name="low_stock_threshold" placeholder="e.g. 2" value="<?php echo htmlspecialchars($_POST['low_stock_threshold'] ?? '2'); ?>" required>
        </div>
        <div class="field-group">
            <label class="field-label">Notes <span style="font-weight:400;text-transform:none;font-size:0.7rem;opacity:0.6;">(optional)</span></label>
            <textarea class="field-textarea" name="notes" placeholder="e.g. Fits Ford Focus 2012-2018, order from Euro Car Parts"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
        </div>
        <button class="submit-btn" type="submit">Add to Inventory</button>
    </form>
</div>
<a class="back-link" href="inventory.php">← Back to Inventory</a>
</div>
</body>
</html>
