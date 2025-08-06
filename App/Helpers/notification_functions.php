<?php
/**
 * Adds a notification to the database
 */
function addNotification($pdo, $userId, $message, $type = 'system', $relatedId = null) {
    try {
        // Verify user exists
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $checkUser->execute([(int)$userId]);
        if (!$checkUser->fetch()) {
            error_log("Notification error: Invalid user ID {$userId}");
            return false;
        }
        
        // Validate notification type
        $validTypes = ['application', 'status_change', 'job_update', 'system'];
        if (!in_array($type, $validTypes)) {
            $type = 'system';
            error_log("Notification warning: Invalid type '{$type}', defaulting to 'system'");
        }
        
        // Insert notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, message, notification_type, related_id, is_read, created_at) 
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        $success = $stmt->execute([
            (int)$userId, 
            trim($message), 
            $type,
            $relatedId !== null ? (int)$relatedId : null
        ]);
        
        if (!$success) {
            error_log("Notification insert failed: " . implode(", ", $stmt->errorInfo()));
        }
        
        return $success;
        
    } catch (PDOException $e) {
        error_log("Database error in addNotification(): " . $e->getMessage());
        return false;
    }
}

/**
 * Gets user notifications
 */
function getUserNotifications($pdo, $userId, $unreadOnly = false, $limit = 50) {
    try {
        $sql = "SELECT id, message, notification_type as type, related_id, is_read, created_at 
                FROM notifications 
                WHERE user_id = ?";
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$userId, (int)$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Database error in getUserNotifications(): " . $e->getMessage());
        return [];
    }
}

/**
 * Marks notification as read
 */
function markNotificationAsRead($pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([(int)$notificationId, (int)$userId]);
    } catch (PDOException $e) {
        error_log("Database error in markNotificationAsRead(): " . $e->getMessage());
        return false;
    }
}

/**
 * Gets unread notification count
 */
function getUnreadNotificationCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([(int)$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Database error in getUnreadNotificationCount(): " . $e->getMessage());
        return 0;
    }
}

/**
 * Cleans up old notifications
 */
function deleteOldNotifications($pdo, $daysOld = 7) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([(int)$daysOld]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Database error in deleteOldNotifications(): " . $e->getMessage());
        return 0;
    }
}
?>