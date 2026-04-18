<?php
include "config.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["job_id"])) {
    $job_id = intval($_POST["job_id"]);
    $deleted_by = $_SESSION["user"];
    $stmt = $conn->prepare("UPDATE jobs SET deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?");
    $stmt->bind_param("si", $deleted_by, $job_id);
    $stmt->execute();
}

header("Location: ../index.php");
exit();
