<?php
session_start();
require_once __DIR__ . '/../Config/database.php';


if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'candidate') {
    header("Location: Login.php");
    exit;
}
function geocodeLocation(string $location): ?array {
    if (empty(trim($location))) {
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
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            return [
                'latitude' => (float)$data[0]['lat'],
                'longitude' => (float)$data[0]['lon']
            ];
        }
    }
    
    error_log("Geocoding failed for location: $location. HTTP Code: $httpCode");
    return null;
}
// CV Validation and Skill Extraction Functions
function validateCV(string $cvPath): array {
    $data = json_encode(['cv_path' => str_replace('\\', '/', realpath($cvPath))]);
    $ch = curl_init("http://localhost:5001/validate_cv");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return ['valid' => false, 'reason' => 'CV validation service error (HTTP ' . $code . ')'];
    $result = json_decode($response, true);
    return $result ?? ['valid' => false, 'reason' => 'Invalid JSON from CV validation'];
}

function extractSkillsFromCV(string $cvPath): array {
    error_log("Starting skill extraction for: " . $cvPath);
    
    $normalizedPath = str_replace('\\', '/', realpath($cvPath));
    $data = json_encode(['cv_path' => $normalizedPath]);
    
    error_log("Sending to Flask: " . $data);
    
    $ch = curl_init("http://localhost:5001/extract_skills");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("Flask response - Code: $code, Error: $error, Response: $response");
    
    $result = json_decode($response, true);
    
    if ($code !== 200) {
        error_log("Skill extraction failed with code $code");
        return [];
    }
    
    if (!isset($result['skills'])) {
        error_log("Invalid response format - missing skills key");
        return [];
    }
    
    error_log("Extracted skills: " . implode(", ", $result['skills']));
    return $result['skills'];
}

$candidateId = $_SESSION['user_id'];
$errors = [];
$success = false;



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    

