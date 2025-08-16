<?php
session_start();
$baseURL = '/MagLine/Public';
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}

$recruiterId = $_SESSION['user_id'];
global $pdo;

$headerProfilePicture = '../Public/Assets/default-avatar.png';
$headerManagerName = 'Recruiter';

try {
    $stmtUser = $pdo->prepare("SELECT name, photo FROM users WHERE id = ?");
    $stmtUser->execute([$recruiterId]); 
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($currentUser) {
        $headerManagerName = htmlspecialchars($currentUser['name'] ?? 'Recruiter');
        $headerProfilePicture = !empty($currentUser['photo'])
            ? '../Public/Uploads/profile_pictures/' . htmlspecialchars($currentUser['photo'])
            : '../Public/Assets/default-user.png';
    }
} catch (PDOException $e) {
    error_log("Error fetching user data in Candidates.php: " . $e->getMessage());
}

$searchTerm = $_GET['search'] ?? '';
$candidates = [];

try {
    $query = "
        SELECT DISTINCT 
            u.id, 
            u.name, 
            u.email,
            u.phone,
            u.linkedin,
            u.photo,
            u.location,
            u.about,
            COUNT(a.id) AS application_count,
            MAX(a.created_at) AS last_application_date
        FROM users u
        JOIN applications a ON a.candidate_id = u.id
        JOIN offers o ON a.offer_id = o.id
        WHERE o.recruiter_id = ?
    ";

    $params = [$recruiterId];

    if (!empty($searchTerm)) {
        $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.location LIKE ?)";
        $searchParam = "%$searchTerm%";
        array_push($params, $searchParam, $searchParam, $searchParam);
    }

    $query .= " GROUP BY u.id ORDER BY last_application_date DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching candidates: " . $e->getMessage());
}

