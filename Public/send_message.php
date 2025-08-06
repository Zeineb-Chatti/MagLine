<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

header('Content-Type: application/json');

// Validate inputs
if (!isset($_SESSION['user_id']) || !isset($_POST['receiver_id']) || empty(trim($_POST['message']))) {
    echo json_encode(['success' => false, 'error' => 'Missing or empty parameters']);
    exit;
}

$sender_id = $_SESSION['user_id'];
$receiver_id = (int)$_POST['receiver_id'];
$message = trim($_POST['message']);

// Validate receiver_id
if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid receiver ID']);
    exit;
}

// Validate message length
if (strlen($message) > 1000) {
    echo json_encode(['success' => false, 'error' => 'Message too long (max 1000 characters)']);
    exit;
}

// Prevent sending to self
if ($sender_id == $receiver_id) {
    echo json_encode(['success' => false, 'error' => 'Cannot send message to yourself']);
    exit;
}

try {
    // Verify receiver exists
    $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $userCheck->execute([$receiver_id]);
    if (!$userCheck->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Receiver not found']);
        exit;
    }
    
    // Sanitize message (prevent XSS)
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at) 
        VALUES (?, ?, ?, 0, NOW())
    ");
    
    $success = $stmt->execute([$sender_id, $receiver_id, $message]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message_id' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in send_message.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred'
    ]);
}