if (isset($_FILES['cv'])) {
    try {
        $filename = $_FILES['cv']['name'];
        $tmpName = $_FILES['cv']['tmp_name'];
        $fileSize = $_FILES['cv']['size'];
        $fileType = $_FILES['cv']['type'];
        $fileError = $_FILES['cv']['error'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Basic validation
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $fileError);
        }
        
        if ($fileSize > 5 * 1024 * 1024) {
            throw new Exception("File size exceeds 5MB limit");
        }
        
        // Strict PDF validation
        $allowedTypes = ['application/pdf', 'application/x-pdf'];
        if (!in_array($fileType, $allowedTypes) || $ext !== 'pdf') {
            throw new Exception("Only PDF files are allowed");
        }
        
        // Verify PDF magic number
        $fileHeader = file_get_contents($tmpName, false, null, 0, 4);
        if ($fileHeader !== "%PDF") {
            throw new Exception("The file is not a valid PDF document");
        }
        
        $newFilename = 'cv_' . $candidateId . '_' . time() . '.pdf';
        $uploadPath = '../Public/Uploads/Cvs/' . $newFilename;
        
        if (!move_uploaded_file($tmpName, $uploadPath)) {
            throw new Exception("Failed to upload file");
        }
        
        // Validate the CV using Flask API
        $cvValidation = validateCV($uploadPath);
        if (!$cvValidation['valid']) {
            unlink($uploadPath);
            throw new Exception("Invalid CV: " . ($cvValidation['reason'] ?? "The file doesn't appear to be a valid CV"));
        }
        
        // Extract skills from CV
        $extractedSkills = extractSkillsFromCV($uploadPath);
        
        $pdo->beginTransaction();
        
        // Delete old CV
        $stmt = $pdo->prepare("DELETE FROM resumes WHERE user_id = ?");
        $stmt->execute([$candidateId]);
        
        // Insert new CV
        $stmt = $pdo->prepare("INSERT INTO resumes (user_id, filename, uploaded_at) VALUES (?, ?, NOW())");
        $stmt->execute([$candidateId, $newFilename]);
        
        // Process extracted skills 
        $extractedSkillIds = [];
        foreach ($extractedSkills as $skillName) {
            $stmt = $pdo->prepare("SELECT id FROM skills WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $stmt->execute([$skillName]);
            $skillId = $stmt->fetchColumn();

            if (!$skillId) {
                $stmt = $pdo->prepare("INSERT INTO skills (name) VALUES (?)");
                $stmt->execute([$skillName]);
                $skillId = $pdo->lastInsertId();
            }
            $extractedSkillIds[] = (int) $skillId;
        }
        
        
        $stmt = $pdo->prepare("SELECT skill_id FROM user_skills WHERE user_id = ? AND source = 'cv'");
        $stmt->execute([$candidateId]);
        $currentCvSkills = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
       
        $toDelete = array_diff($currentCvSkills, $extractedSkillIds);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $stmt = $pdo->prepare("DELETE FROM user_skills WHERE user_id = ? AND skill_id IN ($placeholders) AND source = 'cv'");
            $stmt->execute(array_merge([$candidateId], $toDelete));
        }
        
        // Insert new CV-extracted skills
        $toInsert = array_diff($extractedSkillIds, $currentCvSkills);
        if (!empty($toInsert)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_skills (user_id, skill_id, source) VALUES (?, ?, 'cv')");
            foreach ($toInsert as $skillId) {
                $stmt->execute([$candidateId, $skillId]);
            }
        }
        
        $pdo->commit();
        
        $_SESSION['flash_message'] = [
            'type' => 'success', 
            'message' => 'CV uploaded successfully! ' . 
                        (count($extractedSkills) > 0 ? 'Detected skills: ' . implode(', ', $extractedSkills) : '')
        ];
        $success = true;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (isset($uploadPath) && file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        $errors[] = $e->getMessage();
    }
}
if (isset($_POST['update_profile'])) {
    $name = $_POST['name'] ?? '';
    $newLocation = $_POST['location'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $about = $_POST['about'] ?? '';
    $linkedin = $_POST['linkedin'] ?? '';
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT location, latitude, longitude FROM users WHERE id = ?");
        $stmt->execute([$candidateId]);
        $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentLocation = $currentData['location'] ?? '';
        $currentLat = $currentData['latitude'] ?? null;
        $currentLng = $currentData['longitude'] ?? null;
        
        $latitude = $currentLat;
        $longitude = $currentLng;
 
        if ($newLocation !== $currentLocation) {
            if (!empty(trim($newLocation))) {
                $coordinates = geocodeLocation($newLocation);
                $latitude = $coordinates['latitude'] ?? null;
                $longitude = $coordinates['longitude'] ?? null;
            } else {
                $latitude = null;
                $longitude = null;
            }
        }

        $stmt = $pdo->prepare("
            UPDATE users SET 
                name = ?, 
                location = ?, 
                phone = ?, 
                about = ?, 
                linkedin = ?,
                latitude = ?,
                longitude = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name,
            $newLocation,
            $phone,
            $about,
            $linkedin,
            $latitude,
            $longitude,
            $candidateId
        ]);
        
        $pdo->commit();
        
        $_SESSION['user_name'] = $name;
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Profile updated successfully!'];
        $success = true;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = "Failed to update profile: " . $e->getMessage();
    }
}
    
    if ($success) {
        header("Location: Candidate_profile.php");
        exit;
    }
}

