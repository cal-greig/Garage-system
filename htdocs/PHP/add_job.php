<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

if($_SERVER["REQUEST_METHOD"]=="POST"){
    $stmt=$conn->prepare("INSERT INTO jobs (job_date,customer_name,vehicle,registration,job_type,description,status) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss", $_POST["date"], $_POST["customer"], $_POST["vehicle"], $_POST["reg"], $_POST["type"], $_POST["description"], $_POST["status"]);
    $stmt->execute();
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Job — Garage System</title>
<?php include "form_style.php"; ?>
</head>
<body>
<div class="page-header"><h1>Add Job</h1></div>
<div class="form-wrap">
<div class="form-card">
    <form method="POST">
        <?php csrf_field(); ?>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Date</label>
                <input class="field-input" type="date" name="date" required>
            </div>
            <div class="field-group">
                <label class="field-label">Status</label>
                <select class="field-select" name="status">
                    <option value="not started">Not Started</option>
                    <option value="in progress">In Progress</option>
                    <option value="complete">Complete</option>
                </select>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Customer Name</label>
            <input class="field-input" name="customer" placeholder="e.g. John Smith" required>
        </div>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Vehicle</label>
                <input class="field-input" name="vehicle" placeholder="e.g. Ford Focus">
            </div>
            <div class="field-group">
                <label class="field-label">Registration</label>
                <input class="field-input" name="reg" placeholder="e.g. AB12 CDE">
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Job Type</label>
            <input class="field-input" name="type" placeholder="e.g. Full Service, MOT, Brake Repair">
        </div>
        <div class="field-group">
            <label class="field-label">Description / Notes</label>
            <textarea class="field-textarea" name="description" placeholder="Any additional notes about the job..."></textarea>
        </div>
        <button class="submit-btn" type="submit">Save Job</button>
    </form>
</div>
<a class="back-link" href="dashboard.php">← Back to Dashboard</a>
</div>
</body>
</html>
