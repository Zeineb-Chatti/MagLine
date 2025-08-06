<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['with_user'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$user_id = $_SESSION['user_id'];
$with_user = (int)$_GET['with_user'];

// Validate that with_user is a valid user ID
if ($with_user <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

try {
    // First, verify that the other user exists
    $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $userCheck->execute([$with_user]);
    if (!$userCheck->fetch()) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Fetch messages between the two users
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.sender_id,
            m.receiver_id,
            m.message,
            m.is_read,
            m.created_at,
            u.name as sender_name 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    
    $stmt->execute([$user_id, $with_user, $with_user, $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark messages from the other user as read
    $markReadStmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $markReadStmt->execute([$with_user, $user_id]);
    
    // Format messages
    foreach ($messages as &$message) {
        $message['sender_id'] = (int)$message['sender_id'];
        $message['receiver_id'] = (int)$message['receiver_id'];
        $message['is_read'] = (bool)$message['is_read'];
    }
    
    echo json_encode([
        'success' => true, 
        'messages' => $messages
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_messages.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred'
    ]);
}