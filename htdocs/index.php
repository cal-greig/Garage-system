<?php
session_start();

if(!isset($_SESSION["user"])) {
    header("Location: PHP/login.php");
} else {
    header("Location: PHP/dashboard.php");
}
exit();
