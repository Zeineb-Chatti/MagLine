<?php
session_start();
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../App/Helpers/Haversine.php';

// Strict session validation
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'candidate') {
    header("Location: Login.php");
    exit;
}

// Get job ID from URL
$jobId = $_GET['id'] ?? null;
if (!$jobId) {
    header("Location: jobs.php");
    exit;
}

// Matching configuration
const MAX_DISTANCE_KM = 50;
const SKILLS_WEIGHT = 0.7;
const GEO_WEIGHT = 0.3;

// Initialize variables
$jobDetails = [];
$hasApplied = false;
$applicationStatus = null;
$jobSkillsList = [];
$candidateSkills = [];
$matchPercentage = 0;
$skillsScore = 0;
$geoScore = 0;
$distanceKm = null;
$locationLabel = 'Unknown';
$hasLocationData = false;
$error_message = '';
$debugInfo = []; // For debugging

try {
    // Get job details and company info with location data
    $stmt = $pdo->prepare(
        "SELECT o.*, u.company_name, u.about_company, u.company_website, u.company_logo,
                o.latitude as job_latitude, o.longitude as job_longitude, u.location as company_location
         FROM offers o
         JOIN users u ON o.recruiter_id = u.id
         WHERE o.id = ? AND o.deleted_at IS NULL"
    );
    $stmt->execute([$jobId]);
    $jobDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$jobDetails) {
        throw new Exception("Job not found or no longer available");
    }

    // Debug job coordinates
    $debugInfo['job_lat_raw'] = $jobDetails['job_latitude'];
    $debugInfo['job_lon_raw'] = $jobDetails['job_longitude'];
    $debugInfo['job_lat_type'] = gettype($jobDetails['job_latitude']);
    $debugInfo['job_lon_type'] = gettype($jobDetails['job_longitude']);
    $debugInfo['job_lat_is_numeric'] = is_numeric($jobDetails['job_latitude']);
    $debugInfo['job_lon_is_numeric'] = is_numeric($jobDetails['job_longitude']);

    // Get candidate profile with skills and location
    $stmt = $pdo->prepare(
        "SELECT u.latitude, u.longitude, 
         GROUP_CONCAT(s.name) AS skills 
         FROM users u
         LEFT JOIN user_skills us ON u.id = us.user_id
         LEFT JOIN skills s ON us.skill_id = s.id
         WHERE u.id = ?
         GROUP BY u.id"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];

    // Extract candidate data with proper type conversion
    $candidateSkills = !empty($candidate['skills']) ? explode(',', $candidate['skills']) : [];
    $candidateLat = null;
    $candidateLon = null;

    // More robust coordinate extraction
    if (isset($candidate['latitude']) && $candidate['latitude'] !== null && $candidate['latitude'] !== '') {
        if (is_numeric($candidate['latitude'])) {
            $candidateLat = (float)$candidate['latitude'];
        }
    }
    
    if (isset($candidate['longitude']) && $candidate['longitude'] !== null && $candidate['longitude'] !== '') {
        if (is_numeric($candidate['longitude'])) {
            $candidateLon = (float)$candidate['longitude'];
        }
    }

    // Debug candidate coordinates
    $debugInfo['candidate_lat_raw'] = $candidate['latitude'] ?? 'NULL';
    $debugInfo['candidate_lon_raw'] = $candidate['longitude'] ?? 'NULL';
    $debugInfo['candidate_lat_final'] = $candidateLat;
    $debugInfo['candidate_lon_final'] = $candidateLon;
    $debugInfo['candidate_lat_type'] = gettype($candidate['latitude'] ?? null);
    $debugInfo['candidate_lon_type'] = gettype($candidate['longitude'] ?? null);

    // Check if candidate has applied and get status
    $stmt = $pdo->prepare("SELECT id, status FROM applications WHERE candidate_id = ? AND offer_id = ?");
    $stmt->execute([$_SESSION['user_id'], $jobId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasApplied = $application !== false;
    $applicationStatus = $hasApplied ? strtolower($application['status']) : null;

    // Get required skills for this job
    $stmt = $pdo->prepare(
        "SELECT s.name FROM offer_skills os
         JOIN skills s ON os.skill_id = s.id
         WHERE os.offer_id = ?"
    );
    $stmt->execute([$jobId]);
    $jobSkillsList = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Calculate matching scores
    $commonSkills = count(array_intersect(
        array_map('strtolower', $jobSkillsList),
        array_map('strtolower', $candidateSkills)
    ));
    $skillsScore = !empty($jobSkillsList) ? round(($commonSkills / count($jobSkillsList)) * 100) : 0;

    // Extract job coordinates with proper type conversion
    $jobLat = null;
    $jobLon = null;
    
    if (isset($jobDetails['job_latitude']) && $jobDetails['job_latitude'] !== null && $jobDetails['job_latitude'] !== '') {
        if (is_numeric($jobDetails['job_latitude'])) {
            $jobLat = (float)$jobDetails['job_latitude'];
        }
    }
    
    if (isset($jobDetails['job_longitude']) && $jobDetails['job_longitude'] !== null && $jobDetails['job_longitude'] !== '') {
        if (is_numeric($jobDetails['job_longitude'])) {
            $jobLon = (float)$jobDetails['job_longitude'];
        }
    }

    $debugInfo['job_lat_final'] = $jobLat;
    $debugInfo['job_lon_final'] = $jobLon;

    // Calculate geographic information if ALL coordinates exist and are valid
    $debugInfo['all_coords_valid'] = ($candidateLat !== null && $candidateLon !== null && $jobLat !== null && $jobLon !== null);
    
    if ($candidateLat !== null && $candidateLon !== null && $jobLat !== null && $jobLon !== null) {
        $hasLocationData = true;
        $debugInfo['attempting_haversine'] = true;
        
        try {
            $distanceKm = \App\Helpers\haversine(
                [$candidateLat, $candidateLon],
                [$jobLat, $jobLon]
            )->km;
            
            $geoScore = max(0, round(100 - ($distanceKm / MAX_DISTANCE_KM * 100)));
            $locationLabel = ($distanceKm <= MAX_DISTANCE_KM) ? 'Nearby' : 'Far';
            
            $debugInfo['haversine_success'] = true;
            $debugInfo['distance_calculated'] = $distanceKm;
            $debugInfo['geo_score_calculated'] = $geoScore;
            
        } catch (Exception $e) {
            error_log("Haversine calculation error: " . $e->getMessage());
            $hasLocationData = false;
            $distanceKm = null;
            $geoScore = 0;
            $debugInfo['haversine_error'] = $e->getMessage();
        }
    } else {
        $debugInfo['missing_coordinates'] = [
            'candidate_lat' => $candidateLat === null ? 'NULL' : 'OK',
            'candidate_lon' => $candidateLon === null ? 'NULL' : 'OK',
            'job_lat' => $jobLat === null ? 'NULL' : 'OK', 
            'job_lon' => $jobLon === null ? 'NULL' : 'OK'
        ];
    }

    // Combined score calculation
    if ($hasLocationData) {
        $matchPercentage = round(($skillsScore * SKILLS_WEIGHT) + ($geoScore * GEO_WEIGHT));
    } else {
        $matchPercentage = $skillsScore;
    }

    $debugInfo['final_results'] = [
        'hasLocationData' => $hasLocationData,
        'distanceKm' => $distanceKm,
        'geoScore' => $geoScore,
        'skillsScore' => $skillsScore,
        'matchPercentage' => $matchPercentage
    ];

} catch (Exception $e) {
    error_log("Job detail error: " . $e->getMessage());
    $error_message = "System error. Please try again later.";
    $debugInfo['main_error'] = $e->getMessage();
}

$displayName = $_SESSION['user_name'] ?? 'Candidate';
$companyLogoPath = !empty($jobDetails['company_logo']) 
    ? '../Public/Uploads/Company_Logos/' . basename($jobDetails['company_logo']) 
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($jobDetails['title'] ?? 'Job Details') ?> | DK Soft</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/candidate.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <style>
        :root {
            --space-dark: #0a0a1a;
            --space-darker: #070712;
            --space-navy: #12122d;
            --space-purple: #6e3bdc;
            --space-blue: #3a3aff;
            --space-pink: #ff2d78;
            --space-cyan: #00f0ff;
            --space-light: #e0e0ff;
            --space-lighter: #f5f5ff;
            --radius-xl: 16px;
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 4px;
            --shadow-deep: 0 10px 30px -5px rgba(0, 0, 0, 0.3);
            --shadow-light: 0 5px 15px -3px rgba(0, 0, 0, 0.1);
            --glow-purple: 0 0 20px rgba(110, 59, 220, 0.3);
            --glow-blue: 0 0 20px rgba(58, 58, 255, 0.3);
            --transition: all 0.2s ease;
            --gradient-space: linear-gradient(135deg, var(--space-navy) 0%, var(--space-darker) 100%);
            --gradient-accent: linear-gradient(135deg, var(--space-blue) 0%, var(--space-purple) 100%);
            --gradient-score: linear-gradient(90deg, var(--space-pink) 0%, var(--space-purple) 50%, var(--space-blue) 100%);
            --success-green: #4CAF50;
            --success-dark-green: #2E7D32;
            --warning-orange: #FF9800;
            --error-red: #F44336;
        }

        /* DEBUG PANEL */
        .debug-panel {
            background: rgba(255, 0, 0, 0.1);
            border: 2px solid #ff0000;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 12px;
            color: #fff;
            max-height: 300px;
            overflow-y: auto;
        }

        .debug-panel h4 {
            color: #ff6b6b;
            margin-bottom: 0.5rem;
        }

        .debug-panel pre {
            color: #fff;
            margin: 0;
            white-space: pre-wrap;
        }

        .job-detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .job-header-card {
            background: rgba(18, 18, 45, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(110, 59, 220, 0.2);
            box-shadow: var(--shadow-deep);
            position: relative;
        }

        .job-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .company-name {
            color: var(--space-cyan);
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }

        .match-badge {
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--glow-purple);
            position: absolute;
            top: 2rem;
            right: 2rem;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: var(--space-light);
        }

        .meta-item i {
            color: var(--space-purple);
            font-size: 1.1rem;
        }

        .company-logo-container {
            width: 100px;
            height: 100px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            background-color: rgba(10, 10, 26, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }

        .company-logo {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .company-logo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 2rem;
            color: var(--space-purple);
        }

        .job-content-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        @media (min-width: 992px) {
            .job-content-container {
                grid-template-columns: 2fr 1fr;
            }
        }

        .job-description-card, .job-sidebar-card {
            background: rgba(18, 18, 45, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-deep);
            border: 1px solid rgba(110, 59, 220, 0.1);
            margin-bottom: 2rem;
        }

        .info-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .info-title i {
            color: var(--space-purple);
        }

        .description-content {
            color: var(--space-light);
            line-height: 1.7;
        }

        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .skill-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .skill-matched {
            background: rgba(0, 240, 255, 0.1);
            color: var(--space-cyan);
            border: 1px solid rgba(0, 240, 255, 0.2);
        }

        .skill-unmatched {
            background: rgba(255, 255, 255, 0.05);
            color: var(--space-light);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .job-details-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .job-details-list li {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
            align-items: flex-start;
        }

        .job-details-list li i {
            color: var(--space-purple);
            font-size: 1.1rem;
            margin-top: 0.2rem;
        }

        .job-details-list li div {
            flex: 1;
        }

        .job-details-list li span {
            display: block;
            font-size: 0.85rem;
            color: rgba(224, 224, 255, 0.7);
            margin-bottom: 0.25rem;
        }

        .job-details-list li strong {
            font-weight: 600;
            color: white;
            display: block;
        }

        .location-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.5rem;
        }

        .location-nearby {
            background: rgba(0, 240, 255, 0.1);
            color: var(--space-cyan);
            border: 1px solid rgba(0, 240, 255, 0.2);
        }

        .location-far {
            background: rgba(255, 45, 120, 0.1);
            color: var(--space-pink);
            border: 1px solid rgba(255, 45, 120, 0.2);
        }

        .score-visual {
            margin-bottom: 1.5rem;
        }

        .score-bars {
            display: flex;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .skills-bar {
            height: 100%;
        }

        .geo-bar {
            height: 100%;
        }

        .score-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: rgba(224, 224, 255, 0.7);
        }

        .score-value {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .score-value::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .score-value:nth-child(1)::before {
            background: var(--space-pink);
        }

        .score-value:nth-child(2)::before {
            background: var(--space-blue);
        }

        .job-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn-view {
            background: rgba(255, 255, 255, 0.05);
            color: var(--space-light);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-view:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-apply {
            background: var(--gradient-accent);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 700;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--glow-purple);
        }

        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(110, 59, 220, 0.4);
        }

        .btn-applied {
            background: linear-gradient(135deg, var(--warning-orange) 0%, #e65100 100%);
            color: white;
            pointer-events: none;
            box-shadow: 0 0 15px rgba(255, 152, 0, 0.3);
        }

        .btn-rejected {
            background: linear-gradient(135deg, var(--error-red) 0%, #c62828 100%);
            color: white;
            pointer-events: none;
            box-shadow: 0 0 15px rgba(244, 67, 54, 0.3);
        }

        .btn-shortlisted {
            background: linear-gradient(135deg, var(--space-blue) 0%, var(--space-purple) 100%);
            color: white;
            pointer-events: none;
            box-shadow: 0 0 15px rgba(58, 58, 255, 0.3);
        }

        .btn-approved {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            color: white;
            pointer-events: none;
            box-shadow: 0 0 15px rgba(76, 175, 80, 0.3);
        }

        .btn-message {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-message:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .company-details {
            color: var(--space-light);
            line-height: 1.7;
        }

        .company-details a {
            color: var(--space-cyan);
            text-decoration: none;
            transition: var(--transition);
        }

        .company-details a:hover {
            color: var(--space-blue);
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .job-header-card {
                padding: 1.5rem;
            }
            
            .job-title {
                font-size: 1.5rem;
            }
            
            .match-badge {
                position: static;
                margin-bottom: 1rem;
            }
            
            .company-logo-container {
                width: 80px;
                height: 80px;
                margin-right: 1rem;
            }
            
            .job-meta {
                gap: 1rem;
            }
            
            .job-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .job-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Work Type Badge Styles */
.work-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.work-type-badge.work-on-site {
    color: var(--space-blue);
    border-color: rgba(58, 58, 255, 0.3);
    background: rgba(58, 58, 255, 0.1);
}

.work-type-badge.work-remote {
    color: var(--space-cyan);
    border-color: rgba(0, 240, 255, 0.3);
    background: rgba(0, 240, 255, 0.1);
}

.work-type-badge.work-hybrid {
    color: var(--space-purple);
    border-color: rgba(110, 59, 220, 0.3);
    background: rgba(110, 59, 220, 0.1);
}

.work-type-badge i {
    font-size: 1rem;
}
    </style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/Candidate_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Candidate_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="job-detail-container">
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <?php if (isset($jobDetails['title'])): ?>
                <div class="job-header-card">
                    <?php 
                        // Determine colors based on scores
                        $skillsColor = $skillsScore >= 70 ? 'var(--space-cyan)' : 
                                      ($skillsScore >= 40 ? 'var(--space-blue)' : 'var(--space-pink)');
                        $geoColor = $hasLocationData ? ($geoScore >= 70 ? 'var(--space-cyan)' : 
                                   ($geoScore >= 40 ? 'var(--space-blue)' : 'var(--space-pink)')) : 'transparent';
                        $totalColor = $matchPercentage >= 70 ? 'linear-gradient(135deg, var(--space-cyan) 0%, var(--space-blue) 100%)' : 
                                     ($matchPercentage >= 40 ? 'linear-gradient(135deg, var(--space-blue) 0%, var(--space-purple) 100%)' : 
                                     'linear-gradient(135deg, var(--space-pink) 0%, var(--space-purple) 100%)');
                        $totalGlow = $matchPercentage >= 70 ? '0 0 10px rgba(0, 240, 255, 0.3)' : 
                                    ($matchPercentage >= 40 ? '0 0 10px rgba(58, 58, 255, 0.3)' : 
                                    '0 0 10px rgba(255, 45, 120, 0.3)');
                    ?>
                    
                    <span class="match-badge" style="background: <?= $totalColor ?>; box-shadow: <?= $totalGlow ?>;">
                        <i class="bi bi-stars"></i> <?= $matchPercentage ?>% Match
                    </span>
                    
                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center mb-4">
                        <div class="company-logo-container">
                            <?php if ($companyLogoPath && file_exists($companyLogoPath)): ?>
                                <img src="<?= htmlspecialchars($companyLogoPath) ?>" 
                                     alt="<?= htmlspecialchars($jobDetails['company_name']) ?> Logo" 
                                     class="company-logo">
                            <?php else: ?>
                                <div class="company-logo-placeholder">
                                    <?= !empty($jobDetails['company_name']) ? strtoupper(substr($jobDetails['company_name'], 0, 1)) : 'C' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="d-flex align-items-center flex-wrap gap-2">
    <h1 class="job-title"><?= htmlspecialchars($jobDetails['title']) ?></h1>
    <?php 
    $workType = $jobDetails['work_type'] ?? 'on-site';
    $workTypeConfig = [
        'on-site' => ['label' => 'On-site', 'icon' => 'bi-building', 'class' => 'work-on-site'],
        'remote' => ['label' => 'Remote', 'icon' => 'bi-house', 'class' => 'work-remote'],
        'hybrid' => ['label' => 'Hybrid', 'icon' => 'bi-laptop', 'class' => 'work-hybrid']
    ];
    $workTypeInfo = $workTypeConfig[$workType] ?? $workTypeConfig['on-site'];
    ?>
    <span class="work-type-badge <?= $workTypeInfo['class'] ?>">
        <i class="bi <?= $workTypeInfo['icon'] ?>"></i>
        <?= $workTypeInfo['label'] ?>
    </span>
</div>
                            <p class="company-name">
                                <i class="bi bi-building"></i> <?= htmlspecialchars($jobDetails['company_name']) ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="job-meta">
                        <span class="meta-item">
                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($jobDetails['location']) ?>
                        </span>
                        <span class="meta-item">
                            <i class="bi bi-clock"></i> <?= htmlspecialchars($jobDetails['employment_type']) ?>
                        </span>
                        <span class="meta-item">
                            <i class="bi bi-calendar"></i> Posted <?= date('M j, Y', strtotime($jobDetails['created_at'])) ?>
                        </span>
                        <?php if ($hasLocationData && $distanceKm !== null): ?>
                            <span class="meta-item">
                                <i class="bi bi-signpost"></i> <?= round($distanceKm) ?> km <?= $locationLabel === 'Nearby' ? 'from you' : 'away' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="score-visual">
                        <div class="score-bars">
                            <div class="skills-bar" style="width: <?= $skillsScore ?>%; background: <?= $skillsColor ?>"></div>
                            <?php if ($hasLocationData): ?>
                                <div class="geo-bar" style="width: <?= $geoScore ?>%; background: <?= $geoColor ?>"></div>
                            <?php endif; ?>
                        </div>
                        <div class="score-labels">
                            <span class="score-value">Skills: <?= $skillsScore ?>%</span>
                            <span class="score-value">
                                <?php if ($hasLocationData && $distanceKm !== null): ?>
                                    Location: <?= round($geoScore) ?>% (<?= round($distanceKm) ?> km)
                                <?php else: ?>
                                    Location: Data unavailable
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="job-actions">
                        <a href="jobs.php" class="btn-view">
                            <i class="bi bi-arrow-left"></i> Back to Jobs
                        </a>
                        
                        <?php if ($hasApplied): ?>
                            <?php
                                // Determine button style based on status
                                $statusClass = 'btn-applied';
                                $statusIcon = 'bi-hourglass';
                                $statusText = 'Pending';
                                $isInterview = false;
                                
                                switch(strtolower($applicationStatus)) {
                                    case 'approved':
                                        $statusClass = 'btn-approved';
                                        $statusIcon = 'bi-check-circle';
                                        $statusText = 'Approved';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'btn-rejected';
                                        $statusIcon = 'bi-x-circle';
                                        $statusText = 'Rejected';
                                        break;
                                    case 'shortlisted':
                                    case 'interview':
                                        $statusClass = 'btn-shortlisted';
                                        $statusIcon = 'bi-calendar-check';
                                        $statusText = 'Interview';
                                        $isInterview = true;
                                        break;
                                    default:
                                        $statusClass = 'btn-applied';
                                        $statusIcon = 'bi-hourglass';
                                        $statusText = 'Pending';
                                }
                            ?>
                            
                            <?php if ($isInterview): ?>
                                <a href="interview_details.php?job_id=<?= $jobId ?>" class="btn-apply <?= $statusClass ?>">
                                    <i class="bi <?= $statusIcon ?>"></i> <?= $statusText ?>
                                </a>
                            <?php else: ?>
                                <button class="btn-apply <?= $statusClass ?>" disabled>
                                    <i class="bi <?= $statusIcon ?>"></i> <?= $statusText ?>
                                </button>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <button class="btn-apply" data-apply-job data-job-id="<?= $jobId ?>">
                                <i class="bi bi-send"></i> Apply Now
                            </button>
                        <?php endif; ?>
                        
                        <a href="chat.php?user_id=<?= $jobDetails['recruiter_id'] ?>" 
                           class="btn-apply btn-message">
                            <i class="bi bi-chat-left-text"></i> Contact Recruiter
                        </a>
                    </div>
                </div>
                
                <div class="job-content-container">
                    <div class="job-description-card">
                        <h3 class="info-title">
                            <i class="bi bi-file-text"></i> Job Description
                        </h3>
                        <div class="description-content">
                            <?= nl2br(htmlspecialchars($jobDetails['description'])) ?>
                        </div>
                        
                        <?php if (!empty($jobSkillsList)): ?>
                        <h3 class="info-title mt-4">
                            <i class="bi bi-tools"></i> Required Skills
                        </h3>
                        <div class="skills-container">
                            <?php foreach ($jobSkillsList as $skill): 
                                $isMatched = in_array(strtolower($skill), array_map('strtolower', $candidateSkills));
                            ?>
                                <span class="skill-badge <?= $isMatched ? 'skill-matched' : 'skill-unmatched' ?>">
                                    <?= htmlspecialchars($skill) ?>
                                    <?php if ($isMatched): ?>
                                        <i class="bi bi-check-circle-fill"></i>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="job-sidebar-card">
                        <h3 class="info-title">
                            <i class="bi bi-building"></i> About the Company
                        </h3>
                        <div class="company-details">
                            <?= !empty($jobDetails['about_company']) ? htmlspecialchars($jobDetails['about_company']) : 'No company description provided.' ?>
                            
                            <?php if (!empty($jobDetails['company_website'])): ?>
                                <p>
                                    <i class="bi bi-globe"></i> 
                                    <a href="<?= htmlspecialchars($jobDetails['company_website']) ?>" target="_blank">
                                        Visit website
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <h3 class="info-title mt-4">
                            <i class="bi bi-info-circle"></i> Job Details
                        </h3>
                        <ul class="job-details-list">
                            <li>
                                <i class="bi bi-calendar"></i>
                                <div>
                                    <span>Posted Date</span>
                                    <strong><?= date('M j, Y', strtotime($jobDetails['created_at'])) ?></strong>
                                </div>
                            </li>
                            <li>
                                <i class="bi bi-clock"></i>
                                <div>
                                    <span>Job Type</span>
                                    <strong><?= htmlspecialchars($jobDetails['employment_type']) ?></strong>
                                </div>
                            </li>
                            <li>
    <i class="bi bi-geo-alt"></i>
    <div>
        <span>Location</span>
        <strong><?= htmlspecialchars($jobDetails['location']) ?></strong>
    </div>
</li>
                        </ul>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-danger">
                    Job not found or no longer available.
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Toast notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <?php include __DIR__ . '/Includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply button functionality
            const applyButtons = document.querySelectorAll('[data-apply-job]');
            
            applyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const jobId = this.getAttribute('data-job-id');
                    const originalText = this.innerHTML;
                    
                    this.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Applying...';
                    this.disabled = true;
                    
                    fetch('apply_job.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ job_id: jobId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.add('btn-applied');
                            this.innerHTML = '<i class="bi bi-check-circle"></i> Applied';
                            showToast('Application submitted successfully!', 'success');
                        } else {
                            this.innerHTML = originalText;
                            this.disabled = false;
                            showToast(data.message || 'Error submitting application', 'error');
                        }
                    })
                    .catch(error => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        showToast('Network error. Please try again.', 'error');
                    });
                });
            });
            
            function showToast(message, type) {
                const toastContainer = document.querySelector('.toast-container');
                const toastEl = document.createElement('div');
                toastEl.className = `toast show align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
                toastEl.setAttribute('role', 'alert');
                toastEl.setAttribute('aria-live', 'assertive');
                toastEl.setAttribute('aria-atomic', 'true');
                toastEl.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                toastContainer.appendChild(toastEl);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    toastEl.classList.remove('show');
                    setTimeout(() => toastEl.remove(), 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>