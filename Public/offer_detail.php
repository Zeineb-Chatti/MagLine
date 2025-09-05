<?php
session_start();
$baseURL = '/MagLine/Public';
require_once __DIR__ . '/../Config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}

$offerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$offerId || $offerId < 1) {
    header("Location: Dashboard_Recruiter.php?error=invalid_id");
    exit;
}

$recruiterId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.recruiter_id,
            o.title,
            o.description,
            o.location,
            o.created_at,
            o.status,
            o.employment_type,
            o.work_type,
            u.company_name
        FROM offers o
        JOIN users u ON o.recruiter_id = u.id
        WHERE o.id = ? 
        AND o.recruiter_id = ?
        AND o.deleted_at IS NULL
    ");
    $stmt->execute([$offerId, $recruiterId]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$offer) {
        header("Location: Dashboard_Recruiter.php?error=offer_not_found");
        exit;
    }

    $stmtSkills = $pdo->prepare("
        SELECT s.name 
        FROM offer_skills os
        JOIN skills s ON os.skill_id = s.id
        WHERE os.offer_id = ?
    ");
    $stmtSkills->execute([$offerId]);
    $skills = $stmtSkills->fetchAll(PDO::FETCH_COLUMN);

    $createdDate = date('M j, Y \a\t g:i A', strtotime($offer['created_at']));
    $daysAgo = floor((time() - strtotime($offer['created_at'])) / (60 * 60 * 24));

    $applicationCount = 0;
    $applications = [];
    $statusCounts = [
        'pending' => 0,
        'reviewed' => 0,
        'interview' => 0,
        'rejected' => 0,
        'hired' => 0
    ];

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(status = 'pending') as pending,
            SUM(status = 'reviewed') as reviewed,
            SUM(status = 'interview') as interview,
            SUM(status = 'rejected') as rejected,
            SUM(status = 'hired') as hired
        FROM applications 
        WHERE offer_id = ?
    ");
    $stmt->execute([$offerId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        $applicationCount = $stats['total'];
        $statusCounts = [
            'pending' => $stats['pending'],
            'reviewed' => $stats['reviewed'],
            'interview' => $stats['interview'],
            'rejected' => $stats['rejected'],
            'hired' => $stats['hired']
        ];
    }

    $stmt = $pdo->prepare("
        SELECT 
            a.id, 
            a.status,
            a.created_at,
            u.name AS candidate_name
        FROM applications a
        JOIN users u ON a.candidate_id = u.id
        WHERE a.offer_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$offerId]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: Dashboard_Recruiter.php?error=database_error");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($offer['title']) ?> | MagLine Recruiter</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #13131a;
            --bg-tertiary: #1a1a24;
            --bg-card: #1f1f2e;
            --bg-elevated: #252538;
            
            --accent-primary: #6366f1;
            --accent-secondary: #8b5cf6;
            --accent-success: #10b981;
            --accent-warning: #f59e0b;
            --accent-danger: #ef4444;
            
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #64748b;
            
            --border-subtle: rgba(148, 163, 184, 0.1);
            --border-emphasis: rgba(148, 163, 184, 0.2);
            
            --shadow-soft: 0 1px 3px 0 rgba(0, 0, 0, 0.3);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            --shadow-glow: 0 0 20px rgba(99, 102, 241, 0.15);
            
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        * {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            font-weight: 400;
        }

        /* Header Section */
        .job-header {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl);
            padding: 3rem 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .job-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent-primary), transparent);
            opacity: 0.6;
        }

        .job-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--text-secondary) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            align-items: center;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .meta-item i {
            color: var(--accent-primary);
            font-size: 1.1rem;
        }

        /* Cards */
        .premium-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .premium-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-large);
            border-color: var(--border-emphasis);
        }

        .premium-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .premium-card:hover::before {
            opacity: 1;
        }

        /* Statistics Cards */
        .stat-card {
            text-align: center;
            height: 100%;
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-glow);
            border-color: var(--accent-primary);
        }

        .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--accent-primary);
            margin-bottom: 0.5rem;
            font-family: 'JetBrains Mono', monospace;
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Content Sections */
        .job-section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-primary);
        }

        .job-section-title i {
            color: var(--accent-primary);
            font-size: 1.25rem;
        }

        .content-block {
            background: var(--bg-secondary);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .job-description {
            line-height: 1.8;
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* Skills */
        .skill-tag {
            display: inline-flex;
            align-items: center;
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-primary);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0.25rem;
            border: 1px solid rgba(99, 102, 241, 0.2);
            transition: all 0.3s ease;
        }

        .skill-tag:hover {
            background: rgba(99, 102, 241, 0.2);
            transform: translateY(-1px);
        }

        /* Applications Sidebar */
        .application-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm);
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .application-item:hover {
            background: var(--bg-elevated);
            border-color: var(--accent-primary);
            transform: translateX(4px);
            color: inherit;
        }

        .candidate-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .application-date {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            text-transform: capitalize;
        }

        .status-pending { background: rgba(245, 158, 11, 0.2); color: var(--accent-warning); }
        .status-reviewed { background: rgba(99, 102, 241, 0.2); color: var(--accent-primary); }
        .status-interview { background: rgba(139, 92, 246, 0.2); color: var(--accent-secondary); }
        .status-hired { background: rgba(16, 185, 129, 0.2); color: var(--accent-success); }
        .status-rejected { background: rgba(239, 68, 68, 0.2); color: var(--accent-danger); }

        /* Quick Actions */
        .quick-actions {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .action-btn {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-primary);
            color: white;
            border: none;
            box-shadow: var(--shadow-large);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .action-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-glow);
            background: var(--accent-secondary);
        }

        .action-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .action-btn:hover::before {
            transform: translateX(100%);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        /* Modal Styles */
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-emphasis);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-large);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-subtle);
        }

        .modal-footer {
            border-top: 1px solid var(--border-subtle);
        }

        .form-control {
            background: var(--bg-secondary);
            border: 1px solid var(--border-subtle);
            color: var(--text-primary);
        }

        .form-control:focus {
            background: var(--bg-secondary);
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
            color: var(--text-primary);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .job-title {
                font-size: 2rem;
            }
            
            .job-header {
                padding: 2rem 1.5rem;
            }
            
            .premium-card {
                padding: 1.5rem;
            }
            
            .job-meta {
                gap: 1rem;
            }
            
            .quick-actions {
                bottom: 1rem;
                right: 1rem;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .animate-delay-1 { animation-delay: 0.1s; }
        .animate-delay-2 { animation-delay: 0.2s; }
        .animate-delay-3 { animation-delay: 0.3s; }
        .animate-delay-4 { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/Includes/recruiter_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/recruiter_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid px-4 py-4">
                <!-- Job Header -->
                <div class="job-header animate-fade-in-up">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="flex-grow-1">
                            <h1 class="job-title"><?= htmlspecialchars($offer['title']) ?></h1>
                            <div class="job-meta">
                                <div class="meta-item">
                                    <i class="bi bi-building-fill"></i>
                                    <span><?= htmlspecialchars($offer['company_name']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    <span><?= htmlspecialchars($offer['location']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-briefcase-fill"></i>
                                    <span><?= ucfirst(str_replace('-', ' ', $offer['employment_type'])) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-house-door-fill"></i>
                                    <span><?= ucfirst(str_replace('-', ' ', $offer['work_type'])) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-calendar-check-fill"></i>
                                    <span>Posted <?= $daysAgo > 1 ? "$daysAgo days ago" : "today" ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-people-fill"></i>
                                    <span><?= $applicationCount ?> <?= $applicationCount === 1 ? 'application' : 'applications' ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#actionModal">
                                <i class="bi bi-three-dots me-2"></i>Actions
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Row -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3 animate-fade-in-up animate-delay-1">
                        <div class="stat-card">
                            <div class="stat-value"><?= $applicationCount ?></div>
                            <div class="stat-label">Total Applications</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 animate-fade-in-up animate-delay-2">
                        <div class="stat-card">
                            <div class="stat-value"><?= $statusCounts['pending'] ?></div>
                            <div class="stat-label">Pending Review</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 animate-fade-in-up animate-delay-3">
                        <div class="stat-card">
                            <div class="stat-value"><?= $statusCounts['hired'] ?></div>
                            <div class="stat-label">Successfully Hired</div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 animate-fade-in-up animate-delay-4">
                        <div class="stat-card">
                            <div class="stat-value"><?= $applicationCount > 0 ? round(($statusCounts['hired'] / $applicationCount) * 100) : 0 ?>%</div>
                            <div class="stat-label">Success Rate</div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-8 mb-4">
                        <div class="premium-card animate-fade-in-up">
                            <h2 class="section-title">
                                <i class="bi bi-file-text-fill"></i>
                                Job Description
                            </h2>
                            
                            <div class="content-block">
                                <div class="job-description">
                                    <?= nl2br(htmlspecialchars($offer['description'])) ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($skills)): ?>
                            <h3 class="section-title">
                                <i class="bi bi-stars"></i>
                                Required Skills
                            </h3>
                            
                            <div class="content-block">
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($skills as $skill): ?>
                                        <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h3 class="section-title">
                                        <i class="bi bi-info-circle-fill"></i>
                                        Job Details
                                    </h3>
                                    <div class="content-block">
                                        <div class="mb-3">
                                            <strong class="text-primary">Posted:</strong><br>
                                            <span class="text-secondary"><?= $createdDate ?></span>
                                        </div>
                                        <div class="mb-3">
                                            <strong class="text-primary">Location:</strong><br>
                                            <span class="text-secondary"><?= htmlspecialchars($offer['location']) ?></span>
                                        </div>
                                        <div>
                                            <strong class="text-primary">Job ID:</strong><br>
                                            <span class="text-secondary font-monospace">#ML-<?= str_pad($offerId, 6, '0', STR_PAD_LEFT) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <div class="premium-card animate-fade-in-up animate-delay-2">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="job-section-title mb-0">
                                    <i class="bi bi-people-fill"></i>
                                    Recent Applications
                                </h3>
                                <span class="badge bg-primary"><?= count($applications) ?></span>
                            </div>
                            
                            <?php if (!empty($applications)): ?>
                                <div class="mb-3">
                                    <?php foreach ($applications as $app): ?>
                                        <a href="application_detail.php?id=<?= $app['id'] ?>" class="application-item">
                                            <div>
                                                <div class="candidate-name"><?= htmlspecialchars($app['candidate_name']) ?></div>
                                                <div class="application-date">Applied <?= date('M j, Y', strtotime($app['created_at'])) ?></div>
                                            </div>
                                            <span class="status-badge status-<?= $app['status'] ?>">
                                                <?= ucfirst($app['status']) ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="d-grid">
                                    <a href="applications.php?offer_id=<?= $offerId ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-eye me-2"></i>View All Applications
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <h5>No Applications Yet</h5>
                                    <p>Your job posting is live and waiting for the right candidates.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#actionModal">
                                        <i class="bi bi-megaphone me-2"></i>Promote Job
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Quick Stats Card -->
                        <div class="premium-card animate-fade-in-up animate-delay-3" style="margin-top: 2rem;">
                            <h3 class="job-section-title">
                                <i class="bi bi-graph-up"></i>
                                Application Stats
                            </h3>
                            
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="text-center p-2">
                                        <div class="h5 text-warning mb-0"><?= $statusCounts['pending'] ?></div>
                                        <small class="text-muted">Pending</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2">
                                        <div class="h5 text-info mb-0"><?= $statusCounts['reviewed'] ?></div>
                                        <small class="text-muted">Reviewed</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2">
                                        <div class="h5 text-primary mb-0"><?= $statusCounts['interview'] ?></div>
                                        <small class="text-muted">Interview</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2">
                                        <div class="h5 text-success mb-0"><?= $statusCounts['hired'] ?></div>
                                        <small class="text-muted">Hired</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <button class="action-btn" data-bs-toggle="modal" data-bs-target="#actionModal" title="Job Actions">
            <i class="bi bi-gear-fill"></i>
        </button>
    </div>
    
    <!-- Enhanced Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalLabel">
                        <i class="bi bi-gear-fill me-2"></i>Job Management Actions
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <!-- Share Job -->
                        <div class="col-md-6">
                            <div class="premium-card h-100">
                                <div class="text-center">
                                    <i class="bi bi-share display-4 text-success mb-3"></i>
                                    <h6 class="fw-bold">Share Job Posting</h6>
                                    <p class="text-muted small">Share via social media or direct links</p>
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button class="btn btn-outline-primary btn-sm" onclick="shareJob('linkedin')">
                                            <i class="bi bi-linkedin"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="copyJobLink()">
                                            <i class="bi bi-link-45deg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Manage Applications -->
                        <div class="col-md-6">
                            <div class="premium-card h-100">
                                <div class="text-center">
                                    <i class="bi bi-people display-4 text-warning mb-3"></i>
                                    <h6 class="fw-bold">Manage Applications</h6>
                                    <p class="text-muted small">Review, interview, and hire candidates</p>
                                    <button class="btn btn-warning btn-sm" onclick="window.location.href='applications.php?offer_id=<?= $offerId ?>'">
                                        <i class="bi bi-person-check me-1"></i>Manage
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Job -->
                        <div class="col-md-6">
                            <div class="premium-card h-100">
                                <div class="text-center">
                                    <i class="bi bi-pencil display-4 text-secondary mb-3"></i>
                                    <h6 class="fw-bold">Edit Job Details</h6>
                                    <p class="text-muted small">Update job description, requirements, or details</p>
                                    <button class="btn btn-secondary btn-sm" onclick="window.location.href='edit_offer.php?id=<?= $offerId ?>'">
                                        <i class="bi bi-pencil-square me-1"></i>Edit Job
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Close/Reactivate Job -->
                        <div class="col-md-6">
                            <div class="premium-card h-100">
                                <div class="text-center">
                                    <?php if ($offer['status'] === 'active'): ?>
                                        <i class="bi bi-x-circle display-4 text-danger mb-3"></i>
                                        <h6 class="fw-bold">Close Job Posting</h6>
                                        <p class="text-muted small">Stop accepting new applications</p>
                                        <button class="btn btn-outline-danger btn-sm" onclick="confirmToggleJobStatus('close')">
                                            <i class="bi bi-stop-circle me-1"></i>Close Job
                                        </button>
                                    <?php else: ?>
                                        <i class="bi bi-check-circle display-4 text-success mb-3"></i>
                                        <h6 class="fw-bold">Reactivate Job Posting</h6>
                                        <p class="text-muted small">Start accepting applications again</p>
                                        <button class="btn btn-outline-success btn-sm" onclick="confirmToggleJobStatus('activate')">
                                            <i class="bi bi-play-circle me-1"></i>Reactivate Job
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Share Section -->
                    <div class="mt-4 pt-4 border-top border-secondary">
                        <h6 class="mb-3">Quick Share Link</h6>
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   id="jobShareLink" 
                                   value="<?= "https://$_SERVER[HTTP_HOST]/Public/job_view.php?id=$offerId" ?>" 
                                   readonly>
                            <button class="btn btn-outline-primary" onclick="copyJobLink()">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">Share this link with potential candidates or on job boards</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle me-2"></i>
                    <span id="toastMessage">Action completed successfully!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Stagger animation for cards
            const cards = document.querySelectorAll('.premium-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            const interactiveElements = document.querySelectorAll('.application-item, .skill-tag, .action-btn');
            interactiveElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                element.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });

        function copyJobLink() {
            const linkInput = document.getElementById('jobShareLink');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            
            navigator.clipboard.writeText(linkInput.value).then(() => {
                showToast('Job link copied to clipboard!');
            }).catch(() => {
                // Fallback for older browsers
                document.execCommand('copy');
                showToast('Job link copied to clipboard!');
            });
        }

        function shareJob(platform) {
            const jobTitle = <?= json_encode($offer['title']) ?>;
            const companyName = <?= json_encode($offer['company_name']) ?>;
            const jobUrl = encodeURIComponent(`https://${window.location.host}/Public/job_view.php?id=<?= $offerId ?>`);
            
            let shareUrl = '';
            
            switch(platform) {
                case 'linkedin':
                    shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${jobUrl}`;
                    break;
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${jobUrl}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
                showToast(`Shared on ${platform.charAt(0).toUpperCase() + platform.slice(1)}!`);
            }
        }

        function showToast(message) {
            const toastElement = document.getElementById('successToast');
            const toastMessage = document.getElementById('toastMessage');
            toastMessage.textContent = message;
            
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
        }

        function confirmToggleJobStatus(action) {
            const currentStatus = '<?= $offer['status'] ?>';
            let confirmMessage = '';
            let successMessage = '';
            let newStatus = '';
            
            if (action === 'close') {
                confirmMessage = 'Are you sure you want to close this job posting? This will stop accepting new applications.';
                successMessage = 'Job posting has been closed successfully.';
                newStatus = 'inactive';
            } else {
                confirmMessage = 'Are you sure you want to reactivate this job posting? This will start accepting applications again.';
                successMessage = 'Job posting has been reactivated successfully.';
                newStatus = 'active';
            }
            
            if (confirm(confirmMessage)) {
        
                fetch('toggle_job_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        offer_id: <?= $offerId ?>,
                        new_status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(successMessage);
                        const modal = bootstrap.Modal.getInstance(document.getElementById('actionModal'));
                        modal.hide();
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast('Error updating job status. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error updating job status. Please try again.');
                });
            }
        }

        function printJobDetails() {
            const printContent = `
                <div style="font-family: Inter, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
                    <h1 style="color: #1a1a24; margin-bottom: 10px;"><?= htmlspecialchars($offer['title']) ?></h1>
                    <p style="color: #666; margin-bottom: 20px;"><?= htmlspecialchars($offer['company_name']) ?> • <?= htmlspecialchars($offer['location']) ?></p>
                    
                    <h2 style="color: #1a1a24; margin-top: 30px;">Job Description</h2>
                    <div style="line-height: 1.6; color: #333;">
                        <?= nl2br(htmlspecialchars($offer['description'])) ?>
                    </div>
                    
                    <?php if (!empty($skills)): ?>
                    <h2 style="color: #1a1a24; margin-top: 30px;">Required Skills</h2>
                    <div style="margin-top: 10px;">
                        <?php foreach ($skills as $skill): ?>
                            <span style="display: inline-block; background: #f0f0f0; padding: 5px 10px; margin: 2px; border-radius: 15px; font-size: 14px;"><?= htmlspecialchars($skill) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <p><strong>Posted:</strong> <?= $createdDate ?></p>
                        <p><strong>Job ID:</strong> #ML-<?= str_pad($offerId, 6, '0', STR_PAD_LEFT) ?></p>
                        <p><strong>Applications:</strong> <?= $applicationCount ?></p>
                    </div>
                </div>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title><?= htmlspecialchars($offer['title']) ?> - Job Details</title>
                    <style>
                        body { margin: 0; padding: 20px; font-family: Inter, sans-serif; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    ${printContent}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const modal = new bootstrap.Modal(document.getElementById('actionModal'));
                modal.show();
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printJobDetails();
            }
        });

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        setInterval(async function() {
            try {
                const response = await fetch(`get_application_count.php?offer_id=<?= $offerId ?>`);
                const data = await response.json();
                
                if (data.count !== undefined) {

                    const countElements = document.querySelectorAll('.application-count');
                    countElements.forEach(el => {
                        el.textContent = data.count + (data.count === 1 ? ' application' : ' applications');
                    });
                }
            } catch (error) {
                console.log('Failed to refresh application count:', error);
            }
        }, 30000);
    </script>
    
    <script src="Assets/Js/main.js"></script>
    <?php include __DIR__ . '/Includes/footer.php'; ?>
</body>
</html>
