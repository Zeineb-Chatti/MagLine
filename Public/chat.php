<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

$recipient_id = $_GET['user_id'] ?? null;
if (!$recipient_id || !is_numeric($recipient_id)) {
    header("Location: messages.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Get recipient details - SIMPLIFIED QUERY
$stmt = $pdo->prepare("SELECT id, name, photo FROM users WHERE id = ?");
$stmt->execute([$recipient_id]);
$recipient = $stmt->fetch();

if (!$recipient) {
    header("Location: messages.php?error=user_not_found");
    exit;
}

// IMPORTANT: Mark messages as read IMMEDIATELY when opening chat
$markReadStmt = $pdo->prepare("
    UPDATE messages 
    SET is_read = 1 
    WHERE receiver_id = ? 
    AND sender_id = ? 
    AND is_read = 0
");
$markReadStmt->execute([$current_user_id, $recipient_id]);

// Store minimal data in session - NO last_message_time needed
$_SESSION['force_open_chat'] = [
    'user_id' => $recipient['id'],
    'name' => $recipient['name'],
    'photo' => $recipient['photo']
];

// Redirect to messages page
header("Location: messages.php");
exit;
?>