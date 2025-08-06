<?php
session_start();
$baseURL = '/MagLine/Public';
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}
// In your common include file or config
$headerManagerName = $headerManagerName ?? $_SESSION['user_name'] ?? 'User';
$recruiterId = $_SESSION['user_id'];
global $pdo;

// Fetch recruiter data
$recruiterData = [];
try {
    $stmt = $pdo->prepare("SELECT company_name, company_logo FROM users WHERE id = ?");
    $stmt->execute([$recruiterId]);
    $recruiterData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recruiter data: " . $e->getMessage());
}

// Fetch reports data
$reportsData = [
    'total_offers' => 0,
    'total_applications' => 0,
    'active_offers' => 0,
    'recent_applications' => 0,
    'applications_chart' => [],
    'status_chart' => [],
    'recent_activities' => []
];

try {
    // Total offers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE recruiter_id = ?");
    $stmt->execute([$recruiterId]);
    $reportsData['total_offers'] = $stmt->fetchColumn();

    // Total applications
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a INNER JOIN offers o ON a.offer_id = o.id WHERE o.recruiter_id = ?");
    $stmt->execute([$recruiterId]);
    $reportsData['total_applications'] = $stmt->fetchColumn();

    // Active offers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE recruiter_id = ? AND status = 'active'");
    $stmt->execute([$recruiterId]);
    $reportsData['active_offers'] = $stmt->fetchColumn();

    // Recent applications (last 7 days)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a INNER JOIN offers o ON a.offer_id = o.id WHERE o.recruiter_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$recruiterId]);
    $reportsData['recent_applications'] = $stmt->fetchColumn();

    // Applications chart data (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(a.created_at, '%Y-%m') AS month,
            COUNT(*) AS count
        FROM applications a
        INNER JOIN offers o ON a.offer_id = o.id
        WHERE o.recruiter_id = ?
        AND a.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(a.created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$recruiterId]);
    $reportsData['applications_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Offer status chart data
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) AS count
        FROM offers
        WHERE recruiter_id = ?
        GROUP BY status
    ");
    $stmt->execute([$recruiterId]);
    $reportsData['status_chart'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent activities (last 5 activities)
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.candidate_name,
            o.title AS offer_title,
            a.created_at,
            a.status
        FROM applications a
        INNER JOIN offers o ON a.offer_id = o.id
        WHERE o.recruiter_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$recruiterId]);
    $reportsData['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching reports data: " . $e->getMessage());
}

// Set $currentPage for sidebar active state
$currentPage = basename($_SERVER['PHP_SELF']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Analytics | <?= htmlspecialchars($recruiterData['company_name'] ?? 'MagLine') ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.5.95/css/materialdesignicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet"> 
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
 <style>
    .page-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 2rem;
}

.page-title {
    margin-bottom: 0.5rem;
}

.page-subtitle {
    color: #6c757d;
    font-size: 1rem;
    margin-top: 0;
    text-align: center;
    max-width: 600px; /* Optional: limit width for better readability */
}</style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/Recruiter_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Recruiter_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid py-4">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
                        <?= $_SESSION['flash_message']['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>
                
                <div class="reports-container">
                    <!-- Page Header -->
                    <div class="page-header mb-4">
                        <div>
                            <h1 class="page-title">
                                <i class="mdi mdi-chart-line me-2"></i>Recruitment Analytics
                            </h1>
                            <p class="page-subtitle">
                                Key metrics and insights about your recruitment activities
                            </p>
                        </div>
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="stats-grid mb-4">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary-light">
                                <i class="mdi mdi-briefcase-outline text-primary"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-title">Total Job Offers</h3>
                                <h2 class="stat-value"><?= $reportsData['total_offers'] ?></h2>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon bg-success-light">
                                <i class="mdi mdi-file-document-outline text-success"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-title">Total Applications</h3>
                                <h2 class="stat-value"><?= $reportsData['total_applications'] ?></h2>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon bg-warning-light">
                                <i class="mdi mdi-flash-outline text-warning"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-title">Active Offers</h3>
                                <h2 class="stat-value"><?= $reportsData['active_offers'] ?></h2>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon bg-info-light">
                                <i class="mdi mdi-calendar-blank-outline text-info"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-title">Recent Applications</h3>
                                <h2 class="stat-value"><?= $reportsData['recent_applications'] ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Section -->
                    <div class="charts-section mb-4">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Applications Overview</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="applicationsChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Offer Status</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="offerStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
       <script>
        // Applications Chart
        const applicationsCtx = document.getElementById('applicationsChart').getContext('2d');
        const applicationsChart = new Chart(applicationsCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($reportsData['applications_chart'], 'month')) ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?= json_encode(array_column($reportsData['applications_chart'], 'count')) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Offer Status Chart
        const statusCtx = document.getElementById('offerStatusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($reportsData['status_chart'], 'status')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($reportsData['status_chart'], 'count')) ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    </script>
       
    <script src="Assets/Js/main.js"></script>
    
    <?php include __DIR__ . '/Includes/footer.php'; ?>
</body>
</html>