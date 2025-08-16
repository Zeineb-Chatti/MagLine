<?php
session_start();

$role = 'candidate'; // Default value

if (isset($_SESSION['user_type'])) {
    $role = $_SESSION['user_type'];
} 

elseif (isset($_GET['role'])) {
    $role = $_GET['role'];
}

session_unset();
session_destroy();

header("Location: login.php?role=" . urlencode($role));
exit;
