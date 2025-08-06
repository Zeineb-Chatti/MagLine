<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['query'])) {
    echo json_encode(['success' => false, 'error' => 'Search query is required']);
    exit;
}

$query = trim($input['query']);
$currentUserId = $_SESSION['user_id'];

try {
    $searchTerm = '%' . $query . '%';
    
    $sql = "
        SELECT 
            id,
            name,
            role,
            photo,
            company_name,
            manager_photo,
            location
        FROM users 
        WHERE 
            id != ? AND
            (
                name LIKE ? OR
                email LIKE ? OR
                company_name LIKE ? OR
                location LIKE ?
            )
        ORDER BY
            CASE
                WHEN name LIKE ? THEN 0  -- Exact match first
                WHEN name LIKE ? THEN 1  -- Starts with next
                ELSE 2                   -- Contains last
            END,
            name ASC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $currentUserId,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $query,          // For exact match
        $query . '%'     // For starts with
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results
    $formattedResults = array_map(function($user) {
        // Determine photo path
        $photoPath = '/Public/Assets/default-user.png';
        if ($user['role'] === 'recruiter' && !empty($user['manager_photo'])) {
            $photoPath = '/Public/Uploads/Manager_Photos/' . $user['manager_photo'];
        } elseif (!empty($user['photo'])) {
            $photoPath = '/Public/Uploads/profile_photos/' . $user['photo'];
        }
        
        // Build role badge text
        $roleBadge = ($user['role'] === 'recruiter')
            ? 'Recruiter' . (!empty($user['company_name']) ? ' at ' . $user['company_name'] : '')
            : 'Candidate';
        
        // Only show location if available
        $locationInfo = !empty($user['location']) ? $user['location'] : '';

        return [
            'id' => $user['id'],
            'name' => $user['name'] ?? 'Unknown User',
            'photo' => $photoPath,
            'role' => $user['role'],
            'role_badge' => $roleBadge,
            'location' => $locationInfo,
            'profile_url' => 'view_profile.php?user_id=' . $user['id']
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'results' => $formattedResults
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}