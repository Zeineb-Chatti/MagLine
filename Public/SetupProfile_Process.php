<?php
session_start();

// Enhanced error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/profile_errors.log');

// Database Configuration
$host = 'localhost';
$dbname = 'magline';
$username = 'root';
$password = '';

// Initialize PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed: " . $e->getMessage());
}

// Upload directories configuration
$uploadDirs = [
    'profile_photos' => __DIR__ . '/../Public/uploads/profile_photos/',
    'cvs' => __DIR__ . '/../Public/uploads/cvs/',
    'company_logos' => __DIR__ . '/../Public/uploads/company_logos/',
    'manager_photos' => __DIR__ . '/../Public/uploads/manager_photos/'
];

// Create upload directories if they don't exist
foreach ($uploadDirs as $name => $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            error_log("Created directory: $dir");
        } else {
            error_log("Failed to create directory: $dir");
        }
    } else {
        error_log("Directory exists: $dir");
    }
}

/**
 * Enhanced file upload handler with detailed logging
 */
function handleFileUpload($file, array $allowedTypes, int $maxSize, string $targetDir): array {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        error_log("No file uploaded");
        return ['success' => true, 'filename' => null];
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Upload error code: " . $file['error'];
        error_log($errorMsg);
        return ['success' => false, 'errors' => [$errorMsg]];
    }

    // Validate file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        $errorMsg = "Invalid file type: $ext. Allowed: " . implode(', ', $allowedTypes);
        error_log($errorMsg);
        return ['success' => false, 'errors' => [$errorMsg]];
    }

    // Validate file size
    if ($file['size'] > $maxSize) {
        $errorMsg = "File too large: " . $file['size'] . " bytes. Max: " . $maxSize . " bytes (" . ($maxSize/1048576) . "MB)";
        error_log($errorMsg);
        return ['success' => false, 'errors' => [$errorMsg]];
    }

    // Generate unique filename
    $newName = uniqid('', true) . ".$ext";
    $dest = $targetDir . $newName;

    error_log("Attempting to move file from: " . $file['tmp_name'] . " to: " . $dest);

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $errorMsg = "Failed to move uploaded file from {$file['tmp_name']} to $dest";
        error_log($errorMsg);
        return ['success' => false, 'errors' => [$errorMsg]];
    }

    // Verify file was moved successfully
    if (!file_exists($dest)) {
        $errorMsg = "File was not found after move operation: $dest";
        error_log($errorMsg);
        return ['success' => false, 'errors' => [$errorMsg]];
    }

    error_log("File uploaded successfully: $dest (size: " . filesize($dest) . " bytes)");
    return ['success' => true, 'filename' => $newName, 'full_path' => $dest];
}

/**
 * Geocode location using OpenStreetMap Nominatim
 */
function geocodeLocation(string $location): ?array {
    if (strtolower($location) === 'remote') {
        return null;
    }

    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($location) . "&limit=1";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'MagLine/1.0',
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!$curlError && $httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            return [
                'latitude' => (float)$data[0]['lat'],
                'longitude' => (float)$data[0]['lon']
            ];
        }
    }
    
    error_log("Geocoding failed for location: $location. HTTP Code: $httpCode, Error: $curlError");
    return null;
}

/**
 * Test Flask service connectivity
 */
function testFlaskConnection(): array {
    error_log("Testing Flask service connectivity...");
    
    $endpoints = [
        'base' => 'http://localhost:5001/',
        'validate_cv' => 'http://localhost:5001/validate_cv',
        'extract_skills' => 'http://localhost:5001/extract_skills'
    ];
    
    $results = [];
    
    foreach ($endpoints as $name => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true, // HEAD request
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $results[$name] = [
            'url' => $url,
            'http_code' => $httpCode,
            'error' => $curlError,
            'accessible' => ($httpCode > 0 && $curlError === '')
        ];
        
        $status = $results[$name]['accessible'] ? 'OK' : 'FAILED';
        error_log("Flask $name endpoint: $status (HTTP: $httpCode, Error: $curlError)");
    }
    
    return $results;
}

/**
 * Enhanced CV validation with comprehensive debugging
 */
