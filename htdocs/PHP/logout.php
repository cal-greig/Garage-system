<?php
include "config.php";
session_destroy();
header("Location: /PHP/login.php");
exit();
