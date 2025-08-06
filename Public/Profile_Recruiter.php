<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT company_name, manager_name, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
</head>
<body>
    <h1>Recruiter Profile</h1>
    <p><strong>Manager Name:</strong> <?= htmlspecialchars($user['manager_name']) ?></p>
    <p><strong>Company:</strong> <?= htmlspecialchars($user['company_name']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
</body>
</html>
