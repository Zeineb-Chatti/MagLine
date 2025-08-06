<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/database.php';

function clean($data) {
    return htmlspecialchars(trim($data));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Public/login.php');
    exit;
}

$email = clean($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = clean($_POST['role'] ?? 'candidate');

if (empty($email) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Please enter valid email and password.";
    header("Location: ../Public/login.php?role=$role");
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, password, role, photo, manager_photo FROM users WHERE email = ? AND role = ?");
$stmt->execute([$email, $role]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "No account found with this email and role.";
    header("Location: ../Public/login.php?role=$role");
    exit;
}

if (!password_verify($password, $user['password'])) {
    $_SESSION['error'] = "Incorrect password.";
    header("Location: ../Public/login.php?role=$role");
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];

// set the right photo depending on role
if ($user['role'] === 'candidate') {
    $_SESSION['photo'] = $user['photo'] ?? 'default-user.png';
} elseif ($user['role'] === 'recruiter') {
    $_SESSION['manager_photo'] = $user['manager_photo'] ?? 'default-user.png';  // Changed to manager_photo
}

if ($user['role'] === 'candidate') {
    header("Location: ../Public/dashboard_candidate.php");
} else {
    header("Location: ../Public/dashboard_recruiter.php");
}
exit;