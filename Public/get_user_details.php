<?php
session_start();
require __DIR__ . '/../../Config/database.php';

header('Content-Type: application/json');

$userId = $_GET['id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, photo FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'user' => $user ?: null
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false]);
}