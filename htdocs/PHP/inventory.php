<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

// Handle delete
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["delete_id"])) {
    $did = intval($_POST["delete_id"]);
    $conn->query("DELETE FROM inventory WHERE id=$did");
    header("Location: inventory.php"); exit;
}

$filter = $_GET["filter"] ?? "all"; // all, low, out
$search = trim($_GET["q"] ?? "");

$where = "WHERE 1=1";
if($filter === "low") $where .= " AND quantity > 0 AND quantity <= low_stock_threshold";
if($filter === "out") $where .= " AND quantity = 0";
if($search !== "") {
    $s = $conn->real_escape_string($search);
    $where .= " AND (part_name LIKE '%$s%' OR part_number LIKE '%$s%' OR category LIKE '%$s%')";
}

$parts = $conn->query("SELECT * FROM inventory $where ORDER BY part_name ASC");
$rows = [];
if($parts) while($r = $parts->fetch_assoc()) $rows[] = $r;

// Counts for filter tabs
$total_count = $conn->query("SELECT COUNT(*) as c FROM inventory")->fetch_assoc()["c"] ?? 0;
$low_count   = $conn->query("SELECT COUNT(*) as c FROM inventory WHERE quantity > 0 AND quantity <= low_stock_threshold")->fetch_assoc()["c"] ?? 0;
$out_count   = $conn->query("SELECT COUNT(*) as c FROM inventory WHERE quantity = 0")->fetch_assoc()["c"] ?? 0;

