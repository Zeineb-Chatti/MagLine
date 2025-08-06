<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error marking all notifications read: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}