function validateCV(string $cvPath): array {
    error_log("=== CV Validation Started ===");
    error_log("CV Path provided: $cvPath");

    // Check if file exists
    if (!file_exists($cvPath)) {
        $errorMsg = "CV file does not exist: $cvPath";
        error_log($errorMsg);
        return ['valid' => false, 'reason' => $errorMsg];
    }

    // Check if file is readable
    if (!is_readable($cvPath)) {
        $errorMsg = "CV file is not readable: $cvPath";
        error_log($errorMsg);
        return ['valid' => false, 'reason' => $errorMsg];
    }

    // Get file info
    $fileSize = filesize($cvPath);
    $mimeType = mime_content_type($cvPath);
    error_log("CV file info - Size: $fileSize bytes, MIME: $mimeType");

    // Get real path
    $realPath = realpath($cvPath);
    if ($realPath === false) {
        $errorMsg = "Could not resolve real path for: $cvPath";
        error_log($errorMsg);
        return ['valid' => false, 'reason' => $errorMsg];
    }

    error_log("CV real path: $realPath");

    // Prepare data for Flask
    $normalizedPath = str_replace('\\', '/', $realPath);
    $data = json_encode(['cv_path' => $normalizedPath]);
    error_log("Data being sent to Flask: $data");

    // Test Flask connectivity first
    $flaskTest = testFlaskConnection();
    if (!$flaskTest['validate_cv']['accessible']) {
        $errorMsg = "Flask CV validation service is not accessible";
        error_log($errorMsg);
        return ['valid' => false, 'reason' => $errorMsg];
    }

    // Make cURL request to Flask
    $ch = curl_init("http://localhost:5001/validate_cv");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_VERBOSE => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    error_log("Flask response - HTTP Code: $httpCode, cURL Error: $curlError");
    error_log("Flask raw response: $response");

    // Handle cURL errors
    if ($curlErrno !== 0) {
        $errorMsg = "cURL error ($curlErrno): $curlError";
        error_log($errorMsg);
        return ['valid' => false, 'reason' => $errorMsg];
    }

    // Handle HTTP errors
    if ($httpCode !== 200) {
        $errorMsg = "Flask validation service returned HTTP $httpCode";
        if ($response) {
            $errorMsg .= " - Response: $response";
        }
        error_log($errorMsg);
        return ['valid' => false, 'reason' => $errorMsg];
    }

    // Parse JSON response
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "Invalid JSON response from Flask: " . json_last_error_msg();
        error_log($errorMsg);
        return ['valid' => false, 'reason' => $errorMsg];
    }

    error_log("CV validation result: " . json_encode($result));
    error_log("=== CV Validation Completed ===");

    return $result ?? ['valid' => false, 'reason' => 'Empty response from validation service'];
}

/**
 * Enhanced skill extraction with debugging
 */
function extractSkillsFromCV(string $cvPath): array {
    error_log("=== Skill Extraction Started ===");
    error_log("CV Path for skill extraction: $cvPath");

    if (!file_exists($cvPath)) {
        error_log("CV file does not exist for skill extraction");
        return [];
    }

    $realPath = realpath($cvPath);
    $normalizedPath = str_replace('\\', '/', $realPath);
    $data = json_encode(['cv_path' => $normalizedPath]);
    
    error_log("Skill extraction data: $data");

    $ch = curl_init("http://localhost:5001/extract_skills");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("Skill extraction - HTTP Code: $httpCode, Error: $curlError");
    error_log("Skill extraction response: $response");

    if ($curlError || $httpCode !== 200) {
        error_log("Skill extraction failed - HTTP: $httpCode, cURL: $curlError");
        return [];
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON in skill extraction response");
        return [];
    }

    $skills = isset($result['skills']) && is_array($result['skills']) ? $result['skills'] : [];
    error_log("Extracted skills: " . json_encode($skills));
    error_log("=== Skill Extraction Completed ===");

    return $skills;
}

