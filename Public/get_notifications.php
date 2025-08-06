<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

// Clear any previous output
ob_clean();
header('Content-Type: application/json');

// Verify session
if (!isset($_SESSION['user_id'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]));
}

$userId = $_SESSION['user_id'];

try {
    // Cleanup old notifications
    $cleanupStmt = $pdo->prepare("DELETE FROM notifications 
                                 WHERE user_id = ? 
                                 AND is_read = 1 
                                 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $cleanupStmt->execute([$userId]);
    
    // Get unread count
    $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications 
                                WHERE user_id = ? AND is_read = 0");
    $unreadStmt->execute([$userId]);
    $unreadCount = $unreadStmt->fetchColumn();

    // Get notifications
    $query = "SELECT id, message, notification_type as type, related_id, is_read, created_at 
              FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
    
    if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $query .= " LIMIT " . (int)$_GET['limit'];
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure no output before this
    echo json_encode([
        'success' => true,
        'unread_count' => (int)$unreadCount,
        'notifications' => array_map(function($n) {
            return [
                'id' => (int)$n['id'],
                'type' => $n['type'],
                'related_id' => (int)$n['related_id'],
                'message' => $n['message'],
                'is_read' => (bool)$n['is_read'],
                'formatted_date' => date('M j, Y g:i a', strtotime($n['created_at']))
            ];
        }, $notifications)
    ]);
    exit();

} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    die(json_encode([
        'success' => false,
        'message' => 'Database error'
    ]));
}