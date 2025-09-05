<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['offer_id']) || !is_numeric($data['offer_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid offer ID']);
    exit;
}

if (!isset($data['new_status']) || !in_array($data['new_status'], ['active', 'inactive', 'filled'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$offerId = (int)$data['offer_id'];
$newStatus = $data['new_status'];
$recruiterId = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, status 
        FROM offers 
        WHERE id = ? AND recruiter_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$offerId, $recruiterId]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$offer) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Job offer not found']);
        exit;
    }
   
    if ($offer['status'] === $newStatus) {
        $pdo->rollBack();
        $statusText = $newStatus === 'active' ? 'active' : 'closed';
        echo json_encode(['success' => false, 'message' => "Job posting is already $statusText"]);
        exit;
    }
    
    $stmt = $pdo->prepare("
        UPDATE offers 
        SET status = ? 
        WHERE id = ? AND recruiter_id = ?
    ");
    $stmt->execute([$newStatus, $offerId, $recruiterId]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update job status']);
        exit;
    }

    $pdo->commit();

    $actionText = $newStatus === 'active' ? 'reactivated' : 'closed';
    echo json_encode([
        'success' => true, 
        'message' => "Job posting has been $actionText successfully",
        'offer_id' => $offerId,
        'new_status' => $newStatus
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();

    error_log("Database error in toggle_job_status.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred. Please try again.'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();

    error_log("General error in toggle_job_status.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>
