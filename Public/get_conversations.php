<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.name,
            u.role,
            u.photo,
            u.manager_photo,
            MAX(m.created_at) as last_message_time,
            COUNT(CASE WHEN m.receiver_id = ? AND m.sender_id = u.id AND m.is_read = 0 THEN 1 END) as unread_count,
            (SELECT message FROM messages 
             WHERE (sender_id = u.id AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = u.id)
             ORDER BY created_at DESC 
             LIMIT 1) as last_message
        FROM users u
        LEFT JOIN messages m ON (
            (m.sender_id = u.id AND m.receiver_id = ?) OR
            (m.sender_id = ? AND m.receiver_id = u.id))
        WHERE u.id != ?
        GROUP BY u.id, u.name, u.role, u.photo, u.manager_photo
        HAVING last_message_time IS NOT NULL
        ORDER BY last_message_time DESC
    ");
    
    $stmt->execute([
        $current_user_id, 
        $current_user_id, 
        $current_user_id,
        $current_user_id,
        $current_user_id,
        $current_user_id
    ]);
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'conversations' => array_map(function($conv) {
            if ($conv['role'] === 'recruiter' && !empty($conv['manager_photo'])) {
                $photoPath = '/Public/Uploads/Manager_Photos/' . $conv['manager_photo'];
            } elseif (!empty($conv['photo'])) {
                $photoPath = '/Public/Uploads/profile_photos/' . $conv['photo'];
            } else {
                $photoPath = '/Public/Assets/default-user.png';
            }

            return [
                'id' => (int)$conv['id'],
                'name' => $conv['name'],
                'photo' => $photoPath,
                'last_message' => $conv['last_message'] ? 
                    (strlen($conv['last_message']) > 50 ? 
                        substr($conv['last_message'], 0, 50) . '...' : 
                        $conv['last_message']) : null,
                'last_message_time' => $conv['last_message_time'],
                'unread_count' => (int)$conv['unread_count']
            ];
        }, $conversations)
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error in get_conversations.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>