<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();
$id = intval($_GET["id"]);
if($_SERVER["REQUEST_METHOD"]==="POST"){
    $stmt = $conn->prepare("INSERT INTO quote_labour (quote_id,hours,description) VALUES (?,?,?)");
    $stmt->bind_param("ids", $id, $_POST["hours"], $_POST["desc"]);
    $stmt->execute();
    header("Location: view_quote.php?id=$id"); exit;
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Labour — Quote</title><?php include "form_style.php"; ?>
</head><body>
<div class="page-header"><h1>Add Labour to Quote</h1></div>
<div class="form-wrap"><div class="form-card">
    <form method="POST">
        <?php csrf_field(); ?>
        <div class="field-group">
            <label class="field-label">Hours</label>
            <input class="field-input" type="number" step="0.01" min="0.01" name="hours" placeholder="e.g. 1.5, 0.1, 0.05" required>
        </div>
        <div class="field-group">
            <label class="field-label">Description</label>
            <input class="field-input" name="desc" placeholder="e.g. Replaced brake pads front axle" required>
        </div>
        <button class="submit-btn" type="submit">Add Labour</button>
    </form>
</div>
<a class="back-link" href="view_quote.php?id=<?php echo $id; ?>">← Back to Quote</a>
</div></body></html>
