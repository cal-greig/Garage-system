<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$id = intval($_GET["id"] ?? 0);

if($_SERVER["REQUEST_METHOD"] === "POST") {
    $part_name   = trim($_POST["part"] ?? "");
    $qty         = intval($_POST["qty"] ?? 1);
    $price       = floatval($_POST["price"] ?? 0);
    $inv_id      = intval($_POST["inventory_id"] ?? 0) ?: null;

    $stmt = $conn->prepare("INSERT INTO job_parts (job_id, part_name, quantity, price, inventory_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isidd", $id, $part_name, $qty, $price, $inv_id);

    // Fallback if inventory_id column doesn't exist yet
    if(!$stmt) {
        $stmt = $conn->prepare("INSERT INTO job_parts (job_id, part_name, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isid", $id, $part_name, $qty, $price);
    }
    $stmt->execute();
    header("Location: view_job.php?id=" . $id);
    exit;
}

// Load inventory items for the dropdown
$inv_items = [];
$inv_result = $conn->query("SELECT id, part_name, quantity, unit FROM inventory WHERE quantity > 0 ORDER BY part_name ASC");
if($inv_result) while($r = $inv_result->fetch_assoc()) $inv_items[] = $r;

// Also get all inventory for reference (including out of stock)
$inv_all = [];
$inv_all_result = $conn->query("SELECT id, part_name, quantity, unit, price FROM inventory ORDER BY part_name ASC");
if($inv_all_result) while($r = $inv_all_result->fetch_assoc()) $inv_all[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Part — Garage System</title>
<?php include "form_style.php"; ?>
<style>
    .source-tabs { display:flex; gap:8px; margin-bottom:20px; }
    .source-tab { padding:8px 16px; border-radius:6px; border:1px solid rgba(255,255,255,0.1); background:transparent; color:#7c8a9e; font-family:'Barlow',sans-serif; font-size:0.85rem; font-weight:600; cursor:pointer; transition:all 0.15s; }
    .source-tab.active { background:rgba(245,158,11,0.15); color:#f59e0b; border-color:rgba(245,158,11,0.4); }
    .source-panel { display:none; }
    .source-panel.active { display:block; }
    .inv-item { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#2e3447; border:1px solid rgba(255,255,255,0.07); border-radius:8px; margin-bottom:8px; cursor:pointer; transition:all 0.15s; }
    .inv-item:hover, .inv-item.selected { border-color:#f59e0b; background:rgba(245,158,11,0.08); }
    .inv-item-name { flex:1; font-weight:600; font-size:0.9rem; color:#e2e8f0; }
    .inv-item-stock { font-size:0.8rem; color:#7c8a9e; }
    .inv-item-stock.low { color:#f97316; }
    .inv-item-stock.out { color:#ef4444; }
    .inv-price { font-family:'Barlow Condensed',sans-serif; font-size:1rem; font-weight:700; color:#f59e0b; }
    .stock-warning { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:6px; padding:10px 14px; font-size:0.85rem; color:#ef4444; margin-bottom:12px; display:none; }
</style>
</head>
<body>
<div class="page-header"><h1>Add Part</h1></div>
<div class="form-wrap">
<div class="form-card">

    <?php if(!empty($inv_all)): ?>
    <div class="source-tabs">
        <button class="source-tab active" onclick="switchTab('inventory', this)">From Inventory</button>
        <button class="source-tab" onclick="switchTab('manual', this)">Manual Entry</button>
    </div>
    <?php endif; ?>

    <!-- FROM INVENTORY -->
    <?php if(!empty($inv_all)): ?>
    <div class="source-panel active" id="panel-inventory">
        <form method="POST" id="inv-form">
            <?php csrf_field(); ?>
            <input type="hidden" name="inventory_id" id="selected_inv_id" value="">
            <input type="hidden" name="part" id="inv_part_name" value="">
            <input type="hidden" name="price" id="inv_part_price" value="">

            <div class="field-group">
                <label class="field-label">Select Part from Inventory</label>
                <div id="inv-list">
                    <?php foreach($inv_all as $item): ?>
                        <?php
                            $stock_class = $item['quantity'] <= 0 ? 'out' : ($item['quantity'] <= 5 ? 'low' : '');
                            $stock_label = $item['quantity'] <= 0 ? 'Out of stock' : $item['quantity'] . ' in stock';
                        ?>
                        <div class="inv-item <?php echo $item['quantity'] <= 0 ? 'opacity-50' : ''; ?>"
                             data-id="<?php echo $item['id']; ?>"
                             data-name="<?php echo htmlspecialchars($item['part_name'], ENT_QUOTES); ?>"
                             data-price="<?php echo $item['price']; ?>"
                             data-stock="<?php echo $item['quantity']; ?>"
                             onclick="selectInvItem(this)">
                            <div class="inv-item-name"><?php echo htmlspecialchars($item['part_name']); ?></div>
                            <div class="inv-item-stock <?php echo $stock_class; ?>"><?php echo $stock_label; ?></div>
                            <div class="inv-price">£<?php echo number_format($item['price'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="stock-warning" class="stock-warning">⚠ This item has low or no stock. You can still add it to the job but stock may go below zero on deduction.</div>

            <div id="inv-qty-row" class="field-group" style="display:none;">
                <label class="field-label">Quantity Used</label>
                <input class="field-input" type="number" name="qty" id="inv_qty" value="1" min="1">
            </div>

            <button class="submit-btn" type="submit" id="inv-submit" disabled>Add Selected Part</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- MANUAL ENTRY -->
    <div class="source-panel <?php echo empty($inv_all) ? 'active' : ''; ?>" id="panel-manual">
        <form method="POST">
            <?php csrf_field(); ?>
            <div class="field-group">
                <label class="field-label">Part Name</label>
                <input class="field-input" name="part" placeholder="e.g. Brake Pads, Oil Filter" required>
            </div>
            <div class="form-grid-2">
                <div class="field-group">
                    <label class="field-label">Quantity</label>
                    <input class="field-input" type="number" name="qty" value="1" min="1">
                </div>
                <div class="field-group">
                    <label class="field-label">Unit Price (£)</label>
                    <input class="field-input" type="number" step="0.01" name="price" placeholder="0.00">
                </div>
            </div>
            <button class="submit-btn" type="submit">Add Part</button>
        </form>
    </div>

</div>
<a class="back-link" href="view_job.php?id=<?php echo $id; ?>">← Back to Job</a>
</div>

<script>
function switchTab(tab, btn) {
    document.querySelectorAll('.source-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.source-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('panel-' + tab).classList.add('active');
}

function selectInvItem(el) {
    document.querySelectorAll('.inv-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');

    const id    = el.dataset.id;
    const name  = el.dataset.name;
    const price = el.dataset.price;
    const stock = parseInt(el.dataset.stock);

    document.getElementById('selected_inv_id').value = id;
    document.getElementById('inv_part_name').value   = name;
    document.getElementById('inv_part_price').value  = price;
    document.getElementById('inv-qty-row').style.display = 'block';
    document.getElementById('inv-submit').disabled = false;

    const warn = document.getElementById('stock-warning');
    warn.style.display = stock <= 2 ? 'block' : 'none';
}
</script>
</body>
</html>
