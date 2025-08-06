<?php
ob_start();
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../App/Helpers/notification_functions.php';

if (!function_exists('addNotification')) {
    die(json_encode(['success' => false, 'message' => 'System error: Missing functions']));
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Validate session
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'candidate') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login as candidate']);
    exit;
}

// 2. Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// 3. Validate job ID
if (!isset($input['job_id']) || !filter_var($input['job_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit;
}

$jobId = (int)$input['job_id'];
$candidateId = (int)$_SESSION['user_id'];

try {
    // Begin transaction
    $pdo->beginTransaction();

    // 4. Verify job exists
    $stmt = $pdo->prepare("SELECT id FROM offers WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$jobId]);
    if (!$stmt->fetch()) {
        throw new Exception('Job no longer available');
    }

    // 5. Check for existing application
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE offer_id = ? AND candidate_id = ? LIMIT 1");
    $stmt->execute([$jobId, $candidateId]);
    if ($stmt->fetch()) {
        throw new Exception('You have already applied for this job');
    }

    // 6. Create application
    $stmt = $pdo->prepare("INSERT INTO applications (offer_id, candidate_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
    if (!$stmt->execute([$jobId, $candidateId])) {
        throw new Exception('Failed to submit application');
    }

    // Commit transaction
    $pdo->commit();

    // --- Notification part ---
    $stmt = $pdo->prepare("SELECT title, recruiter_id FROM offers WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    if ($job) {
        $jobTitle = $job['title'];
        $recruiterId = $job['recruiter_id'];
        $message = "A new candidate applied to your job offer: " . htmlspecialchars($jobTitle);
        addNotification(
            $pdo,
            $recruiterId,
            "New application for your job: " . $jobTitle,
            'application',
            $jobId
        );
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully!',
        'application_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again later.'
    ]);
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
