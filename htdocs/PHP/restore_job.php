<?php
include "config.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["job_id"])) {
    $job_id = intval($_POST["job_id"]);
    $stmt = $conn->prepare("UPDATE jobs SET deleted=0, deleted_at=NULL, deleted_by=NULL WHERE id=?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
}

header("Location: deleted_jobs.php");
exit();