try {
    $stmt = $pdo->prepare("SELECT name, email, location, phone, about, linkedin, created_at, photo FROM users WHERE id = ?");
    $stmt->execute([$candidateId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Profile not found'];
        header("Location: Dashboard_Candidate.php");
        exit;
    }

    $candidate = array_map(function($value) { return $value ?? ''; }, $candidate);

    if (!isset($_SESSION['user_photo'])) {
        $_SESSION['user_photo'] = $candidate['photo'];
    }
$stmt = $pdo->prepare("SELECT s.id, s.name, us.source FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ?");
$stmt->execute([$candidateId]);
$currentSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
$currentSkillIds = array_column($currentSkills, 'id');

$cvSkills = array_filter($currentSkills, function($skill) { return $skill['source'] === 'cv'; });
$manualSkills = array_filter($currentSkills, function($skill) { return $skill['source'] === 'manual'; });

    $stmt = $pdo->prepare("SELECT id, name FROM skills ORDER BY name");
    $stmt->execute();
    $allSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT filename, uploaded_at FROM resumes WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->execute([$candidateId]);
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE candidate_id = ?");
    $stmt->execute([$candidateId]);
    $applicationCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error loading profile data'];
    header("Location: Dashboard_Candidate.php");
    exit;
}

$profilePhoto = !empty($_SESSION['user_photo']) 
    ? '/Public/Uploads/profile_photos/' . $_SESSION['user_photo']
    : '/Public/Assets/default-user.png';

$resumeUploadDate = $resume ? date('F j, Y', strtotime($resume['uploaded_at'])) : null;
$memberSince = date('F Y', strtotime($candidate['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($candidate['name']) ?> | MagLine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/candidate.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <style>
        :root {
            --dark-blue: #0a0a2a;
            --deep-indigo: #1a1a5a;
            --purple-glow: #121d7dff;
            --pink-glow: #d16aff;
            --blue-glow: #3b82f6;
            --text-light: #e0e7ff;
            --text-muted: #8a9eff;
            --glass-bg: rgba(10, 10, 42, 0.75);
            --glass-border: rgba(124, 77, 255, 0.25);
            --shadow-glow: 0 0 15px rgba(124, 77, 255, 0.45);
            --input-bg: rgba(10, 10, 42, 0.85);
            --input-border: rgba(124, 77, 255, 0.3);
            --input-focus: rgba(124, 77, 255, 0.25);
        }
        
        .skill-badge {
            background-color: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            display: inline-block;
            transition: transform 0.2s;
        }
        .skill-badge:hover {
            transform: scale(1.05);
        }
        .skills-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        .skill-category {
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 8px rgba(77, 98, 255, 0.41);
        }
        .skill-category:hover {
            border-color: var(--pink-glow);
            box-shadow: 0 0 15px rgba(106, 114, 255, 0.59);
        }
        .category-header {
            padding: 15px 20px;
            background: rgba(30, 55, 195, 0.57);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        .category-header:hover {
            background: rgba(42, 39, 229, 0.4);
        }
        .category-title {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-light);
        }
        .category-toggle {
            font-size: 1rem;
            color: var(--purple-glow);
            transition: transform 0.3s ease;
        }
        .category-header.active .category-toggle {
            transform: rotate(90deg);
        }
        .category-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
        }
        .category-content.active {
            max-height: 800px;
        }
        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            padding: 15px;
        }
        .skill-item {
            position: relative;
        }
        .skill-checkbox {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        .skill-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1.5px solid var(--input-border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        .skill-checkbox:checked + .skill-label {
            background: linear-gradient(135deg, rgba(30, 17, 125, 0.3), rgba(59, 130, 246, 0.3));
            border-color: var(--purple-glow);
            color: white;
            box-shadow: 0 0 10px rgba(22, 33, 80, 0.53);
        }
        .skill-label:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        .skill-checkmark {
            width: 16px;
            height: 16px;
            border: 1.5px solid var(--text-muted);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .skill-checkbox:checked + .skill-label .skill-checkmark {
            background: var(--purple-glow);
            border-color: var(--pink-glow);
        }
        .skill-checkmark::after {
            content: '✓';
            opacity: 0;
            color: white;
            font-weight: bold;
            font-size: 0.7rem;
            transition: opacity 0.3s ease;
        }
        .skill-checkbox:checked + .skill-label .skill-checkmark::after {
            opacity: 1;
        }
        .cv-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: var(--dark-card);
            border-radius: 0.5rem;
        }
        .pdf-icon {
            font-size: 2.5rem;
            color: #d32f2f;
            margin-right: 1rem;
        }
        .cv-meta {
            display: flex;
            align-items: center;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid var(--pink-glow);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .skill-badge.cv-skill {
    background: linear-gradient(135deg, #4ade80, #22c55e);
    color: white;
}

.skill-badge.manual-skill {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
}
.skill-badge.cv-skill {
    background: linear-gradient(135deg, #4ade80, #22c55e);
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.skill-badge.manual-skill {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.skill-badge i {
    font-size: 0.8rem;
}
    </style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/Candidate_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Candidate_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid py-4">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
                        <?= htmlspecialchars($_SESSION['flash_message']['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-header bg-dark-card rounded-3 p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <img src="<?= $profilePhoto ?>" alt="Profile Photo" class="profile-photo img-fluid rounded-circle"
                                 onerror="this.onerror=null;this.src='/Public/Assets/default-user.png'">
                        </div>
                        <div class="col-md-6">
                            <h1 class="mb-1"><?= htmlspecialchars($candidate['name']) ?></h1>
                            <p class="text-muted mb-2">Member since <?= $memberSince ?></p>
                            <?php if (!empty($candidate['location'])): ?>
                                <p class="mb-2"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($candidate['location']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#uploadCvModal">
                                <i class="bi bi-upload"></i> Upload CV
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="bi bi-pencil-square"></i> Edit Profile
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card bg-dark-card mb-4">
                            <div class="card-header bg-dark">
                                <h3 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i> About</h3>
                            </div>
                            <div class="card-body">
                                <?= $candidate['about'] ? nl2br(htmlspecialchars($candidate['about'])) : '<p class="text-muted">No information provided yet</p>' ?>
                            </div>
                        </div>
                        
                        <div class="card bg-dark-card mb-4">
                            <div class="card-header bg-dark">
                                <h3 class="mb-0"><i class="bi bi-tools me-2"></i> My Skills</h3>
                            </div>
                            <div class="card-body">
                               <?php if (!empty($currentSkills)): ?>
    <div class="d-flex flex-wrap mb-4">
        <?php foreach ($cvSkills as $skill): ?>
            <span class="skill-badge cv-skill">
                <i class="bi bi-file-earmark-text"></i>
                <?= htmlspecialchars($skill['name']) ?>
            </span>
        <?php endforeach; ?>
        <?php foreach ($manualSkills as $skill): ?>
            <span class="skill-badge manual-skill">
                <i class="bi bi-person-fill"></i>
                <?= htmlspecialchars($skill['name']) ?>
            </span>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="text-muted">No skills added yet</p>
<?php endif; ?>
                                
                                <form method="POST" action="">
                                    <div class="skills-container">
                                        <?php
                                        $categories = [
                                            'Programming Languages' => array_filter($allSkills, function($skill) { return $skill['id'] <= 13; }),
                                            'Web Development' => array_filter($allSkills, function($skill) { return $skill['id'] > 13 && $skill['id'] <= 26; }),
                                            'Data & AI' => array_filter($allSkills, function($skill) { return $skill['id'] > 26 && $skill['id'] <= 39; }),
                                            'Cloud & DevOps' => array_filter($allSkills, function($skill) { return $skill['id'] > 39 && $skill['id'] <= 52; }),
                                            'Mobile Development' => array_filter($allSkills, function($skill) { return $skill['id'] > 52 && $skill['id'] <= 59; }),
                                            'Cybersecurity' => array_filter($allSkills, function($skill) { return $skill['id'] > 59 && $skill['id'] <= 64; }),
                                            'Design & UX' => array_filter($allSkills, function($skill) { return $skill['id'] > 64 && $skill['id'] <= 70; }),
                                            'Project Management' => array_filter($allSkills, function($skill) { return $skill['id'] > 70 && $skill['id'] <= 75; })
                                        ];
                                        
                                        foreach ($categories as $category => $categorySkills): 
                                            if (empty($categorySkills)) continue;
                                        ?>
                                            <div class="skill-category">
                                                <div class="category-header" onclick="toggleCategory('<?= strtolower(str_replace(' ', '-', $category)) ?>')">
                                                    <div class="category-title"><?= $category ?></div>
                                                    <div class="category-toggle">
                                                        <i class="fas fa-chevron-right"></i>
                                                    </div>
                                                </div>
                                                <div class="category-content" id="category-<?= strtolower(str_replace(' ', '-', $category)) ?>">
                                                    <div class="skills-grid">
                                                        <?php foreach ($categorySkills as $skill): ?>
                                                            <div class="skill-item">
                                                                <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>" id="skill-<?= strtolower(str_replace(' ', '-', $skill['name'])) ?>" class="skill-checkbox" <?= in_array($skill['id'], $currentSkillIds) ? 'checked' : '' ?>>
                                                                <label for="skill-<?= strtolower(str_replace(' ', '-', $skill['name'])) ?>" class="skill-label">
                                                                    <div class="skill-checkmark"></div>
                                                                    <span><?= htmlspecialchars($skill['name']) ?></span>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="submit" name="update_skills" class="btn btn-primary w-100 mt-3">
                                        <i class="bi bi-save"></i> Update Skills
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card bg-dark-card mb-4">
                            <div class="card-header bg-dark">
                                <h3 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i> Curriculum Vitae</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($resume): ?>
                                    <div class="cv-display">
                                        <div class="cv-meta">
                                            <i class="bi bi-file-earmark-pdf-fill pdf-icon"></i>
                                            <div>
                                                <h5 class="cv-filename">Your Resume</h5>
                                                <p class="cv-upload-date">Uploaded: <?= $resumeUploadDate ?></p>
                                            </div>
                                        </div>
                                        <div class="cv-actions d-flex gap-2">
                                            <a href="/Public/Uploads/Cvs/<?= htmlspecialchars($resume['filename']) ?>" 
                                               class="btn btn-primary" download>
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                            <a href="/Public/Uploads/Cvs/<?= htmlspecialchars($resume['filename']) ?>" 
                                               target="_blank" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> Preview
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="bi bi-file-earmark-text fs-1 text-muted"></i>
                                        <p class="text-muted">No CV uploaded yet</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadCvModal">
                                            <i class="bi bi-upload"></i> Upload CV
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="stats-card mb-4">
                            <div class="stats-number"><?= $applicationCount ?></div>
                            <div class="text-muted">Applications</div>
                        </div>
                        
                        <div class="card bg-dark-card mb-4">
                            <div class="card-header bg-dark">
                                <h3 class="mb-0"><i class="bi bi-envelope me-2"></i> Contact</h3>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <?php if (!empty($candidate['email'])): ?>
                                       <li class="mb-3">
                                           <i class="bi bi-envelope-fill me-2 text-primary"></i>
                                           <?= htmlspecialchars($candidate['email']) ?>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (!empty($candidate['phone'])): ?>
                                        <li class="mb-3">
                                            <i class="bi bi-telephone-fill me-2 text-primary"></i>
                                            <?= htmlspecialchars($candidate['phone']) ?>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (!empty($candidate['linkedin'])): ?>
                                        <li class="mb-3">
                                            <i class="bi bi-linkedin me-2 text-primary"></i>
                                            <a href="<?= htmlspecialchars($candidate['linkedin']) ?>" target="_blank">
                                                LinkedIn Profile
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (!empty($candidate['location'])): ?>
                                        <li>
                                            <i class="bi bi-geo-alt-fill me-2 text-primary"></i>
                                            <?= htmlspecialchars($candidate['location']) ?>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark-card">
                <div class="modal-header border-dark">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?= htmlspecialchars($candidate['name']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?= htmlspecialchars($candidate['phone']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location" 
                                           value="<?= htmlspecialchars($candidate['location']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">LinkedIn</label>
                                    <input type="url" class="form-control" name="linkedin" 
                                           value="<?= htmlspecialchars($candidate['linkedin']) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">About You</label>
                            <textarea class="form-control" name="about" rows="4"><?= htmlspecialchars($candidate['about']) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-dark">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload CV Modal -->
    <div class="modal fade" id="uploadCvModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark-card">
                <div class="modal-header border-dark">
                    <h5 class="modal-title">Upload CV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data" id="cvForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="cv" class="form-label">Select PDF File</label>
                            <input type="file" class="form-control" name="cv" id="cv" accept="application/pdf" required>
                            <small class="text-muted">Max file size: 5MB</small>
                        </div>
                    </div>
                    <div class="modal-footer border-dark">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="cvSubmitBtn">Upload CV</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <?php include __DIR__ . '/Includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../Public/Assets/Js/main.js"></script>
    <script>
        function toggleCategory(categoryName) {
            const header = event.currentTarget;
            const content = document.getElementById(`category-${categoryName}`);
            
            header.classList.toggle('active');
            content.classList.toggle('active');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const categories = document.querySelectorAll('.category-content');
            categories.forEach(category => {
                category.classList.remove('active');
            });
        });

        document.getElementById('cvForm').addEventListener('submit', function(e) {
            const loadingOverlay = document.getElementById('loadingOverlay');
            const cvInput = document.getElementById('cv');
 
            if (cvInput.files.length === 0) {
                e.preventDefault();
                return;
            }

            loadingOverlay.classList.add('active');
  
            document.getElementById('cvSubmitBtn').disabled = true;
        });

        document.getElementById('uploadCvModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('loadingOverlay').classList.remove('active');
            document.getElementById('cvSubmitBtn').disabled = false;
        });
    </script>
</body>
</html>
