<?php
include "config.php";
if(!isset($_SESSION["user"])) { header("Location: login.php"); exit(); }
csrf_verify();

$part_id   = intval($_POST["part_id"]);
$direction = $_POST["direction"] === "remove" ? "remove" : "add";
$qty       = max(1, intval($_POST["qty"]));

if($direction === "add") {
    $conn->query("UPDATE inventory SET quantity = quantity + $qty WHERE id = $part_id");
} else {
    // Don't go below 0
    $conn->query("UPDATE inventory SET quantity = GREATEST(0, quantity - $qty) WHERE id = $part_id");
}

header("Location: inventory.php");
exit;
