<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../App/Helpers/notification_functions.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * Job Offer Processing Script
 * Handles job offer creation with geocoding, skill extraction, and candidate notifications
 */

// Security: Verify user authentication and authorization
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}

// Get current user information
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Configuration constants
const VALID_EMPLOYMENT_TYPES = ['Full-time', 'Part-time', 'Contract'];
const VALID_WORK_TYPES = ['on-site', 'remote', 'hybrid'];
const GEOCODING_TIMEOUT = 5;
const FLASK_API_TIMEOUT = 10;
const MIN_SKILL_LENGTH = 2;

// Initialize form data and errors
$errors = [];
$formData = [
    'title' => '',
    'description' => '',
    'location' => '',
    'employment_type' => '',
    'work_type' => 'on-site',
    'skills' => []
];

/**
 * Geocode location using OpenStreetMap Nominatim API
 * @param string $location The location to geocode
 * @return array|null Returns [lat, lon] or null if geocoding fails
 */
function geocodeLocation($location) {
    $geocodeUrl = "https://nominatim.openstreetmap.org/search?format=json&q=" . 
                 urlencode($location) . "&limit=1";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $geocodeUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'JobPlatform/1.0 (contact@yourdomain.com)',
        CURLOPT_TIMEOUT => GEOCODING_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Geocoding cURL error: {$curlError}");
        return null;
    }

    if ($httpCode !== 200) {
        error_log("Geocoding API error: HTTP {$httpCode}");
        return null;
    }

    $geoData = json_decode($response, true);
    if (empty($geoData) || !isset($geoData[0]['lat']) || !isset($geoData[0]['lon'])) {
        error_log("Geocoding failed - no coordinates found for: {$location}");
        return null;
    }

    $latitude = (float)$geoData[0]['lat'];
    $longitude = (float)$geoData[0]['lon'];
    error_log("Geocoding success: {$location} => {$latitude},{$longitude}");
    
    return [$latitude, $longitude];
}

/**
 * Extract skills from job description using Flask API
 * @param array $jobData Job data including description, location, work_type, coordinates
 * @return array Array of extracted skill names
 */
function extractSkillsFromAPI($jobData) {
    $flaskApiUrl = 'http://localhost:5001/extract_skills_from_text';
    $postData = json_encode([
        'text' => $jobData['description'],
        'is_job_description' => true,
        'location' => $jobData['location'],
        'work_type' => $jobData['work_type'],
        'latitude' => $jobData['latitude'],
        'longitude' => $jobData['longitude']
    ]);

    $ch = curl_init($flaskApiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_TIMEOUT => FLASK_API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Flask API cURL error: {$curlError}");
        return [];
    }

    if ($httpCode !== 200) {
        error_log("Flask API error: HTTP {$httpCode}");
        return [];
    }

    $json = json_decode($response, true);
    if (!$json || empty($json['skills']) || !is_array($json['skills'])) {
        error_log("Flask API returned no valid skills");
        return [];
    }

    return $json['skills'];
}

/**
 * Process and store skills for a job offer
 * @param PDO $pdo Database connection
 * @param int $offerId Job offer ID
 * @param array $skillNames Array of skill names to process
 * @return int Number of skills successfully processed
 */
