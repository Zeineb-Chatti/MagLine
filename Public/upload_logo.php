<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$recruiterId = $_SESSION['user_id'];
$uploadDir = __DIR__ . '/../Public/Uploads/company_logos/';

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$response = ['success' => false];

try {
    // Validate file
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['logo'];
    
    // Validate image
    $validTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $validTypes)) {
        throw new Exception('Only JPG, PNG or GIF images are allowed');
    }
    
    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('File size exceeds 2MB limit');
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('logo_') . '.' . $ext;
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Update database
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET company_logo = ? WHERE id = ?");
        $stmt->execute([$filename, $recruiterId]);
        
        // Delete old logo if exists
        $oldLogo = $pdo->prepare("SELECT company_logo FROM users WHERE id = ?");
        $oldLogo->execute([$recruiterId]);
        $oldLogoPath = $oldLogo->fetchColumn();
        
        if ($oldLogoPath && file_exists($uploadDir . $oldLogoPath)) {
            unlink($uploadDir . $oldLogoPath);
        }
        
        $response = [
            'success' => true,
            'newLogoUrl' => '/MagLine/Public/Uploads/company_logos/' . $filename
        ];
    } else {
        throw new Exception('Failed to move uploaded file');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);