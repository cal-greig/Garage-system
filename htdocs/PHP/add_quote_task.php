<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();
$id = intval($_GET["id"]);
if($_SERVER["REQUEST_METHOD"]==="POST"){
    $stmt = $conn->prepare("INSERT INTO quote_tasks (quote_id,task) VALUES (?,?)");
    $stmt->bind_param("is", $id, $_POST["task"]);
    $stmt->execute();
    header("Location: view_quote.php?id=$id"); exit;
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Task — Quote</title><?php include "form_style.php"; ?>
</head><body>
<div class="page-header"><h1>Add Task to Quote</h1></div>
<div class="form-wrap"><div class="form-card">
    <form method="POST">
        <?php csrf_field(); ?>
        <div class="field-group">
            <label class="field-label">Task Description</label>
            <input class="field-input" name="task" placeholder="e.g. Replace front brake pads and discs" required>
        </div>
        <button class="submit-btn" type="submit">Add Task</button>
    </form>
</div>
<a class="back-link" href="view_quote.php?id=<?php echo $id; ?>">← Back to Quote</a>
</div></body></html>