// Main processing logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== Profile Setup Process Started ===");
    error_log("POST data received: " . json_encode($_POST));
    
    $errors = [];
    $user_id = $_SESSION['user_id'] ?? null;
    $role = $_POST['role'] ?? 'candidate';

    error_log("User ID: $user_id, Role: $role");

    if (!$user_id) {
        error_log("User not authenticated");
        die("User not authenticated");
    }

    try {
        $pdo->beginTransaction();
        error_log("Database transaction started");

        if ($role === 'candidate') {
            error_log("Processing candidate profile...");

            // Validate required fields
            $requiredFields = ['full_name', 'phone', 'location'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
                }
            }

            if (!empty($errors)) {
                error_log("Validation errors: " . json_encode($errors));
            }

            // Geocode the location
            $coordinates = geocodeLocation($_POST['location']);
            $latitude = $coordinates['latitude'] ?? null;
            $longitude = $coordinates['longitude'] ?? null;
            error_log("Location coordinates: " . ($latitude ? "Lat: $latitude, Lng: $longitude" : "Not available"));

            // Handle file uploads
            error_log("Processing file uploads...");
            
            $cvResult = handleFileUpload(
                $_FILES['cv'] ?? null, 
                ['pdf'], 
                5 * 1024 * 1024, // 5MB
                $uploadDirs['cvs']
            );

            $photoResult = handleFileUpload(
                $_FILES['photo'] ?? null, 
                ['jpg', 'jpeg', 'png', 'gif'], 
                2 * 1024 * 1024, // 2MB
                $uploadDirs['profile_photos']
            );

            // Check upload results
            if (!$cvResult['success']) {
                $errors = array_merge($errors, $cvResult['errors']);
                error_log("CV upload failed: " . json_encode($cvResult['errors']));
            }

            if (!$photoResult['success']) {
                $errors = array_merge($errors, $photoResult['errors']);
                error_log("Photo upload failed: " . json_encode($photoResult['errors']));
            }

            $extractedSkills = [];

            // Process CV if uploaded successfully
            if ($cvResult['filename'] && empty($errors)) {
                error_log("CV uploaded successfully, starting validation...");
                
                $cvValidation = validateCV($cvResult['full_path']);
                
                if (!$cvValidation['valid']) {
                    error_log("CV validation failed: " . $cvValidation['reason']);
                    // Clean up uploaded file
                    if (file_exists($cvResult['full_path'])) {
                        unlink($cvResult['full_path']);
                        error_log("Deleted invalid CV file: " . $cvResult['full_path']);
                    }
                    $errors[] = $cvValidation['reason'] ?? "CV validation failed";
                } else {
                    error_log("CV validation successful, extracting skills...");
                    $extractedSkills = extractSkillsFromCV($cvResult['full_path']);
                    $_SESSION['extracted_skills'] = $extractedSkills;
                }
            }

            // If no errors, update database
            // In the candidate profile section, after successful photo upload:
if (empty($errors)) {
    error_log("No errors found, updating candidate profile in database...");

    // Update user table with coordinates
    $stmt = $pdo->prepare("
        UPDATE users SET 
            role = ?, name = ?, phone = ?, location = ?, 
            linkedin = ?, about = ?, photo = ?,
            latitude = ?, longitude = ?
        WHERE id = ?
    ");
    $stmt->execute([
        'candidate',
        $_POST['full_name'],
        $_POST['phone'],
        $_POST['location'],
        $_POST['linkedin'] ?? null,
        $_POST['about'] ?? null,
        $photoResult['filename'], // This is the uploaded photo filename
        $latitude,
        $longitude,
        $user_id
    ]);
    
    // ADD THESE LINES TO SET SESSION VARIABLES FOR THE HEADER
    $_SESSION['photo'] = $photoResult['filename'];
    $_SESSION['user_name'] = $_POST['full_name'];
    
    error_log("User table updated successfully with coordinates");
    // ... rest of your code ...


                // Insert resume record if CV was uploaded
                if ($cvResult['filename']) {
                    $stmt = $pdo->prepare("INSERT INTO resumes (user_id, filename) VALUES (?, ?)");
                    $stmt->execute([$user_id, $cvResult['filename']]);
                    error_log("Resume record inserted: " . $cvResult['filename']);
                }

                // Handle skills
                $manualSkillIds = isset($_POST['skills']) && is_array($_POST['skills']) ? 
                    array_map('intval', $_POST['skills']) : [];
                $extractedSkillIds = [];

                error_log("Manual skills: " . json_encode($manualSkillIds));
                error_log("Extracted skills: " . json_encode($extractedSkills));

                // Process extracted skills
                foreach ($extractedSkills as $skillName) {
                    // Check if skill exists
                    $stmt = $pdo->prepare("SELECT id FROM skills WHERE LOWER(name) = LOWER(?) LIMIT 1");
                    $stmt->execute([$skillName]);
                    $skillId = $stmt->fetchColumn();

                    if (!$skillId) {
                        // Create new skill
                        $stmt = $pdo->prepare("INSERT INTO skills (name) VALUES (?)");
                        $stmt->execute([$skillName]);
                        $skillId = $pdo->lastInsertId();
                        error_log("Created new skill: $skillName (ID: $skillId)");
                    }
                    $extractedSkillIds[] = (int) $skillId;
                }

                // Combine manual and extracted skills
                $finalSkills = array_unique(array_merge($manualSkillIds, $extractedSkillIds));
                error_log("Final skills to associate: " . json_encode($finalSkills));

                // Sync user skills
                $stmt = $pdo->prepare("SELECT skill_id FROM user_skills WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $existingSkills = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

                $toInsert = array_diff($finalSkills, $existingSkills);
                $toDelete = array_diff($existingSkills, $finalSkills);

                // Remove old skills
                if (!empty($toDelete)) {
                    $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                    $stmt = $pdo->prepare("DELETE FROM user_skills WHERE user_id = ? AND skill_id IN ($placeholders)");
                    $stmt->execute(array_merge([$user_id], $toDelete));
                    error_log("Removed skills: " . json_encode($toDelete));
                }

                // Add new skills
                if (!empty($toInsert)) {
                    $stmt = $pdo->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?, ?)");
                    foreach ($toInsert as $skillId) {
                        $stmt->execute([$user_id, $skillId]);
                    }
                    error_log("Added skills: " . json_encode($toInsert));
                }
            }

        
           } elseif ($role === 'recruiter') {
    error_log("Processing recruiter profile...");

    // Validate required fields
    $requiredFields = ['company_name', 'manager_name', 'manager_phone', 'manager_email', 'location'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }

    // Handle file uploads
    $companyLogo = handleFileUpload(
        $_FILES['company_logo'] ?? null, 
        ['jpg', 'jpeg', 'png', 'gif'], 
        3 * 1024 * 1024, // 3MB
        $uploadDirs['company_logos']
    );

    $managerPhoto = handleFileUpload(
        $_FILES['manager_photo'] ?? null, 
        ['jpg', 'jpeg', 'png', 'gif'], 
        2 * 1024 * 1024, // 2MB
        $uploadDirs['manager_photos']
    );

    if (!$companyLogo['success']) {
        $errors = array_merge($errors, $companyLogo['errors']);
    }
    if (!$managerPhoto['success']) {
        $errors = array_merge($errors, $managerPhoto['errors']);
    }

    if (empty($errors)) {
    // Update recruiter profile - FIXED VERSION
    $stmt = $pdo->prepare("
        UPDATE users SET 
            role = ?, name = ?, phone = ?, location = ?,
            company_name = ?, company_size = ?, industry = ?, company_website = ?,
            company_logo = ?, manager_name = ?, manager_photo = ?, manager_job_title = ?,
            manager_phone = ?, manager_email = ?, about_company = ?
        WHERE id = ?
    ");
    $stmt->execute([
        'recruiter',
        $_POST['manager_name'],           // name field gets manager name
        $_POST['manager_phone'],          // phone field gets manager phone  
        $_POST['location'],
        $_POST['company_name'],
        $_POST['company_size'] ?? null,
        $_POST['industry'] ?? null,
        $_POST['company_website'] ?? null,
        $companyLogo['filename'],
        $_POST['manager_name'],
        $managerPhoto['filename'],
        $_POST['manager_job_title'] ?? null,
        $_POST['manager_phone'],
        $_POST['manager_email'],          // manager_email field gets manager email
        $_POST['about_company'] ?? null,
        $user_id
    ]);
    
    // Set session variables for the header
    $_SESSION['manager_photo'] = $managerPhoto['filename'];
    $_SESSION['user_name'] = $_POST['manager_name'];
    $_SESSION['company_name'] = $_POST['company_name'];
    $_SESSION['company_logo'] = $companyLogo['filename'];
    
    error_log("Recruiter profile updated successfully with session variables set");
}

        } else {
            $errors[] = "Invalid role specified";
            error_log("Invalid role specified: $role");
        }

        // Finalize transaction
        if (empty($errors)) {
            $pdo->commit();
            error_log("Transaction committed successfully");

            $_SESSION['user_role'] = $role;
            $_SESSION['profile_completed'] = true;
            unset($_SESSION['profile_errors'], $_SESSION['profile_form_data']);

            error_log("Profile setup completed successfully for role: $role");
            header("Location: Dashboard_" . ucfirst($role) . ".php");
            exit();
        } else {
            $pdo->rollBack();
            error_log("Transaction rolled back due to errors: " . json_encode($errors));

            $_SESSION['profile_errors'] = $errors;
            $_SESSION['profile_form_data'] = $_POST;
            header("Location: SetUp_Profile.php?role=" . urlencode($role));
            exit();
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Exception occurred: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        $_SESSION['profile_errors'] = ["System error: " . $e->getMessage()];
        header("Location: SetUp_Profile.php?role=" . urlencode($role));
        exit();
    }

    error_log("=== Profile Setup Process Completed ===");

} else {
    // Redirect if not POST request
    error_log("Non-POST request redirected to setup page");
    header("Location: SetUp_Profile.php");
    exit();
}
?>