function processSkills($pdo, $offerId, $skillNames) {
    $processedCount = 0;
    
    if (empty($skillNames)) {
        return $processedCount;
    }

    $stmtSelectSkill = $pdo->prepare("SELECT id FROM skills WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
    $stmtInsertSkill = $pdo->prepare("INSERT INTO skills (name, created_at) VALUES (?, NOW())");
    $stmtLinkSkill = $pdo->prepare("INSERT IGNORE INTO offer_skills (offer_id, skill_id) VALUES (?, ?)");
    
    foreach ($skillNames as $skillName) {
        $skillNameClean = trim($skillName);
        
        // Validate skill name
        if (empty($skillNameClean) || 
            is_numeric($skillNameClean) || 
            strlen($skillNameClean) < MIN_SKILL_LENGTH) {
            continue;
        }
        
        // Check if skill exists
        $stmtSelectSkill->execute([$skillNameClean]);
        $skillId = $stmtSelectSkill->fetchColumn();
        
        // Create new skill if it doesn't exist
        if (!$skillId) {
            try {
                $stmtInsertSkill->execute([$skillNameClean]);
                $skillId = $pdo->lastInsertId();
                error_log("Created new skill: {$skillNameClean} (ID: {$skillId})");
            } catch (PDOException $e) {
                error_log("Failed to create skill '{$skillNameClean}': " . $e->getMessage());
                continue;
            }
        }
        
        // Link skill to offer
        try {
            $stmtLinkSkill->execute([$offerId, $skillId]);
            $processedCount++;
        } catch (PDOException $e) {
            error_log("Failed to link skill {$skillId} to offer {$offerId}: " . $e->getMessage());
        }
    }
    
    return $processedCount;
}

/**
 * Send notifications to all candidates about new job offer
 * @param PDO $pdo Database connection
 * @param array $jobData Job offer data
 * @param int $offerId Job offer ID
 * @return int Number of notifications sent
 */
function notifyCandidates($pdo, $jobData, $offerId) {
    $notificationCount = 0;
    
    try {
        // Get all candidates
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = ? AND is_active = 1");
        $stmt->execute(['candidate']);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($candidates) . " active candidates to notify");
        
        if (empty($candidates)) {
            error_log("No active candidates found for notifications");
            return 0;
        }

        // Prepare notification message
        $workTypeEmoji = match($jobData['work_type']) {
            'remote' => '🏠',
            'hybrid' => '🔄',
            'on-site' => '🏢',
            default => '💼'
        };
        
        $workTypeLabel = ucfirst($jobData['work_type']);
        $message = "{$workTypeEmoji} New {$workTypeLabel} job: " . 
                  htmlspecialchars($jobData['title']) . " - " . 
                  htmlspecialchars($jobData['location']);
        
        // Send notifications
        foreach ($candidates as $candidate) {
            $notificationSent = addNotification(
                $pdo,
                $candidate['id'],
                $message,
                'system',
                $offerId
            );
            
            if ($notificationSent) {
                $notificationCount++;
                error_log("Notification sent to candidate: {$candidate['name']} (ID: {$candidate['id']})");
            } else {
                error_log("Failed to notify candidate: {$candidate['name']} (ID: {$candidate['id']})");
            }
        }
        
    } catch (Exception $e) {
        error_log("Error in candidate notifications: " . $e->getMessage());
    }
    
    return $notificationCount;
}

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and collect form data
    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['location'] = trim($_POST['location'] ?? '');
    $formData['employment_type'] = trim($_POST['employment_type'] ?? '');
    $formData['work_type'] = trim($_POST['work_type'] ?? 'on-site');
    $formData['skills'] = array_filter(array_map('intval', $_POST['skills'] ?? []));

    // Validate required fields
    if (empty($formData['title'])) {
        $errors['title'] = 'Job title is required.';
    } elseif (strlen($formData['title']) > 255) {
        $errors['title'] = 'Job title must be less than 255 characters.';
    }

    if (empty($formData['description'])) {
        $errors['description'] = 'Job description is required.';
    } elseif (strlen($formData['description']) < 50) {
        $errors['description'] = 'Job description must be at least 50 characters.';
    }

    if (empty($formData['location'])) {
        $errors['location'] = 'Job location is required.';
    }

    if (empty($formData['employment_type']) || !in_array($formData['employment_type'], VALID_EMPLOYMENT_TYPES)) {
        $errors['employment_type'] = 'Please select a valid employment type.';
    }

    if (empty($formData['work_type']) || !in_array($formData['work_type'], VALID_WORK_TYPES)) {
        $errors['work_type'] = 'Please select a valid work type.';
    }

    if (empty($formData['skills'])) {
        $errors['skills'] = 'Please select at least one skill.';
    }

    // Proceed only if validation passes
    if (empty($errors)) {
        try {
            // Determine if geocoding is needed
            $latitude = null;
            $longitude = null;
            $shouldGeocode = false;

            // Geocoding logic: Only skip for truly remote jobs
            if (!empty($formData['location']) && strtolower($formData['location']) !== 'remote') {
                switch ($formData['work_type']) {
                    case 'on-site':
                    case 'hybrid':
                        $shouldGeocode = true; // Both need physical location coordinates
                        break;
                    case 'remote':
                        $shouldGeocode = false; // Pure remote doesn't need coordinates
                        break;
                    default:
                        $shouldGeocode = true; // Default to geocoding for safety
                }
            }

            // Perform geocoding if needed
            if ($shouldGeocode) {
                $coordinates = geocodeLocation($formData['location']);
                if ($coordinates) {
                    [$latitude, $longitude] = $coordinates;
                }
            }

            // Begin database transaction
            $pdo->beginTransaction();

            // Insert job offer
            $stmt = $pdo->prepare("
                INSERT INTO offers 
                (recruiter_id, title, description, location, employment_type, work_type, latitude, longitude, created_at) 
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $formData['title'],
                $formData['description'],
                $formData['location'],
                $formData['employment_type'],
                $formData['work_type'],
                $latitude,
                $longitude
            ]);

            $offerId = $pdo->lastInsertId();
            error_log("Job offer created successfully with ID: {$offerId}");

            // Process manually selected skills
            $manualSkillNames = [];
            if (!empty($formData['skills'])) {
                $skillIds = implode(',', array_map('intval', $formData['skills']));
                $stmt = $pdo->prepare("SELECT name FROM skills WHERE id IN ({$skillIds})");
                $stmt->execute();
                $manualSkillNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            $manualSkillsCount = processSkills($pdo, $offerId, $manualSkillNames);

            // Extract skills from job description using AI
            $jobDataForAI = [
                'description' => $formData['description'],
                'location' => $formData['location'],
                'work_type' => $formData['work_type'],
                'latitude' => $latitude,
                'longitude' => $longitude
            ];

            $extractedSkills = extractSkillsFromAPI($jobDataForAI);
            $extractedSkillsCount = processSkills($pdo, $offerId, $extractedSkills);

            // Commit the main transaction
            $pdo->commit();
            error_log("Job offer transaction committed successfully");

            // Send notifications (separate from main transaction)
            $notificationCount = notifyCandidates($pdo, $formData, $offerId);

            // Prepare success message
            $totalSkills = $manualSkillsCount + $extractedSkillsCount;
            $workTypeLabel = ucfirst($formData['work_type']);
            
            $successMessage = "Job offer posted successfully as {$workTypeLabel} position";
            
            if ($totalSkills > 0) {
                $successMessage .= " with {$totalSkills} skills";
                if ($extractedSkillsCount > 0) {
                    $successMessage .= " ({$extractedSkillsCount} AI-extracted)";
                }
            }
            
            if ($notificationCount > 0) {
                $successMessage .= ". Notified {$notificationCount} candidates";
            }

            if ($shouldGeocode && $latitude && $longitude) {
                $successMessage .= ". Location geocoded successfully";
            }

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => $successMessage
            ];

            header("Location: post_offer.php");
            exit;

        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log("Critical error in job offer creation: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $errors['general'] = "Failed to create job offer. Please try again.";
        }
    }

    // Store errors and form data for redisplay
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $formData;
        header("Location: post_offer.php");
        exit;
    }
}

// Redirect if accessed directly without POST
header("Location: post_offer.php");
exit;
?>