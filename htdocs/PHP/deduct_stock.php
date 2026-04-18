<?php
include "config.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

if($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: dashboard.php"); exit(); }

$job_id = intval($_POST["job_id"] ?? 0);

// Verify job exists and is complete
$stmt = $conn->prepare("SELECT id, status, stock_deducted FROM jobs WHERE id = ? AND (deleted=0 OR deleted IS NULL)");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if(!$job) { header("Location: dashboard.php"); exit(); }
if($job["stock_deducted"]) {
    header("Location: view_job.php?id=$job_id&msg=already_deducted");
    exit();
}

// Get all job parts that are linked to inventory
$parts = $conn->query("SELECT inventory_id, quantity FROM job_parts WHERE job_id = $job_id AND inventory_id IS NOT NULL AND inventory_id > 0");

$conn->begin_transaction();
try {
    while($part = $parts->fetch_assoc()) {
        $inv_id = intval($part["inventory_id"]);
        $qty    = intval($part["quantity"]);
        // Deduct but never go below 0
        $upd = $conn->prepare("UPDATE inventory SET quantity = GREATEST(0, quantity - ?) WHERE id = ?");
        $upd->bind_param("ii", $qty, $inv_id);
        $upd->execute();
    }
    // Mark stock as deducted on the job
    $conn->query("UPDATE jobs SET stock_deducted = 1 WHERE id = $job_id");
    $conn->commit();
    header("Location: view_job.php?id=$job_id&msg=stock_deducted");
} catch(Exception $e) {
    $conn->rollback();
    header("Location: view_job.php?id=$job_id&msg=deduct_error");
}
exit;
