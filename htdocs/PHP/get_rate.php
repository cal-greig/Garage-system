<?php
// Returns current hourly labour rate from settings table
function get_hourly_rate($conn) {
    $result = $conn->query("SELECT value FROM settings WHERE key_name='hourly_rate'");
    if($result && $row = $result->fetch_assoc()) return floatval($row["value"]);
    return 50.00;
}

// Returns friends & family discount percentage
function get_ff_discount($conn) {
    $result = $conn->query("SELECT value FROM settings WHERE key_name='ff_discount'");
    if($result && $row = $result->fetch_assoc()) return floatval($row["value"]);
    return 10.00;
}
