<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$sender_id = $_POST['sender_id'] ?? null;

if (!$sender_id || !is_numeric($sender_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid sender ID']);
    exit;
}

try {
$stmt = $pdo->prepare("
    UPDATE messages 
    SET is_read = 1 
    WHERE receiver_id = ? 
    AND sender_id = ? 
    AND is_read = 0
");
$stmt->execute([$current_user_id, $sender_id]);
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Database error in mark_messages_read.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>
