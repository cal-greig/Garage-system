<?php
include "config.php";
include "navbar.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$id = intval($_GET["id"]);

if($_SERVER["REQUEST_METHOD"] === "POST") {
    $assigned_to = intval($_POST["assigned_to"] ?? 0) ?: null;
    $stmt = $conn->prepare("UPDATE jobs SET job_date=?, customer_name=?, vehicle=?, registration=?, job_type=?, description=?, status=?, assigned_to=? WHERE id=?");
    if($stmt) {
        $stmt->bind_param("sssssssii", $_POST["date"], $_POST["customer"], $_POST["vehicle"], $_POST["reg"], $_POST["type"], $_POST["description"], $_POST["status"], $assigned_to, $id);
    } else {
        // Fallback if assigned_to column not yet added
        $stmt = $conn->prepare("UPDATE jobs SET job_date=?, customer_name=?, vehicle=?, registration=?, job_type=?, description=?, status=? WHERE id=?");
        $stmt->bind_param("sssssssi", $_POST["date"], $_POST["customer"], $_POST["vehicle"], $_POST["reg"], $_POST["type"], $_POST["description"], $_POST["status"], $id);
    }
    $stmt->execute();
    header("Location: view_job.php?id=" . $id);
    exit;
}

$stmt2 = $conn->prepare("SELECT * FROM jobs WHERE id=?");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$job = $stmt2->get_result()->fetch_assoc();

// Load mechanics for assignment dropdown
$mechanics = [];
$mech_result = $conn->query("SELECT id, username, role FROM users ORDER BY username ASC");
if($mech_result) while($r = $mech_result->fetch_assoc()) $mechanics[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Job — Garage System</title>
<?php include "form_style.php"; ?>
</head>
<body>
<div class="page-header"><h1>Edit Job</h1></div>
<div class="form-wrap">
<div class="form-card">
    <form method="POST">
        <?php csrf_field(); ?>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Date</label>
                <input class="field-input" type="date" name="date" value="<?php echo $job['job_date']; ?>">
            </div>
            <div class="field-group">
                <label class="field-label">Status</label>
                <select class="field-select" name="status">
                    <option value="not started" <?php if($job['status']=='not started') echo 'selected'; ?>>Not Started</option>
                    <option value="in progress" <?php if($job['status']=='in progress') echo 'selected'; ?>>In Progress</option>
                    <option value="complete"    <?php if($job['status']=='complete')    echo 'selected'; ?>>Complete</option>
                </select>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Customer Name</label>
            <input class="field-input" name="customer" value="<?php echo htmlspecialchars($job['customer_name']); ?>" required>
        </div>
        <div class="form-grid-2">
            <div class="field-group">
                <label class="field-label">Vehicle</label>
                <input class="field-input" name="vehicle" value="<?php echo htmlspecialchars($job['vehicle']); ?>">
            </div>
            <div class="field-group">
                <label class="field-label">Registration</label>
                <input class="field-input" name="reg" value="<?php echo htmlspecialchars($job['registration']); ?>">
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Job Type</label>
            <input class="field-input" name="type" value="<?php echo htmlspecialchars($job['job_type']); ?>">
        </div>
        <div class="field-group">
            <label class="field-label">Assigned Mechanic</label>
            <select class="field-select" name="assigned_to">
                <option value="">— Unassigned —</option>
                <?php foreach($mechanics as $m): ?>
                    <option value="<?php echo $m['id']; ?>"
                        <?php echo ($job['assigned_to'] ?? null) == $m['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['username']); ?>
                        <?php echo $m['role'] === 'admin' ? ' (Admin)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-group">
            <label class="field-label">Description / Notes</label>
            <textarea class="field-textarea" name="description"><?php echo htmlspecialchars($job['description']); ?></textarea>
        </div>
        <button class="submit-btn" type="submit">Save Changes</button>
    </form>
</div>
<a class="back-link" href="view_job.php?id=<?php echo $id; ?>">← Back to Job</a>
</div>
</body>
</html>