$currentPage = basename($_SERVER['PHP_SELF']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Candidates | MagLine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="../Public/Assets/CSS/main.css">
    <link rel="stylesheet" href="../Public/Assets/CSS/candidate.css">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <style>
        :root {
            --primary-color: #4c2aac;
            --primary-light: #6B46C1;
            --primary-dark: #3B1F7A;
            --dark-bg: #0f0f13;
            --surface-bg: #1A1A23;
            --surface-light: #242432;
            --text-primary: #ffffff;
            --text-secondary: rgba(255,255,255,0.7);
            --text-muted: rgba(255,255,255,0.5);
            --border-color: rgba(255,255,255,0.08);
            --accent-gold: #F59E0B;
            --accent-emerald: #10B981;
            --accent-rose: #F43F5E;
        }
        
        .main-content {
            background: linear-gradient(135deg, var(--dark-bg) 0%, #16161d 50%, var(--dark-bg) 100%);
            min-height: 100vh;
        }
        
        .candidate-item {
            background: linear-gradient(145deg, var(--surface-bg), var(--surface-light));
            border-radius: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .candidate-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-gold), var(--accent-emerald));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .candidate-item:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 
                0 20px 40px rgba(0,0,0,0.3),
                0 0 0 1px rgba(76, 42, 172, 0.2),
                inset 0 1px 0 rgba(255,255,255,0.1);
            border-color: var(--primary-color);
        }
        
        .candidate-item:hover::before {
            opacity: 1;
        }
        
        .candidate-profile-section {
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            position: relative;
        }
        
        .candidate-avatar-container {
            position: relative;
            flex-shrink: 0;
        }
        
        .candidate-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid transparent;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-gold)) padding-box,
                        linear-gradient(45deg, var(--primary-color), var(--accent-gold)) border-box;
            transition: all 0.3s ease;
        }
        
        .candidate-item:hover .candidate-avatar {
            transform: scale(1.1);
            box-shadow: 0 10px 20px rgba(76, 42, 172, 0.3);
        }
        
        .status-indicator {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 18px;
            height: 18px;
            background: var(--accent-emerald);
            border-radius: 50%;
            border: 3px solid var(--surface-bg);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .candidate-info {
            flex: 1;
        }
        
        .candidate-name {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }
        
        .candidate-title {
            color: var(--primary-light);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .candidate-location {
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .metrics-section {
            display: flex;
            padding: 0 2rem 1.5rem 2rem;
            gap: 2rem;
        }
        
        .metric-item {
            flex: 1;
            text-align: center;
            padding: 1rem;
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .metric-item:hover {
            background: rgba(76, 42, 172, 0.1);
            border-color: var(--primary-color);
        }
        
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }
        
        .metric-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .actions-section {
            padding: 1.5rem 2rem 2rem 2rem;
            border-top: 1px solid var(--border-color);
            background: rgba(0,0,0,0.2);
        }
        
        .contact-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .contact-buttons {
            display: flex;
            gap: 0.75rem;
        }
        
        .contact-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: rgba(255,255,255,0.05);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1.1rem;
        }
        
        .contact-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        
        .contact-btn.email:hover {
            background: var(--accent-emerald);
            border-color: var(--accent-emerald);
        }
        
        .contact-btn.phone:hover {
            background: var(--accent-gold);
            border-color: var(--accent-gold);
        }
        
        .contact-btn.linkedin:hover {
            background: #0077B5;
            border-color: #0077B5;
        }
        
        .view-profile-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-profile-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 42, 172, 0.3);
            background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
        }
        
        .search-container {
            background: linear-gradient(145deg, var(--surface-bg), var(--surface-light));
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .search-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-gold));
        }
        
        .search-input {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            color: white;
            border-radius: 12px;
            padding: 0.875rem 1.25rem;
            font-size: 1rem;
        }
        
        .search-input:focus {
            background: rgba(0,0,0,0.5);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(76, 42, 172, 0.25);
        }
        
        .search-input::placeholder {
            color: var(--text-muted);
        }
        
        .input-group-text {
            background: rgba(0,0,0,0.3) !important;
            border-color: var(--border-color) !important;
            border-radius: 12px 0 0 12px !important;
            color: var(--text-secondary);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border: none;
            border-radius: 0 12px 12px 0;
            padding: 0.875rem 1.5rem;
            font-weight: 500;
        }
        
        .btn-outline-secondary {
            border-color: var(--border-color);
            color: var(--text-secondary);
            border-radius: 12px;
            margin-left: 0.5rem;
        }
        
        .empty-state {
            background: linear-gradient(145deg, var(--surface-bg), var(--surface-light));
            border-radius: 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }
        
        .page-header {
            background: linear-gradient(145deg, var(--surface-bg), var(--surface-light));
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-gold), var(--accent-emerald));
        }
        
        .stats-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 12px;
            padding: 0.75rem 1.25rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/Recruiter_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Recruiter_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid py-4 px-4">
                <div class="page-header animate__animated animate__fadeIn">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2 d-flex align-items-center">
                                <i class="bi bi-people-fill me-3" style="color: var(--primary-color);"></i>
                                Talent Portfolio
                            </h2>
                            <p class="text-muted mb-0 fs-6">
                                Candidates who applied to your opportunities
                            </p>
                        </div>
                        <div class="stats-badge">
                            <i class="bi bi-people me-2"></i>
                            <?= count($candidates) ?> candidates
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
                                <i class="bi <?= $_SESSION['flash_message']['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                                <?= $_SESSION['flash_message']['message'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['flash_message']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="search-container animate__animated animate__fadeIn">
                    <form method="GET" class="d-flex">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control search-input" 
                                   placeholder="Search talent by name, email, or location..." 
                                   value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search me-1"></i> Search
                            </button>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="candidates.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <?php if (empty($candidates)): ?>
                    <div class="empty-state animate__animated animate__fadeIn">
                        <div class="empty-state-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="mb-3">No Candidates Found</h3>
                        <p class="text-muted mb-4 fs-5">
                            <?= empty($searchTerm) 
                                ? "Your talent pipeline is waiting to be filled." 
                                : "No candidates match your search criteria." ?>
                        </p>
                        <?php if (empty($searchTerm)): ?>
                            <a href="Post_offer.php" class="btn btn-primary btn-lg px-4">
                                <i class="bi bi-plus-circle me-2"></i>Create Job Opportunity
                            </a>
                        <?php else: ?>
                            <a href="candidates.php" class="btn btn-primary btn-lg px-4">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Reset Search
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="candidates-list">
                        <?php foreach ($candidates as $index => $c): 
                            $candidatePhoto = !empty($c['photo'])
                                ? '../Public/Uploads/profile_photos/' . $c['photo']
                                : '../Public/Assets/default-user.png';
                            $animationDelay = ($index * 0.1) . 's';
                        ?>
                            <div class="candidate-item animate__animated animate__fadeInUp" 
                                 style="animation-delay: <?= $animationDelay ?>;">
                                
                                <!-- Profile Section -->
                                <div class="candidate-profile-section">
                                    <div class="candidate-avatar-container">
                                        <img src="<?= htmlspecialchars($candidatePhoto) ?>" 
                                             alt="<?= htmlspecialchars($c['name']) ?>" 
                                             class="candidate-avatar"
                                             onerror="this.onerror=null;this.src='../Public/Assets/default-user.png'">
                                        <div class="status-indicator" data-bs-toggle="tooltip" title="Active Candidate"></div>
                                    </div>
                                    
                                    <div class="candidate-info">
                                        <h4 class="candidate-name"><?= htmlspecialchars($c['name']) ?></h4>
                                        <div class="candidate-title"><?= htmlspecialchars($c['email']) ?></div>
                                        <?php if (!empty($c['location'])): ?>
                                            <div class="candidate-location">
                                                <i class="bi bi-geo-alt-fill"></i>
                                                <?= htmlspecialchars($c['location']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Metrics Section -->
                                <div class="metrics-section">
                                    <div class="metric-item">
                                        <div class="metric-value"><?= (int)$c['application_count'] ?></div>
                                        <div class="metric-label">Applications</div>
                                    </div>
                                    <div class="metric-item">
                                        <div class="metric-value">
                                            <?= $c['phone'] ? '✓' : '—' ?>
                                        </div>
                                        <div class="metric-label">Phone Available</div>
                                    </div>
                                    <div class="metric-item">
                                        <div class="metric-value">
                                            <?= $c['last_application_date'] ? date('M j', strtotime($c['last_application_date'])) : '—' ?>
                                        </div>
                                        <div class="metric-label">Last Applied</div>
                                    </div>
                                </div>
                                
                                <!-- Actions Section -->
                                <div class="actions-section">
                                    <div class="contact-actions">
                                        <div class="contact-buttons">
                                            <a href="mailto:<?= htmlspecialchars($c['email']) ?>" 
                                               class="contact-btn email"
                                               data-bs-toggle="tooltip" 
                                               title="Send Email">
                                                <i class="bi bi-envelope-fill"></i>
                                            </a>
                                            <?php if ($c['phone']): ?>
                                                <a href="tel:<?= htmlspecialchars($c['phone']) ?>" 
                                                   class="contact-btn phone"
                                                   data-bs-toggle="tooltip" 
                                                   title="Call Candidate">
                                                    <i class="bi bi-telephone-fill"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($c['linkedin']): ?>
                                                <a href="<?= htmlspecialchars($c['linkedin']) ?>" 
                                                   target="_blank"
                                                   class="contact-btn linkedin"
                                                   data-bs-toggle="tooltip" 
                                                   title="View LinkedIn Profile">
                                                    <i class="bi bi-linkedin"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <a href="view_profile.php?user_id=<?= $c['id'] ?>" 
                                           class="view-profile-btn">
                                            <i class="bi bi-person-circle"></i>
                                            View Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
 
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.candidate-item').forEach(card => {
                observer.observe(card);
            });
        });
    </script>
    
    <?php include __DIR__ . '/Includes/footer.php'; ?>
</body>
</html>