// Stock value
$stock_value = $conn->query("SELECT COALESCE(SUM(quantity * cost_price),0) as v FROM inventory")->fetch_assoc()["v"] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory — Garage System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{--steel:#1a1f2e;--steel-mid:#242938;--steel-light:#2e3447;--accent:#f59e0b;--green:#10b981;--red:#ef4444;--orange:#f97316;--text:#e2e8f0;--text-muted:#7c8a9e;--border:rgba(255,255,255,0.07);}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Barlow',sans-serif;background:var(--steel);color:var(--text);min-height:100vh;}

    .page-header{background:var(--steel-mid);border-bottom:1px solid var(--border);padding:20px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
    .page-header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#fff;}
    .header-actions{display:flex;gap:10px;align-items:center;}
    .new-btn{padding:9px 20px;background:var(--accent);color:#000;border:none;border-radius:7px;font-family:'Barlow',sans-serif;font-weight:700;font-size:0.88rem;text-decoration:none;transition:background 0.15s;}
    .new-btn:hover{background:#d97706;}

    .content{max-width:1100px;margin:0 auto;padding:28px;}

    /* Stats row */
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
    .stat-card{background:var(--steel-mid);border:1px solid var(--border);border-radius:9px;padding:16px 20px;}
    .stat-label{font-size:0.72rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;}
    .stat-value{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:700;color:#fff;}
    .stat-value.amber{color:var(--accent);}
    .stat-value.red{color:var(--red);}
    .stat-value.green{color:var(--green);}

    /* Toolbar */
    .toolbar{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;}
    .search-input{background:var(--steel-light);border:1px solid var(--border);border-radius:7px;color:var(--text);font-family:'Barlow',sans-serif;font-size:0.9rem;padding:9px 14px;width:260px;transition:border-color 0.2s;}
    .search-input:focus{outline:none;border-color:var(--accent);}
    .search-input::placeholder{color:var(--text-muted);}
    .filter-btn{padding:6px 14px;border-radius:20px;font-size:0.8rem;font-weight:600;border:1px solid var(--border);color:var(--text-muted);background:transparent;cursor:pointer;transition:all 0.15s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
    .filter-btn:hover{color:var(--text);}
    .filter-btn.active{background:var(--accent);color:#000;border-color:var(--accent);}
    .filter-btn.red-active{background:var(--red);color:#fff;border-color:var(--red);}
    .filter-btn.orange-active{background:var(--orange);color:#fff;border-color:var(--orange);}
    .count-pill{font-size:0.7rem;background:rgba(255,255,255,0.1);padding:1px 6px;border-radius:8px;}

    /* Table */
    .panel{background:var(--steel-mid);border:1px solid var(--border);border-radius:10px;overflow:hidden;}
    table{width:100%;border-collapse:collapse;}
    thead th{font-size:0.7rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);padding:12px 16px;text-align:left;border-bottom:1px solid var(--border);background:rgba(255,255,255,0.02);}
    thead th.right{text-align:right;}
    tbody td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:0.9rem;color:var(--text);vertical-align:middle;}
    tbody td.right{text-align:right;}
    tbody tr:last-child td{border-bottom:none;}
    tbody tr:hover{background:var(--steel-light);}

    .part-name{font-weight:700;color:#fff;}
    .part-num{font-size:0.75rem;color:var(--text-muted);margin-top:2px;}

    /* Stock badge */
    .stock-badge{display:inline-flex;align-items:center;gap:6px;font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:700;}
    .stock-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
    .dot-ok{background:var(--green);}
    .dot-low{background:var(--orange);}
    .dot-out{background:var(--red);}
    .stock-ok{color:#fff;}
    .stock-low{color:var(--orange);}
    .stock-out{color:var(--red);}

    .price-cell{font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:600;color:var(--accent);}

    /* Row actions */
    .row-actions{display:flex;gap:6px;justify-content:flex-end;}
    .btn-sm{padding:5px 11px;border-radius:5px;font-size:0.78rem;font-weight:600;text-decoration:none;border:1px solid var(--border);color:var(--text-muted);background:transparent;cursor:pointer;transition:all 0.15s;white-space:nowrap;}
    .btn-sm:hover{color:var(--text);border-color:rgba(255,255,255,0.2);}
    .btn-sm.edit:hover{color:var(--accent);border-color:var(--accent);}
    .btn-sm.adjust:hover{color:var(--green);border-color:var(--green);}
    .btn-sm.del:hover{color:var(--red);border-color:var(--red);}

    .empty-state{padding:56px 20px;text-align:center;color:var(--text-muted);}
    .empty-icon{font-size:2.8rem;margin-bottom:10px;}
    .empty-title{font-size:1rem;font-weight:600;color:var(--text);margin-bottom:6px;}

    /* Adjust modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:1000;align-items:center;justify-content:center;}
    .modal-overlay.active{display:flex;}
    .modal-box{background:var(--steel-mid);border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:28px;max-width:380px;width:90%;}
    .modal-title{font-size:1.05rem;font-weight:700;color:#fff;margin-bottom:4px;}
    .modal-sub{font-size:0.83rem;color:var(--text-muted);margin-bottom:20px;}
    .adj-btns{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;}
    .adj-btn{padding:11px;border-radius:7px;font-family:'Barlow',sans-serif;font-weight:700;font-size:0.9rem;border:1px solid var(--border);cursor:pointer;transition:all 0.15s;text-align:center;}
    .adj-btn.add{background:rgba(16,185,129,0.12);color:var(--green);border-color:rgba(16,185,129,0.3);}
    .adj-btn.add.selected{background:var(--green);color:#fff;}
    .adj-btn.remove{background:rgba(239,68,68,0.1);color:var(--red);border-color:rgba(239,68,68,0.3);}
    .adj-btn.remove.selected{background:var(--red);color:#fff;}
    .field-label{display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:7px;}
    .field-input{width:100%;background:var(--steel-light);border:1px solid var(--border);border-radius:7px;color:var(--text);font-family:'Barlow',sans-serif;font-size:1rem;padding:11px 14px;}
    .field-input:focus{outline:none;border-color:var(--accent);}
    .modal-actions{display:flex;gap:10px;margin-top:18px;}
    .btn-cancel{flex:1;padding:10px;background:rgba(255,255,255,0.07);color:var(--text);border:1px solid rgba(255,255,255,0.1);border-radius:7px;font-weight:600;font-size:0.88rem;cursor:pointer;}
    .btn-confirm{flex:1;padding:10px;background:var(--accent);color:#000;border:none;border-radius:7px;font-weight:700;font-size:0.88rem;cursor:pointer;}
    .btn-confirm:hover{background:#d97706;}

    @media(max-width:768px){
        .stats-row{grid-template-columns:1fr 1fr;}
        table thead th:nth-child(3),table tbody td:nth-child(3){display:none;}
    }
</style>
</head>
<body>

<div class="page-header">
    <h1>Inventory</h1>
    <div class="header-actions">
        <a class="new-btn" href="add_inventory.php">+ Add Part</a>
    </div>
</div>

<div class="content">

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total Parts</div>
            <div class="stat-value"><?php echo $total_count; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Low Stock</div>
            <div class="stat-value amber"><?php echo $low_count; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Out of Stock</div>
            <div class="stat-value red"><?php echo $out_count; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Stock Value</div>
            <div class="stat-value green">£<?php echo number_format($stock_value, 2); ?></div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <?php csrf_field(); ?>
            <input class="search-input" type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search parts...">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
        </form>
        <a class="filter-btn <?php echo $filter==='all'?'active':''; ?>" href="inventory.php?q=<?php echo urlencode($search); ?>&filter=all">
            All <span class="count-pill"><?php echo $total_count; ?></span>
        </a>
        <a class="filter-btn <?php echo $filter==='low'?'orange-active':''; ?>" href="inventory.php?q=<?php echo urlencode($search); ?>&filter=low">
            ⚠ Low <span class="count-pill"><?php echo $low_count; ?></span>
        </a>
        <a class="filter-btn <?php echo $filter==='out'?'red-active':''; ?>" href="inventory.php?q=<?php echo urlencode($search); ?>&filter=out">
            ✗ Out <span class="count-pill"><?php echo $out_count; ?></span>
        </a>
    </div>

    <!-- Table -->
    <div class="panel">
        <?php if(empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <div class="empty-title"><?php echo $search ? 'No parts match "'.htmlspecialchars($search).'"' : 'No parts in inventory yet'; ?></div>
                <?php if(!$search): ?><div style="font-size:0.85rem;margin-top:4px;">Click <strong>+ Add Part</strong> to get started</div><?php endif; ?>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Part</th>
                    <th class="right">In Stock</th>
                    <th class="right">Cost Price</th>
                    <th class="right">Stock Value</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $p):
                $qty = intval($p["quantity"]);
                $threshold = intval($p["low_stock_threshold"]);
                if($qty === 0) { $dot = "dot-out"; $cls = "stock-out"; }
                elseif($qty <= $threshold) { $dot = "dot-low"; $cls = "stock-low"; }
                else { $dot = "dot-ok"; $cls = "stock-ok"; }
                $line_val = $qty * floatval($p["cost_price"]);
            ?>
                <tr>
                    <td>
                        <div class="part-name"><?php echo htmlspecialchars($p["part_name"]); ?></div>
                        <?php if($p["part_number"]): ?>
                            <div class="part-num"><?php echo htmlspecialchars($p["part_number"]); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="right">
                        <span class="stock-badge <?php echo $cls; ?>">
                            <span class="stock-dot <?php echo $dot; ?>"></span>
                            <?php echo $qty; ?>
                            <?php if($qty > 0 && $qty <= $threshold): ?>
                                <span style="font-size:0.72rem;font-weight:600;background:rgba(249,115,22,0.15);color:var(--orange);border:1px solid rgba(249,115,22,0.3);border-radius:4px;padding:1px 6px;margin-left:4px;">LOW</span>
                            <?php elseif($qty === 0): ?>
                                <span style="font-size:0.72rem;font-weight:600;background:rgba(239,68,68,0.12);color:var(--red);border:1px solid rgba(239,68,68,0.3);border-radius:4px;padding:1px 6px;margin-left:4px;">OUT</span>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td class="right price-cell">£<?php echo number_format($p["cost_price"], 2); ?></td>
                    <td class="right price-cell">£<?php echo number_format($line_val, 2); ?></td>
                    <td class="right">
                        <div class="row-actions">
                            <button class="btn-sm adjust"
                                onclick="openAdjust(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['part_name'])); ?>', <?php echo $qty; ?>)">
                                ± Stock
                            </button>
                            <a class="btn-sm edit" href="edit_inventory.php?id=<?php echo $p['id']; ?>">Edit</a>
                            <form method="POST" style="margin:0;" class="delete-part-form" data-partname="<?php echo htmlspecialchars($p['part_name'], ENT_QUOTES); ?>">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="delete_id" value="<?php echo $p['id']; ?>">
                                <button class="btn-sm del" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Stock Adjust Modal -->
<div class="modal-overlay" id="adjustModal">
    <div class="modal-box">
        <div class="modal-title">± Adjust Stock</div>
        <div class="modal-sub" id="adjustSub">Adjusting stock for part</div>
        <form method="POST" action="adjust_inventory.php">
        <?php csrf_field(); ?>
            <input type="hidden" name="part_id" id="adjustPartId">
            <input type="hidden" name="direction" id="adjustDirection" value="add">

            <div class="adj-btns">
                <button type="button" class="adj-btn add selected" id="btnAdd" onclick="setDir('add')">+ Add Stock</button>
                <button type="button" class="adj-btn remove" id="btnRemove" onclick="setDir('remove')">− Remove Stock</button>
            </div>

            <label class="field-label">Quantity</label>
            <input class="field-input" type="number" name="qty" min="1" value="1" required id="adjustQty">

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAdjust()">Cancel</button>
                <button type="submit" class="btn-confirm">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdjust(id, name, currentQty) {
    document.getElementById('adjustPartId').value = id;
    document.getElementById('adjustSub').textContent = name + ' — currently ' + currentQty + ' in stock';
    document.getElementById('adjustQty').value = 1;
    setDir('add');
    document.getElementById('adjustModal').classList.add('active');
}
function closeAdjust() {
    document.getElementById('adjustModal').classList.remove('active');
}
function setDir(dir) {
    document.getElementById('adjustDirection').value = dir;
    document.getElementById('btnAdd').classList.toggle('selected', dir === 'add');
    document.getElementById('btnRemove').classList.toggle('selected', dir === 'remove');
}
// Auto-search on type
document.querySelector('.search-input').addEventListener('input', function() {
    clearTimeout(this._t);
    this._t = setTimeout(() => this.closest('form').submit(), 400);
});
// Delete part confirmation
document.querySelectorAll('.delete-part-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if(!confirm('Delete "' + this.dataset.partname + '"? This cannot be undone.')) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>
