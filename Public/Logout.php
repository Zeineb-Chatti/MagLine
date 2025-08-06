<?php
session_start();

// 1. Get the current role before destroying session
$role = 'candidate'; // Default value

// First check session (if user is logged in)
if (isset($_SESSION['user_type'])) {
    $role = $_SESSION['user_type'];
} 
// Then check URL parameter (if coming from a logout link)
elseif (isset($_GET['role'])) {
    $role = $_GET['role'];
}

// 2. Destroy the session completely
session_unset();
session_destroy();

// 3. Redirect to login page with the correct role
header("Location: login.php?role=" . urlencode($role));
exit;