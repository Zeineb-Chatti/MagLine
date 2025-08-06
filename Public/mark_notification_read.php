<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$notificationId) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error marking notification read: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}