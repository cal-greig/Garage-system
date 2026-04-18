<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();
$id = intval($_GET["id"]);
if($_SERVER["REQUEST_METHOD"]==="POST"){
    $stmt = $conn->prepare("INSERT INTO quote_parts (quote_id,part_name,quantity,price) VALUES (?,?,?,?)");
    $stmt->bind_param("isid", $id, $_POST["part_name"], $_POST["quantity"], $_POST["price"]);
    $stmt->execute();
    header("Location: view_quote.php?id=$id"); exit;
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Part — Quote</title><?php include "form_style.php"; ?>
</head><body>
<div class="page-header"><h1>Add Part to Quote</h1></div>
<div class="form-wrap"><div class="form-card">
    <form method="POST">
        <?php csrf_field(); ?>
        <div class="field-group">
            <label class="field-label">Part Name</label>
            <input class="field-input" name="part_name" placeholder="e.g. Brake Pads (Front)" required>
        </div>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Quantity</label>
                <input class="field-input" type="number" step="1" min="1" name="quantity" placeholder="1" required>
            </div>
            <div class="field-group">
                <label class="field-label">Unit Price (£)</label>
                <input class="field-input" type="number" step="0.01" min="0" name="price" placeholder="0.00" required>
            </div>
        </div>
        <button class="submit-btn" type="submit">Add Part</button>
    </form>
</div>
<a class="back-link" href="view_quote.php?id=<?php echo $id; ?>">← Back to Quote</a>
</div></body></html>
