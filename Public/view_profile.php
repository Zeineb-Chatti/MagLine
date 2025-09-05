<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit;
}

if (!isset($_SESSION['role'])) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_role = $stmt->fetchColumn();
    $_SESSION['role'] = $user_role ? $user_role : 'candidate';
}

$current_user_role = $_SESSION['role'];
$profile_id = $_GET['user_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    header("Location: messages.php?error=profile_not_found");
    exit;
}

$is_recruiter = ($profile['role'] === 'recruiter');

$currentSkills = [];
if (!$is_recruiter) {
    $stmt = $pdo->prepare("SELECT s.id, s.name FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ?");
    $stmt->execute([$profile_id]);
    $currentSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$resume = null;
if (!$is_recruiter) {
    $stmt = $pdo->prepare("SELECT filename, uploaded_at FROM resumes WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->execute([$profile_id]);
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);
}

$jobOffers = [];
if ($is_recruiter) {
    $stmt = $pdo->prepare("
        SELECT id, title, description, location, employment_type, created_at 
        FROM offers 
        WHERE recruiter_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$profile_id]);
    $jobOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$profilePhoto = null;

if ($is_recruiter && !empty($profile['manager_photo'])) {
    $profilePhoto = '/Public/Uploads/Manager_Photos/' . $profile['manager_photo'];
} elseif (!empty($profile['photo'])) {
    $profilePhoto = '/Public/Uploads/profile_photos/' . $profile['photo'];
}

if (!$profilePhoto) {
    $profilePhoto = '/Public/Assets/default-user.png';
}

$resumeUploadDate = $resume ? date('F j, Y', strtotime($resume['uploaded_at'])) : null;
$memberSince = date('F Y', strtotime($profile['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['name']) ?>'s Profile | MagLine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link href="../Public/Assets/CSS/candidate.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/sidebar.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
   <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #3a86ff 0%, #0066ff 100%);
            --secondary-gradient: linear-gradient(135deg, #0066ff 0%, #00c6ff 100%);
            --glass-effect: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glow-effect: 0 0 15px rgba(58, 134, 255, 0.3);
            --deep-blue: #0a192f;
            --mid-blue: #172a45;
            --light-blue: #64ffda;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--deep-blue);
            color: #e6f1ff;
        }

        .profile-container {
            background: var(--mid-blue);
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            margin: 2rem auto;
            max-width: 1200px;
            overflow: hidden;
            backdrop-filter: blur(8px);
            background: linear-gradient(to bottom right, rgba(23, 42, 69, 0.9), rgba(10, 25, 47, 0.9));
            position: relative;
            z-index: 1;
        }

        .profile-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(58, 134, 255, 0.1) 0%, transparent 70%);
            z-index: -1;
            animation: float 15s infinite alternate;
        }

        @keyframes float {
            0% { transform: translate(0, 0); }
            50% { transform: translate(-5%, -5%); }
            100% { transform: translate(5%, 5%); }
        }

        .profile-header {
            padding: 3rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            border-bottom: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
        }

        .profile-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(100, 255, 218, 0.5), transparent);
        }

        .profile-photo-container {
            width: 160px;
            height: 160px;
            flex-shrink: 0;
            border-radius: 50%;
            padding: 5px;
            background: var(--primary-gradient);
            box-shadow: var(--glow-effect);
            transition: transform 0.3s ease;
        }

        .profile-photo-container:hover {
            transform: scale(1.05);
        }

        .profile-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--mid-blue);
        }

        .profile-info {
            flex: 1;
            position: relative;
        }

        .profile-display-name {
            font-size: 2.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #64ffda 0%, #3a86ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 15px rgba(58, 134, 255, 0.2);
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .profile-subtitle {
            color: #ccd6f6;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 400;
        }

        .profile-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #8892b0;
            font-size: 0.95rem;
            background: rgba(100, 255, 218, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: 1px solid rgba(100, 255, 218, 0.2);
        }

        .content-wrapper {
            padding: 3rem;
        }

        .section {
            margin-bottom: 3rem;
            position: relative;
        }

        .info-title {
            font-size: 1.6rem;
            font-weight: 600;
            color: #ccd6f6;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .info-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 3px;
        }

        .content-box {
            background: rgba(10, 25, 47, 0.5);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .content-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .skill-badge {
            display: inline-block;
            background: var(--primary-gradient);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            margin: 0.4rem;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .skill-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .cv-button {
            background: var(--primary-gradient);
            color: white;
            padding: 0.9rem 1.8rem;
            border-radius: 30px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(58, 134, 255, 0.3);
        }

        .cv-button:hover {
            color: white;
            background: var(--secondary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(58, 134, 255, 0.4);
        }

        .company-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease;
        }

        .company-logo:hover {
            transform: scale(1.05);
        }

        .contact-list {
            list-style: none;
            padding: 0;
        }

        .contact-list li {
            padding: 1rem 0;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.3s ease;
        }

        .contact-list li:hover {
            background: rgba(100, 255, 218, 0.05);
        }

        .contact-list li:last-child {
            border-bottom: none;
        }

        .contact-list a {
            color: #64ffda;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-list a:hover {
            color: #3a86ff;
            text-decoration: underline;
        }

        .job-offer-card {
            background: rgba(10, 25, 47, 0.7);
            border-radius: 12px;
            padding: 1.8rem;
            border: 1px solid var(--glass-border);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .job-offer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border-color: rgba(100, 255, 218, 0.3);
        }

        .job-offer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-gradient);
        }

        .job-offer-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #ccd6f6;
            margin-bottom: 0.8rem;
        }

        .job-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
        }

        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #8892b0;
            font-size: 0.9rem;
            background: rgba(100, 255, 218, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            border: 1px solid rgba(100, 255, 218, 0.2);
        }

        .view-offer-btn {
            background: var(--primary-gradient);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .view-offer-btn:hover {
            background: var(--secondary-gradient);
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 4px 15px rgba(58, 134, 255, 0.3);
        }

        .hidden-section {
            display: none;
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            font-size: 0.95rem;
            font-weight: 500;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .recruiter-badge {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .candidate-badge {
            background: rgba(58, 134, 255, 0.15);
            color: #3a86ff;
            border: 1px solid rgba(58, 134, 255, 0.3);
        }

        .profile-badge i {
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 0.8rem 1.8rem;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(58, 134, 255, 0.3);
        }

        .btn-primary:hover {
            background: var(--secondary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(58, 134, 255, 0.4);
        }

        .btn-outline-primary {
            background: transparent;
            border: 2px solid #3a86ff;
            color: #3a86ff;
            padding: 0.8rem 1.8rem;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: rgba(58, 134, 255, 0.1);
            border-color: #64ffda;
            color: #64ffda;
            transform: translateY(-2px);
        }

        /* Floating animation for elements */
        .float-animation {
            animation: floatElement 6s ease-in-out infinite;
        }

        @keyframes floatElement {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 2rem;
            }
            
            .profile-photo-container {
                margin-bottom: 1.5rem;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .content-wrapper {
                padding: 2rem;
            }
        }

        @media (max-width: 768px) {
            .profile-display-name {
                font-size: 2.2rem;
            }
            
            .info-title {
                font-size: 1.4rem;
            }
            
            .content-box {
                padding: 1.5rem;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--deep-blue);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-gradient);
            border-radius: 10px;
        }

        /* Pulse animation for interactive elements */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse:hover {
            animation: pulse 1.5s infinite;
        }
    </style>
</head>
<body>
    <?php if ($current_user_role === 'candidate'): ?>
        <?php include __DIR__ . '/Includes/Candidate_header.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/Includes/Recruiter_header.php'; ?>
    <?php endif; ?>
    
    <div class="main-wrapper">
        <?php if ($current_user_role === 'candidate'): ?>
            <?php include __DIR__ . '/Includes/Candidate_sidebar.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/Includes/Recruiter_sidebar.php'; ?>
        <?php endif; ?>
        
        <main class="main-content">
            <div class="container-fluid">
                <div class="profile-container">
                    <div class="profile-header">
                        <div class="profile-photo-container">
                            <img src="<?= htmlspecialchars($profilePhoto) ?>" 
                                 alt="<?= htmlspecialchars($profile['name']) ?>'s Profile Photo" 
                                 class="profile-photo" 
                                 onerror="this.onerror=null;this.src='/Public/Assets/default-user.png'">
                        </div>
                        <div class="profile-info">
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                <span class="profile-badge <?= $is_recruiter ? 'recruiter-badge' : 'candidate-badge' ?>">
                                    <i class="bi <?= $is_recruiter ? 'bi-briefcase' : 'bi-person' ?>"></i>
                                    <?= $is_recruiter 
                                        ? 'Recruiter' . (!empty($profile['company_name']) ? ' at ' . htmlspecialchars($profile['company_name']) : '')
                                        : 'Candidate' ?>
                                </span>
                            </div>
                            <h1 class="profile-display-name"><?= htmlspecialchars($profile['name']) ?></h1>
                            <?php if ($is_recruiter && !empty($profile['manager_job_title'])): ?>
                                <div class="profile-subtitle"><?= htmlspecialchars($profile['manager_job_title']) ?></div>
                            <?php elseif (!$is_recruiter && !empty($profile['title'])): ?>
                                <div class="profile-subtitle"><?= htmlspecialchars($profile['title']) ?></div>
                            <?php endif; ?>
                            <div class="profile-meta">
                                <span class="meta-item">
                                    <i class="bi bi-person-circle"></i> 
                                    <?= $is_recruiter ? 'Recruiter' : 'Candidate' ?> since <?= $memberSince ?>
                                </span>
                                <?php if (!empty($profile['location'])): ?>
                                    <span class="meta-item">
                                        <i class="bi bi-geo-alt-fill"></i> 
                                        <?= htmlspecialchars($profile['location']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="messages.php?with_user=<?= $profile['id'] ?>&name=<?= urlencode($profile['name']) ?>" 
                           class="btn btn-primary">
                            <i class="bi bi-send-fill"></i> Send Message
                        </a>
                    </div>

                    <?php if ($is_recruiter && !empty($jobOffers)): ?>
                        <div class="text-center my-3">
                            <button id="toggleJobOffers" class="btn btn-outline-primary">
                                <i class="bi bi-briefcase"></i> View <?= count($jobOffers) ?> Job Offers
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="content-wrapper">
                        <?php if (!empty($profile['about'])): ?>
                            <section class="section">
                                <h3 class="info-title">
                                    <i class="bi bi-person-lines-fill"></i>About
                                </h3>
                                <div class="content-box">
                                    <?= nl2br(htmlspecialchars($profile['about'])) ?>
                                </div>
                            </section>
                        <?php endif; ?>
                        
                        <?php if (!$is_recruiter && !empty($currentSkills)): ?>
                            <section class="section">
                                <h3 class="info-title">
                                    <i class="bi bi-tools"></i>Skills & Expertise
                                </h3>
                                <div class="d-flex flex-wrap">
                                    <?php foreach ($currentSkills as $skill): ?>
                                        <span class="skill-badge">
                                            <?= htmlspecialchars($skill['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                        
                        <?php if (!$is_recruiter && $resume): ?>
                            <section class="section">
                                <h3 class="info-title">
                                    <i class="bi bi-file-earmark-text"></i>Curriculum Vitae
                                </h3>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <a href="/Public/Uploads/Cvs/<?= htmlspecialchars($resume['filename']) ?>" 
                                       target="_blank" class="cv-button">
                                        <i class="bi bi-eye"></i> View CV
                                    </a>
                                    <span class="text-muted">Uploaded <?= $resumeUploadDate ?></span>
                                </div>
                            </section>
                        <?php endif; ?>
                        
                        <?php if ($is_recruiter): ?>
                            <?php if (!empty($profile['company_name'])): ?>
                                <section class="section">
                                    <h3 class="info-title">
                                        <i class="bi bi-building"></i>Company
                                    </h3>
                                    <div class="content-box">
                                        <div class="d-flex align-items-center mb-4">
                                            <?php if (!empty($profile['company_logo'])): ?>
                                                <img src="/Public/Uploads/Company_Logos/<?= htmlspecialchars($profile['company_logo']) ?>" 
                                                     alt="Company Logo" class="company-logo me-4"
                                                     onerror="this.style.display='none'">
                                            <?php endif; ?>
                                            <div>
                                                <h4 class="mb-1"><?= htmlspecialchars($profile['company_name']) ?></h4>
                                                <?php if (!empty($profile['industry'])): ?>
                                                    <small class="text-muted"><?= htmlspecialchars($profile['industry']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($profile['company_size'])): ?>
                                            <p class="mb-2"><strong>Company Size:</strong> <?= htmlspecialchars($profile['company_size']) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile['company_website'])): ?>
                                            <p class="mb-3">
                                                <strong>Website:</strong> 
                                                <a href="<?= htmlspecialchars($profile['company_website']) ?>" target="_blank" 
                                                   class="text-primary">
                                                    <?= htmlspecialchars($profile['company_website']) ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($profile['about_company'])): ?>
                                            <div class="mt-3">
                                                <h5 class="mb-3">About Company</h5>
                                                <p><?= nl2br(htmlspecialchars($profile['about_company'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            <?php endif; ?>
                            
                            <?php if (!empty($profile['manager_name'])): ?>
                                <section class="section">
                                    <h3 class="info-title">
                                        <i class="bi bi-person-badge"></i>Manager Information
                                    </h3>
                                    <div class="content-box">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5><?= htmlspecialchars($profile['manager_name']) ?></h5>
                                                <?php if (!empty($profile['manager_job_title'])): ?>
                                                    <p class="text-muted"><?= htmlspecialchars($profile['manager_job_title']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="list-unstyled">
                                                    <?php if (!empty($profile['manager_email'])): ?>
                                                        <li class="mb-2">
                                                            <i class="bi bi-envelope-fill me-2"></i>
                                                            <a href="mailto:<?= htmlspecialchars($profile['manager_email']) ?>" 
                                                               class="text-decoration-none">
                                                                <?= htmlspecialchars($profile['manager_email']) ?>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($profile['manager_phone'])): ?>
                                                        <li>
                                                            <i class="bi bi-telephone-fill me-2"></i>
                                                            <a href="tel:<?= htmlspecialchars($profile['manager_phone']) ?>" 
                                                               class="text-decoration-none">
                                                                <?= htmlspecialchars($profile['manager_phone']) ?>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <section class="section">
                            <h3 class="info-title">
                                <i class="bi bi-envelope"></i>Contact Information
                            </h3>
                            <div class="content-box">
                                <ul class="contact-list">
                                    <li>
                                        <i class="bi bi-envelope-fill"></i>
                                        <a href="mailto:<?= htmlspecialchars($profile['email']) ?>">
                                            <?= htmlspecialchars($profile['email']) ?>
                                        </a>
                                    </li>
                                    <?php if (!empty($profile['phone'])): ?>
                                        <li>
                                            <i class="bi bi-telephone-fill"></i>
                                            <a href="tel:<?= htmlspecialchars($profile['phone']) ?>">
                                                <?= htmlspecialchars($profile['phone']) ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['linkedin'])): ?>
                                        <li>
                                            <i class="bi bi-linkedin"></i>
                                            <a href="<?= htmlspecialchars($profile['linkedin']) ?>" target="_blank">
                                                LinkedIn Profile
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (!empty($profile['location'])): ?>
                                        <li>
                                            <i class="bi bi-geo-alt-fill"></i>
                                            <span><?= htmlspecialchars($profile['location']) ?></span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </section>

                        <?php if ($is_recruiter && !empty($jobOffers)): ?>
                            <section class="hidden-section" id="jobOffersSection">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h3 class="info-title">
                                        <i class="bi bi-briefcase"></i>Published Job Offers
                                    </h3>
                                    <span class="badge bg-primary">
                                        <?= count($jobOffers) ?> Active
                                    </span>
                                </div>
                                
                                <div class="row">
                                    <?php foreach ($jobOffers as $offer): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="job-offer-card">
                                                <h4 class="job-offer-title"><?= htmlspecialchars($offer['title']) ?></h4>
                                                <div class="job-meta">
                                                    <?php if (!empty($offer['location'])): ?>
                                                        <span class="job-meta-item">
                                                            <i class="bi bi-geo-alt-fill"></i>
                                                            <?= htmlspecialchars($offer['location']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($offer['employment_type'])): ?>
                                                        <span class="job-meta-item">
                                                            <i class="bi bi-clock-history"></i>
                                                            <?= htmlspecialchars($offer['employment_type']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="job-meta-item">
                                                        <i class="bi bi-calendar"></i>
                                                        <?= date('M j, Y', strtotime($offer['created_at'])) ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($offer['description'])): ?>
                                                    <p class="mb-3">
                                                        <?= nl2br(htmlspecialchars(substr($offer['description'], 0, 180))) ?>
                                                        <?= strlen($offer['description']) > 180 ? '...' : '' ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if ($current_user_role === 'candidate'): ?>
                                                    <!-- Only show View Details button for candidates -->
                                                    <a href="candidate_job_detail.php?id=<?= $offer['id'] ?>" class="view-offer-btn">
                                                        <i class="bi bi-eye"></i> View Details
                                                    </a>
                                                <?php else: ?>
                                                    <!-- For recruiters viewing other recruiters' profiles, just show info -->
                                                    <div class="text-muted small">
                                                        <i class="bi bi-info-circle"></i> Job offer posted by this recruiter
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/Includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scroll behavior
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelector(this.getAttribute('href')).scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            });

            const toggleBtn = document.getElementById('toggleJobOffers');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    const section = document.getElementById('jobOffersSection');
                    const isHidden = section.classList.contains('hidden-section');
                    
                    if (isHidden) {
                        section.classList.remove('hidden-section');
                        toggleBtn.innerHTML = '<i class="bi bi-eye-slash"></i> Hide Job Offers';
                        toggleBtn.classList.add('btn-primary');
                        toggleBtn.classList.remove('btn-outline-primary');
                    } else {
                        section.classList.add('hidden-section');
                        toggleBtn.innerHTML = '<i class="bi bi-briefcase"></i> View <?= count($jobOffers) ?> Job Offers';
                        toggleBtn.classList.remove('btn-primary');
                        toggleBtn.classList.add('btn-outline-primary');
                    }
                });
            }

            document.querySelectorAll('.skill-badge, .job-offer-card, .content-box').forEach(el => {
                el.style.transition = 'all 0.3s ease';
            });
        });
    </script>
</body>
</html>
