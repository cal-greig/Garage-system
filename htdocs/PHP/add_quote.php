<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$db_error = "";
if($_SERVER["REQUEST_METHOD"]==="POST"){
    $stmt = $conn->prepare("INSERT INTO quotes (customer_name,customer_phone,customer_email,contact_source,vehicle,registration,job_type,description,status,total_amount,created_by) VALUES (?,?,?,?,?,?,?,?,'pending',0,?)");
    if(!$stmt) {
        $db_error = "Database error: " . $conn->error . " — make sure you have run quotes_setup.sql in phpMyAdmin.";
    } else {
        $stmt->bind_param("sssssssss",
            $_POST["customer"], $_POST["phone"], $_POST["email"],
            $_POST["contact_source"],
            $_POST["vehicle"], $_POST["reg"], $_POST["type"],
            $_POST["description"], $_SESSION["user"]
        );
        if($stmt->execute()) {
            $new_id = $conn->insert_id;
            header("Location: view_quote.php?id=$new_id");
            exit;
        } else {
            $db_error = "Failed to save quote: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Quote — Garage System</title>
<?php include "form_style.php"; ?>
</head>
<body>
<div class="page-header"><h1>New Quote</h1></div>
<div class="form-wrap">
<div class="form-card">

    <?php if($db_error): ?>
    <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#ef4444;border-radius:8px;padding:12px 16px;font-size:0.88rem;margin-bottom:20px;">
        ⚠ <?php echo htmlspecialchars($db_error); ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_field(); ?>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Customer Name</label>
                <input class="field-input" name="customer" placeholder="e.g. John Smith" value="<?php echo htmlspecialchars($_POST['customer'] ?? ''); ?>" required>
            </div>
            <div class="field-group">
                <label class="field-label">Phone Number <span style="font-weight:400;text-transform:none;font-size:0.7rem;opacity:0.6;">(optional)</span></label>
                <input class="field-input" name="phone" placeholder="e.g. 07700 000000" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Email Address <span style="font-weight:400;text-transform:none;font-size:0.7rem;opacity:0.6;">(optional)</span></label>
                <input class="field-input" type="text" name="email" placeholder="e.g. john@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="field-group">
                <label class="field-label">How did they contact? <span style="font-weight:400;text-transform:none;font-size:0.7rem;opacity:0.6;">(optional)</span></label>
                <input class="field-input" name="contact_source" placeholder="e.g. Snapchat: j.smith, Facebook: John Smith" value="<?php echo htmlspecialchars($_POST['contact_source'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Vehicle</label>
                <input class="field-input" name="vehicle" placeholder="e.g. Ford Focus" value="<?php echo htmlspecialchars($_POST['vehicle'] ?? ''); ?>">
            </div>
            <div class="field-group">
                <label class="field-label">Registration</label>
                <input class="field-input" name="reg" placeholder="e.g. AB12 CDE" value="<?php echo htmlspecialchars($_POST['reg'] ?? ''); ?>">
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Job Type</label>
            <input class="field-input" name="type" placeholder="e.g. Full Service, Brake Repair" value="<?php echo htmlspecialchars($_POST['type'] ?? ''); ?>">
        </div>
        <div class="field-group">
            <label class="field-label">Description / Notes</label>
            <textarea class="field-textarea" name="description" placeholder="Details about the work required..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        <button class="submit-btn" type="submit">Create Quote</button>
    </form>
</div>
<a class="back-link" href="quotes.php">← Back to Quotes</a>
</div>
</body></html>
