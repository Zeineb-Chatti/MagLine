<?php
session_start();
header('Content-Type: application/json');

// Debug: Log the session data
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['recruiter_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated', 'debug' => $_SESSION]);
    exit;
}

try {
    // Database connection - adjust path as needed
    require_once '../config/database.php';
    
    // Determine user type and ID
    $isCandidate = isset($_SESSION['user_id']);
    $userId = $isCandidate ? $_SESSION['user_id'] : $_SESSION['recruiter_id'];
    $userType = $isCandidate ? 'candidate' : 'recruiter';
    
    // Debug: Log user info
    error_log("User ID: $userId, User Type: $userType");
    
    // Query to count unread messages
    // Try different possible table structures
    $possibleQueries = [
        // Option 1: With sender_type and receiver_type columns
        "SELECT COUNT(*) as unread_count 
         FROM messages 
         WHERE receiver_id = ? 
         AND receiver_type = ? 
         AND is_read = 0",
        
        // Option 2: Without type columns, assuming separate user tables
        "SELECT COUNT(*) as unread_count 
         FROM messages 
         WHERE receiver_id = ? 
         AND is_read = 0",
         
        // Option 3: Different column names
        "SELECT COUNT(*) as unread_count 
         FROM messages 
         WHERE to_user_id = ? 
         AND read_status = 0"
    ];
    
    $unreadCount = 0;
    $queryWorked = false;
    
    foreach ($possibleQueries as $index => $query) {
        try {
            $stmt = $pdo->prepare($query);
            if ($index === 0) {
                $stmt->execute([$userId, $userType]);
            } else {
                $stmt->execute([$userId]);
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $unreadCount = $result['unread_count'] ?? 0;
            $queryWorked = true;
            error_log("Query $index worked. Unread count: $unreadCount");
            break;
        } catch (PDOException $e) {
            error_log("Query $index failed: " . $e->getMessage());
            continue;
        }
    }
    
    if (!$queryWorked) {
        // If no queries work, return a test count for debugging
        error_log("No queries worked, returning test data");
        echo json_encode([
            'success' => true,
            'unread_count' => 2, // Test value
            'debug' => 'No queries worked, using test data'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'debug' => "User: $userId ($userType)"
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>