<?php
session_start();
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../App/Helpers/Haversine.php';

// Strict session validation
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'candidate') {
    header("Location: Login.php");
    exit;
}

$candidateId = (int)$_SESSION['user_id'];

// Initialize pagination and filters
$search = htmlspecialchars(trim($_GET['search'] ?? ''));
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Matching algorithm configuration
const MAX_DISTANCE_KM = 50;
const SKILLS_WEIGHT = 0.7;
const GEO_WEIGHT = 0.3;

// Sanitize filters
$matchThreshold = min(max((int)($_GET['match_threshold'] ?? 0), 0), 100);
$locationFilter = in_array($_GET['location'] ?? 'all', ['all', 'nearby']) ? $_GET['location'] ?? 'all' : 'all';
$jobTypeFilter = in_array($_GET['job_type'] ?? 'all', ['all', 'Full-time', 'Part-time', 'Contract']) ? $_GET['job_type'] ?? 'all' : 'all';
$workTypeFilter = in_array($_GET['work_type'] ?? 'all', ['all', 'on-site', 'remote', 'hybrid']) ? $_GET['work_type'] ?? 'all' : 'all';
$showAllJobs = isset($_GET['show_all']) && ($_GET['show_all'] === '1' || $_GET['show_all'] === 'on');

try {
    // Fetch candidate profile with skills
    $stmt = $pdo->prepare("SELECT 
        u.name, u.photo, u.latitude, u.longitude, 
        GROUP_CONCAT(s.name) AS skills 
        FROM users u
        LEFT JOIN user_skills us ON u.id = us.user_id
        LEFT JOIN skills s ON us.skill_id = s.id
        WHERE u.id = ?
        GROUP BY u.id");
    $stmt->execute([$candidateId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];

    // Extract candidate data
    $candidateSkills = !empty($candidate['skills']) ? explode(',', $candidate['skills']) : [];
    $candidateLat = is_numeric($candidate['latitude'] ?? null) ? (float)$candidate['latitude'] : null;
    $candidateLon = is_numeric($candidate['longitude'] ?? null) ? (float)$candidate['longitude'] : null;

    // Build base SQL query (without LIMIT/OFFSET)
    $baseQuery = "FROM offers o
        INNER JOIN users u ON o.recruiter_id = u.id
        WHERE o.deleted_at IS NULL";
    $params = [];

    // Add active status filter if column exists
    $statusCheck = $pdo->query("SHOW COLUMNS FROM offers LIKE 'status'");
    if ($statusCheck->rowCount() > 0) {
        $baseQuery .= " AND o.status = 'active'";
    }

    // Add search filter
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $baseQuery .= " AND (o.title LIKE ? OR u.company_name LIKE ? OR o.description LIKE ?)";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
    }

    // Add job type filter
    if ($jobTypeFilter !== 'all') {
        $baseQuery .= " AND o.employment_type = ?";
        $params[] = $jobTypeFilter;
    }

    // Add work type filter
    if ($workTypeFilter !== 'all') {
        $baseQuery .= " AND o.work_type = ?";
        $params[] = $workTypeFilter;
    }

    // Fetch ALL jobs that match the basic filters (no pagination yet)
    $sql = "SELECT 
        o.id, o.title, o.description, o.location, 
        o.created_at AS posted_date, o.employment_type AS job_type,
        o.work_type, u.company_name, o.recruiter_id, o.latitude, o.longitude
        " . $baseQuery . "
        ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allJobs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

    // Fetch all job skills in one query
    $jobSkills = [];
    if (!empty($allJobs)) {
        $jobIds = array_column($allJobs, 'id');
        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $stmt = $pdo->prepare("SELECT os.offer_id, s.name 
            FROM offer_skills os 
            JOIN skills s ON os.skill_id = s.id 
            WHERE os.offer_id IN ($placeholders)");
        $stmt->execute($jobIds);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $jobSkills[$row['offer_id']][] = $row['name'];
        }
    }

    // Calculate matching scores for each job
    $scoredJobs = [];
    foreach ($allJobs as $job) {
        // Skills match (0-100%)
        $jobSkillsList = $jobSkills[$job['id']] ?? [];
        $commonSkills = count(array_intersect(
            array_map('strtolower', $jobSkillsList),
            array_map('strtolower', $candidateSkills)
        ));
        $job['skills_score'] = !empty($jobSkillsList) ? 
            round(($commonSkills / count($jobSkillsList)) * 100) : 0;

        // Initialize geo-related scores
        $job['geo_score'] = 0;
        $job['distance_km'] = null;
        $job['location_label'] = 'Unknown';

        // Calculate geographic information if coordinates exist
        if ($candidateLat && $candidateLon && 
            is_numeric($job['latitude']) && is_numeric($job['longitude'])) {
            try {
                $job['distance_km'] = \App\Helpers\haversine(
                    [$candidateLat, $candidateLon],
                    [(float)$job['latitude'], (float)$job['longitude']]
                )->km;
                
                // Calculate geo score (0-100%) based on distance
                $job['geo_score'] = max(0, 100 - ($job['distance_km'] / MAX_DISTANCE_KM * 100));
                
                // Determine location label
                $job['location_label'] = ($job['distance_km'] <= MAX_DISTANCE_KM) ? 'Nearby' : 'Far';
            } catch (InvalidArgumentException $e) {
                error_log("Haversine error: " . $e->getMessage());
            }
        }

        // Combined score calculation
        if ($locationFilter === 'nearby') {
            // For nearby filter, only include jobs within MAX_DISTANCE_KM
            if ($job['location_label'] !== 'Nearby') {
                continue; // Skip jobs that aren't nearby
            }
            // When filtering by nearby, use weighted combined score (70% skills + 30% location)
            $job['total_score'] = round(($job['skills_score'] * SKILLS_WEIGHT) + ($job['geo_score'] * GEO_WEIGHT));
        } else {
            // For "all locations", use pure skills score
            $job['total_score'] = $job['skills_score'];
        }
        
        $scoredJobs[] = $job;
    }

    // Apply score threshold filter - ENHANCED LOGIC
    $filteredJobs = array_values(array_filter($scoredJobs, function($job) use ($matchThreshold, $showAllJobs) {
        // If "Show all jobs" is checked, show ALL jobs including 0% matches
        if ($showAllJobs) {
            return true;
        }
        
        // If "Show all jobs" is not checked, only show jobs with score > 0% AND >= threshold
        // This ensures we don't show 0% matches unless explicitly requested
        if ($job['total_score'] <= 0) {
            return false; // Never show 0% matches unless "show all" is checked
        }
        
        // Apply the minimum match threshold
        return $job['total_score'] >= $matchThreshold;
    }));

    // Sort by: Score → Distance → Skills → Date
    usort($filteredJobs, function($a, $b) use ($locationFilter) {
        // Primary sort by total score (descending)
        $comparison = $b['total_score'] <=> $a['total_score'];
        
        // If scores are equal, consider distance if available and location filter is nearby
        if ($comparison === 0 && $locationFilter === 'nearby' && isset($a['distance_km']) && isset($b['distance_km'])) {
            $comparison = ($a['distance_km'] <=> $b['distance_km']);
        }
        
        // Then by skills score if still equal
        if ($comparison === 0) {
            $comparison = $b['skills_score'] <=> $a['skills_score'];
        }
        
        // Finally by date if still equal
        if ($comparison === 0) {
            $comparison = strtotime($b['posted_date']) <=> strtotime($a['posted_date']);
        }
        
        return $comparison;
    });

    // Get total count after filtering
    $totalFiltered = count($filteredJobs);
    $totalPages = max(1, ceil($totalFiltered / $limit));

    // Apply pagination
    $filteredJobs = array_slice($filteredJobs, $offset, $limit);

    // Get applied job IDs
    $appliedJobs = [];
    $appStmt = $pdo->prepare("SELECT offer_id FROM applications WHERE candidate_id = ?");
    $appStmt->execute([$candidateId]);
    $appliedJobs = $appStmt->fetchAll(PDO::FETCH_COLUMN, 0);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "System error. Please try again later.";
    $filteredJobs = [];
    $totalPages = 1;
    $totalFiltered = 0;
}

// Work type configuration for display
$workTypeConfig = [
    'on-site' => ['label' => 'On-site', 'icon' => 'bi-building', 'color' => 'var(--space-blue)'],
    'remote' => ['label' => 'Remote', 'icon' => 'bi-house', 'color' => 'var(--space-cyan)'],
    'hybrid' => ['label' => 'Hybrid', 'icon' => 'bi-laptop', 'color' => 'var(--space-purple)']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Listings | DK Soft</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <link href="../Public/Assets/CSS/candidate.css" rel="stylesheet">
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
            --glow-cyan: 0 0 20px rgba(0, 240, 255, 0.3);
            --glow-pink: 0 0 20px rgba(255, 45, 120, 0.3);
            --transition: all 0.2s ease;
            --gradient-space: linear-gradient(135deg, var(--space-navy) 0%, var(--space-darker) 100%);
            --gradient-accent: linear-gradient(135deg, var(--space-blue) 0%, var(--space-purple) 100%);
            --gradient-score: linear-gradient(90deg, var(--space-pink) 0%, var(--space-purple) 50%, var(--space-blue) 100%);
            --gradient-reset: linear-gradient(135deg, var(--space-pink) 0%, var(--space-cyan) 100%);
            --success-green: #4CAF50;
            --success-dark-green: #2E7D32;
            --warning-orange: #FF9800;
            --error-red: #F44336;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gradient-space);
            background-attachment: fixed;
            color: var(--space-light);
            line-height: 1.6;
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .page-header {
            background: rgba(18, 18, 45, 0.9);
            padding: 1.5rem 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            border: 1px solid rgba(110, 59, 220, 0.2);
            box-shadow: var(--shadow-deep);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            font-weight: 700;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.75rem;
        }

        .opportunities-badge {
            background: var(--gradient-accent);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--glow-purple);
        }

        .search-filters-container {
            background: rgba(18, 18, 45, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-deep);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .search-box {
            margin-bottom: 1.5rem;
        }

        .filter-title {
            font-size: 0.85rem;
            color: var(--space-cyan);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: block;
        }

        .search-input {
            background: rgba(10, 10, 26, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-lg);
            transition: var(--transition);
            width: 100%;
            font-size: 1rem;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--space-purple);
            box-shadow: var(--glow-purple);
            background: rgba(10, 10, 26, 0.9);
        }

        .search-btn {
            background: var(--gradient-accent);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            box-shadow: var(--glow-purple);
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(110, 59, 220, 0.4);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            margin-bottom: 0;
        }

        .filter-select {
            background: rgba(10, 10, 26, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-lg);
            transition: var(--transition);
            width: 100%;
            font-size: 1rem;
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--space-purple);
            box-shadow: var(--glow-purple);
        }

        .show-all-toggle {
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-check-input {
            width: 1.1em;
            height: 1.1em;
            margin-top: 0;
            background-color: rgba(10, 10, 26, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-check-input:checked {
            background-color: var(--space-purple);
            border-color: var(--space-purple);
        }

        .form-check-label {
            color: var(--space-light);
            font-size: 0.95rem;
        }

        /* Enhanced Reset Button Styles */
        .btn-reset {
            background: transparent;
            color: rgba(224, 224, 255, 0.7);
            border: none;
            border-radius: var(--radius-md);
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-reset:hover {
            color: var(--space-cyan);
            background: rgba(0, 240, 255, 0.08);
            transform: translateY(-1px);
        }

        .btn-reset:active {
            color: var(--space-cyan, #00f0ff);
            background: rgba(0, 240, 255, 0.12);
            border-color: rgba(0, 240, 255, 0.3);
            transform: translateY(0);
            box-shadow: 
                0 1px 2px rgba(0, 0, 0, 0.1),
                0 0 8px rgba(0, 240, 255, 0.2);
        }

        .btn-reset:focus {
            outline: 2px solid rgba(0, 240, 255, 0.4);
            outline-offset: 2px;
        }

        .btn-reset:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn-reset i {
            font-size: 0.9rem;
            vertical-align: baseline;
            display: inline-block;
        }

        .btn-reset:hover i {
            transform: rotate(180deg);
        }

        /* Work Type Tag Styles */
        .work-type-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .work-type-tag.work-on-site {
            color: var(--space-blue);
            border-color: rgba(58, 58, 255, 0.3);
            background: rgba(58, 58, 255, 0.1);
        }

        .work-type-tag.work-remote {
            color: var(--space-cyan);
            border-color: rgba(0, 240, 255, 0.3);
            background: rgba(0, 240, 255, 0.1);
        }

        .work-type-tag.work-hybrid {
            color: var(--space-purple);
            border-color: rgba(110, 59, 220, 0.3);
            background: rgba(110, 59, 220, 0.1);
        }

        .work-type-tag:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* Job List */
        .job-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .job-card {
            background: rgba(18, 18, 45, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-deep);
            border: 1px solid rgba(110, 59, 220, 0.1);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px -5px rgba(110, 59, 220, 0.4), 
                        0 10px 25px -10px rgba(0, 0, 0, 0.4);
            border-color: rgba(110, 59, 220, 0.3);
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .job-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            margin: 0;
            line-height: 1.4;
        }

        .company-name {
            color: var(--space-cyan);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--space-light);
        }

        .meta-item i {
            color: var(--space-purple);
            font-size: 1rem;
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
            font-size: 0.8rem;
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

        .job-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .location-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .job-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-view {
            background: rgba(255, 255, 255, 0.05);
            color: var(--space-light);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
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
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
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
            background: linear-gradient(135deg, var(--success-green) 0%, var(--success-dark-green) 100%);
            color: white;
            pointer-events: none;
            box-shadow: 0 0 15px rgba(76, 175, 80, 0.3);
        }

        .match-badge {
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            flex-shrink: 0;
            margin-left: 0.5rem;
        }

        /* Enhanced Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(18, 18, 45, 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            margin: 2rem 0;
            border: 1px dashed rgba(110, 59, 220, 0.3);
            box-shadow: var(--shadow-deep);
            position: relative;
            overflow: hidden;
        }

        .empty-state::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(110, 59, 220, 0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
        }

        .empty-state-content {
            position: relative;
            z-index: 2;
        }

        .empty-state-icon {
            font-size: 4rem;
            background: var(--gradient-reset);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            display: inline-block;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .empty-state h4 {
            color: white;
            margin-bottom: 1rem;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: rgba(224, 224, 255, 0.8);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            font-size: 1rem;
            line-height: 1.7;
        }

        /* Pagination */
        .pagination-container {
            margin-top: 3rem;
            display: flex;
            justify-content: center;
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
        }

        .page-item .page-link {
            background: rgba(18, 18, 45, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--space-light);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .page-item.active .page-link {
            background: var(--gradient-accent);
            border-color: var(--space-purple);
            color: white;
            box-shadow: var(--glow-purple);
        }

        .page-item:not(.active) .page-link:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                padding: 1.25rem;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .job-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .match-badge {
                margin-left: 0;
            }
            
            .job-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .job-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .job-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/Candidate_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Candidate_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid">
                <div class="page-header">
                    <h2><i class="bi bi-briefcase me-2"></i>Job Opportunities</h2>
                    <span class="opportunities-badge">
                        <?= $totalFiltered ?> <?= $totalFiltered === 1 ? 'opportunity' : 'opportunities' ?>
                    </span>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <!-- Search and Filters -->
                <form method="GET" class="search-filters-container">
                    <div class="search-box">
                        <label class="filter-title">SEARCH JOBS</label>
                        <div class="input-group">
                            <input type="text" class="form-control search-input" 
                                   name="search" placeholder="Job title, company, or keywords..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-title">MINIMUM MATCH</label>
                            <select name="match_threshold" class="form-select filter-select">
                                <option value="0" <?= $matchThreshold === 0 ? 'selected' : '' ?>>Any match</option>
                                <option value="30" <?= $matchThreshold === 30 ? 'selected' : '' ?>>Good (30%+)</option>
                                <option value="50" <?= $matchThreshold === 50 ? 'selected' : '' ?>>Strong (50%+)</option>
                                <option value="70" <?= $matchThreshold === 70 ? 'selected' : '' ?>>Excellent (70%+)</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-title">LOCATION</label>
                            <select name="location" class="form-select filter-select">
                                <option value="all" <?= $locationFilter === 'all' ? 'selected' : '' ?>>Any location</option>
                                <option value="nearby" <?= $locationFilter === 'nearby' ? 'selected' : '' ?>>Within <?= MAX_DISTANCE_KM ?> km</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-title">JOB TYPE</label>
                            <select name="job_type" class="form-select filter-select">
                                <option value="all" <?= $jobTypeFilter === 'all' ? 'selected' : '' ?>>All types</option>
                                <option value="Full-time" <?= $jobTypeFilter === 'Full-time' ? 'selected' : '' ?>>Full-time</option>
                                <option value="Part-time" <?= $jobTypeFilter === 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                                <option value="Contract" <?= $jobTypeFilter === 'Contract' ? 'selected' : '' ?>>Contract</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-title">WORK TYPE</label>
                            <select name="work_type" class="form-select filter-select">
                                <option value="all" <?= $workTypeFilter === 'all' ? 'selected' : '' ?>>All work types</option>
                                <option value="on-site" <?= $workTypeFilter === 'on-site' ? 'selected' : '' ?>>On-site</option>
                                <option value="remote" <?= $workTypeFilter === 'remote' ? 'selected' : '' ?>>Remote</option>
                                <option value="hybrid" <?= $workTypeFilter === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                            </select>
                        </div>
                    </div>

                    <div class="show-all-toggle">
                        <input class="form-check-input" type="checkbox" id="showAllJobs" name="show_all" value="1" <?= $showAllJobs ? 'checked' : '' ?>>
                        <label class="form-check-label" for="showAllJobs">Show all jobs (including 0% matches)</label>
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <button type="submit" class="btn search-btn">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                        <?php if (!empty($search) || $matchThreshold > 0 || $locationFilter !== 'all' || $jobTypeFilter !== 'all' || $workTypeFilter !== 'all'): ?>
                            <a href="jobs.php" class="btn-reset">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Job listings -->
                <?php if (!empty($filteredJobs)): ?>
                    <div class="job-list">
                        <?php foreach ($filteredJobs as $job): 
                            $isApplied = in_array($job['id'], $appliedJobs);
                            $skillsScore = (int)$job['skills_score'];
                            $geoScore = isset($job['geo_score']) ? (int)$job['geo_score'] : 0;
                            $totalScore = (int)$job['total_score'];
                            
                            // Determine colors based on scores
                            $skillsColor = $skillsScore >= 70 ? 'var(--space-cyan)' : 
                                          ($skillsScore >= 40 ? 'var(--space-blue)' : 'var(--space-pink)');
                            $geoColor = $geoScore >= 70 ? 'var(--space-cyan)' : 
                                       ($geoScore >= 40 ? 'var(--space-blue)' : 'var(--space-pink)');
                            $totalColor = $totalScore >= 70 ? 'linear-gradient(135deg, var(--space-cyan) 0%, var(--space-blue) 100%)' : 
                                         ($totalScore >= 40 ? 'linear-gradient(135deg, var(--space-blue) 0%, var(--space-purple) 100%)' : 
                                         'linear-gradient(135deg, var(--space-pink) 0%, var(--space-purple) 100%)');
                            $totalGlow = $totalScore >= 70 ? '0 0 10px rgba(0, 240, 255, 0.3)' : 
                                        ($totalScore >= 40 ? '0 0 10px rgba(58, 58, 255, 0.3)' : 
                                        '0 0 10px rgba(255, 45, 120, 0.3)');

                            // Get work type configuration
                            $workType = $job['work_type'] ?? 'on-site';
                            $workTypeInfo = $workTypeConfig[$workType] ?? $workTypeConfig['on-site'];
                        ?>
                            <div class="job-card">
                                <div class="job-header">
                                    <h3 class="job-title"><?= htmlspecialchars($job['title']) ?></h3>
                                    <span class="match-badge" style="background: <?= $totalColor ?>; box-shadow: <?= $totalGlow ?>;">
                                        <?= $totalScore ?>% match
                                    </span>
                                </div>
                                
                                <p class="company-name">
                                    <i class="bi bi-building"></i> <?= htmlspecialchars($job['company_name']) ?>
                                </p>
                                
                                <div class="job-meta">
                                    <span class="meta-item">
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($job['location']) ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="bi bi-clock"></i> <?= htmlspecialchars($job['job_type']) ?>
                                    </span>
                                    <span class="work-type-tag work-<?= $workType ?>">
                                        <i class="<?= $workTypeInfo['icon'] ?>"></i>
                                        <?= $workTypeInfo['label'] ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($job['posted_date'])) ?>
                                    </span>
                                </div>
                                
                                <div class="score-visual">
                                    <div class="score-bars">
                                        <div class="skills-bar" style="width: <?= $skillsScore ?>%; background: <?= $skillsColor ?>"></div>
                                        <?php if ($locationFilter === 'nearby' && isset($job['geo_score'])): ?>
                                            <div class="geo-bar" style="width: <?= $geoScore ?>%; background: <?= $geoColor ?>"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="score-labels">
                                        <span class="score-value">Skills: <?= $skillsScore ?>%</span>
                                        <?php if ($locationFilter === 'nearby' && isset($job['geo_score'])): ?>
                                            <span class="score-value">Location: <?= $geoScore ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="job-footer">
                                    <?php if ($locationFilter === 'nearby' && isset($job['distance_km'])): ?>
                                        <span class="location-tag <?= $job['distance_km'] <= MAX_DISTANCE_KM ? 'location-nearby' : 'location-far' ?>">
                                            <i class="bi bi-signpost"></i> 
                                            <?= round($job['distance_km']) ?> km 
                                            <?= $job['distance_km'] <= MAX_DISTANCE_KM ? 'nearby' : 'away' ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="job-actions">
                                        <a href="Candidate_job_detail.php?id=<?= (int)$job['id'] ?>" class="btn-view">
                                            <i class="bi bi-eye"></i> Details
                                        </a>
                                        <?php if ($isApplied): ?>
                                            <button class="btn-apply btn-applied" disabled>
                                                <i class="bi bi-check-circle"></i> Applied
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-apply" data-apply-job data-job-id="<?= (int)$job['id'] ?>">
                                                <i class="bi bi-send"></i> Apply
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                                               aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" 
                                               href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                                               aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-content">
                            <div class="empty-state-icon">
                                <i class="bi bi-binoculars"></i>
                            </div>
                            <h4>No matching jobs found</h4>
                            <p>We couldn't find any jobs matching your current criteria. Try adjusting your filters, expanding your search terms, or enable "Show all jobs" to see more opportunities.</p>
                            <a href="jobs.php" class="btn-reset">
                                <i class="bi bi-arrow-repeat me-2"></i> Reset All Filters
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Toast notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <?php include __DIR__ . '/Includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../Public/Assets/Js/jobs.js"></script>
</body>
</html>