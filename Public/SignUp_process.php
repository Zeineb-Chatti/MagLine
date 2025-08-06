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
    header('Location: /Public/signup.php');
    exit;
}

$name = clean($_POST['name'] ?? '');
$email = clean($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = clean($_POST['role'] ?? 'candidate');

if (empty($name) || empty($email) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Please fill all fields correctly.";
    header("Location: /Public/signup.php?role=$role");
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    $_SESSION['error'] = "Email is already registered.";
    header("Location: /Public/signup.php?role=$role");
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$success = $stmt->execute([$name, $email, $hashedPassword, $role]);

if (!$success) {
    $_SESSION['error'] = "Registration failed. Please try again.";
    header("Location: /Public/signup.php?role=$role");
    exit;
}

$userId = $pdo->lastInsertId();

$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $name;
$_SESSION['user_role'] = $role;

header("Location: setup_profile.php?role=$role");
exit;