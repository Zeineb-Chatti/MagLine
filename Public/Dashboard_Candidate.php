<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

// Initialize all variables with safe defaults
$candidateId = $_SESSION['user_id'] ?? null;
$candidateName = 'Candidate';
$displayName = 'Candidate';
$totalApplications = 0;
$recentApplications = [];
$latestJobs = [];
$statusDistribution = [];
$headerManagerName = 'Candidate';

// Strict authentication check
if (!$candidateId || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'candidate') {
    header("Location: Login.php");
    exit;
}

try {
    // 1. Get user data
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$candidateId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userData && !empty($userData['name'])) {
        $candidateName = $userData['name'];
        $_SESSION['user_name'] = $candidateName;
        
        // Extract first name
        $nameParts = explode(' ', $candidateName);
        $displayName = $nameParts[0];
        $headerManagerName = $displayName;
    }

    // 2. Count total applications
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE candidate_id = ?");
    $stmt->execute([$candidateId]);
    $totalApplications = (int)$stmt->fetchColumn();

    // 3. Get recent applications
$stmt = $pdo->prepare(
    "SELECT o.id, o.title, u.company_name AS company_name, a.status, a.created_at as application_date
     FROM applications a
     JOIN offers o ON a.offer_id = o.id
     JOIN users u ON o.recruiter_id = u.id
     WHERE a.candidate_id = ?
     ORDER BY a.created_at DESC
     LIMIT 5"
);

$stmt->execute([$candidateId]);
$recentApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Get latest jobs - CRITICAL FIX HERE
$stmt = $pdo->prepare(
    "SELECT o.id, o.title, u.company_name AS company_name, o.created_at AS posted_date
     FROM offers o
     JOIN users u ON o.recruiter_id = u.id
     WHERE o.status = 'active' AND o.deleted_at IS NULL
     ORDER BY o.created_at DESC
     LIMIT 5"
);

$stmt->execute();
$latestJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // 5. Get status distribution
    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) as count 
        FROM applications 
        WHERE candidate_id = ?
        GROUP BY status"
    );
    $stmt->execute([$candidateId]);
    $statusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $_SESSION['flash_error'] = "Unable to load some dashboard data. Please try again later.";
    
    // Fallback to session data if available
    if (isset($_SESSION['user_name'])) {
        $nameParts = explode(' ', $_SESSION['user_name']);
        $displayName = $nameParts[0];
        $headerManagerName = $displayName;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($displayName) ?>'s Dashboard | MagLine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Public/Assets/CSS/main.css">
    <link rel="stylesheet" href="../Public/Assets/CSS/candidate.css">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dark-theme">
    <?php 
        $headerManagerName = $candidateName;
        include __DIR__ . '/Includes/Candidate_header.php'; 
    ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Candidate_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>Welcome back, <?= htmlspecialchars($displayName) ?>!</h2>
                        <p class="company-name">Job Seeker Dashboard</p>
                    </div>
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h3><?= $totalApplications ?></h3>
                    <p>Total Applications</p>
                    <a href="Candidate_applications.php" class="btn btn-primary">
                        <i class="bi bi-eye me-2"></i> View All
                    </a>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-briefcase"></i>
                    </div>
                    <h3><?= count($latestJobs) ?></h3>
                    <p>New Job Offers</p>
                    <a href="jobs.php" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i> Browse Jobs
                    </a>
                </div>
            </div>

            <div class="content-row">
                <div class="content-card">
                    <div class="card-header">
                        <h4><i class="bi bi-pie-chart me-2"></i>Application Status</h4>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart" height="250"></canvas>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h4><i class="bi bi-briefcase me-2"></i>Recent Job Offers</h4>
                        <span class="badge"><?= count($latestJobs) ?></span>
                    </div>
                    <?php if (!empty($latestJobs)): ?>
                        <div class="list-group">
                            <?php foreach ($latestJobs as $job): ?>
                                <div class="list-item">
                                    <div class="item-content">
                                        <h5><?= htmlspecialchars($job['title']) ?></h5>
                                        <p><?= htmlspecialchars($job['company_name']) ?></p>
                                        <small>Posted on <?= date('M j, Y', strtotime($job['posted_date'])) ?></small>
                                    </div>
                                    <a href="Candidate_Job_detail.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-briefcase"></i>
                            <p>No job offers available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-row mt-4">
                <div class="content-card full-width">
                    <div class="card-header">
                        <h4><i class="bi bi-file-earmark-text me-2"></i>Recent Applications</h4>
                        <span class="badge"><?= count($recentApplications) ?></span>
                    </div>
                    <?php if (!empty($recentApplications)): ?>
                        <div class="list-group">
                            <?php foreach ($recentApplications as $app): ?>
                                <div class="list-item">
                                    <div class="item-content">
                                        <h5><?= htmlspecialchars($app['title']) ?></h5>
                                        <p><?= htmlspecialchars($app['company_name']) ?></p>
                                        <small>Applied on <?= date('M j, Y', strtotime($app['application_date'])) ?></small>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $app['status'])) ?>">
                                            <?= htmlspecialchars(ucfirst($app['status'])) ?>
                                        </span>
                                    </div>
                                    <a href="Candidate_Job_detail.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-file-earmark-excel"></i>
                            <p>No applications yet</p>
                            <a href="jobs.php" class="btn btn-primary">Browse Jobs</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/Includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../Public/Assets/Js/main.js"></script>
    
    <script>
   // Professional Status-Based Chart Colors
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('statusChart').getContext('2d');
    const statusData = <?= json_encode($statusDistribution) ?>;
    
    const labels = statusData.map(item => item.status);
    const data = statusData.map(item => item.count);
    
    // Smart color mapping based on status
    function getStatusColor(status) {
        const statusLower = status.toLowerCase();
        
        // Success/Positive states
        if (statusLower.includes('accept') || statusLower.includes('hired') || 
            statusLower.includes('approved') || statusLower.includes('selected')) {
            return 'rgba(16, 185, 129, 0.85)';
        }
        
        // Progress/Active states
        if (statusLower.includes('interview') || statusLower.includes('shortlist') || 
            statusLower.includes('reviewing') || statusLower.includes('in_review') ||
            statusLower.includes('in review') || statusLower.includes('second round')) {
            return 'rgba(79, 172, 254, 0.85)';
        }
        
        // Waiting/Pending states
        if (statusLower.includes('pending') || statusLower.includes('waiting') || 
            statusLower.includes('submitted') || statusLower.includes('applied') ||
            statusLower.includes('under review')) {
            return 'rgba(245, 158, 11, 0.85)';
        }
        
        // Negative states
        if (statusLower.includes('reject') || statusLower.includes('declined') || 
            statusLower.includes('unsuccessful') || statusLower.includes('not selected')) {
            return 'rgba(239, 68, 68, 0.75)';
        }
        
        // Withdrawn/Inactive
        if (statusLower.includes('withdrawn') || statusLower.includes('cancelled') || 
            statusLower.includes('expired') || statusLower.includes('inactive')) {
            return 'rgba(139, 92, 246, 0.75)';
        }
        
        // Default
        return 'rgba(6, 182, 212, 0.85)';
    }
    
    function getBorderColor(bgColor) {
        return bgColor.replace(/0\.\d+/, '1');
    }
    
    function getHoverColor(bgColor) {
        return bgColor.replace(/0\.\d+/, '0.95');
    }
    
    // Generate colors based on status values
    const backgroundColors = labels.map(status => getStatusColor(status));
    const borderColors = backgroundColors.map(color => getBorderColor(color));
    const hoverColors = backgroundColors.map(color => getHoverColor(color));
    
    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 3,
                hoverBorderWidth: 4,
                hoverBackgroundColor: hoverColors,
                borderRadius: 8,
                spacing: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 25,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: {
                            family: 'Inter, Poppins, sans-serif',
                            size: 14,
                            weight: '500'
                        },
                        color: '#e0e7ff',
                        boxWidth: 12,
                        boxHeight: 12,
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => {
                                    const dataset = data.datasets[0];
                                    const value = dataset.data[i];
                                    const total = dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    
                                    return {
                                        text: `${label.charAt(0).toUpperCase() + label.slice(1)} (${percentage}%)`,
                                        fillStyle: dataset.backgroundColor[i],
                                        strokeStyle: dataset.borderColor[i],
                                        lineWidth: 2,
                                        pointStyle: 'circle',
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                            return [];
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(10, 10, 42, 0.85)',
                    titleColor: '#e0e7ff',
                    bodyColor: '#8a9eff',
                    borderColor: 'rgba(124, 77, 255, 0.4)',
                    borderWidth: 2,
                    cornerRadius: 15,
                    padding: 16,
                    displayColors: true,
                    titleFont: {
                        family: 'Inter, Poppins, sans-serif',
                        size: 15,
                        weight: '600'
                    },
                    bodyFont: {
                        family: 'Inter, Poppins, sans-serif',
                        size: 13,
                        weight: '400'
                    },
                    callbacks: {
                        title: function(context) {
                            return `${context[0].label} Applications`;
                        },
                        label: function(context) {
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${value} applications (${percentage}%)`;
                        },
                        labelColor: function(context) {
                            return {
                                borderColor: context.dataset.borderColor[context.dataIndex],
                                backgroundColor: context.dataset.backgroundColor[context.dataIndex],
                                borderWidth: 2,
                                borderRadius: 4
                            };
                        }
                    }
                }
            },
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 2000,
                easing: 'easeInOutCubic',
                delay: (context) => {
                    return context.dataIndex * 200;
                }
            },
            hover: {
                animationDuration: 400,
                intersect: false,
                mode: 'nearest'
            },
            onHover: (event, activeElements) => {
                event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                
                if (activeElements.length > 0) {
                    chart.canvas.style.filter = 'drop-shadow(0 0 20px rgba(124, 77, 255, 0.4))';
                } else {
                    chart.canvas.style.filter = 'none';
                }
            }
        }
    });
    
    // Post-render glow effect
    const originalDraw = chart.draw;
    chart.draw = function() {
        const ctx = this.ctx;
        ctx.save();
        ctx.shadowColor = 'rgba(124, 77, 255, 0.3)';
        ctx.shadowBlur = 20;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;
        originalDraw.call(this);
        ctx.restore();
    };
    
    // Subtle pulsing animation for pending statuses
    let pulseDirection = 1;
    let pulseIntensity = 0.85;
    
    setInterval(() => {
        pulseIntensity += 0.015 * pulseDirection;
        if (pulseIntensity >= 0.95 || pulseIntensity <= 0.75) {
            pulseDirection *= -1;
        }
        
        chart.data.datasets[0].backgroundColor = labels.map((label, i) => {
            const statusLower = label.toLowerCase();
            if (statusLower.includes('pending') || statusLower.includes('waiting') || 
                statusLower.includes('submitted')) {
                return getStatusColor(label).replace(/0\.\d+/, pulseIntensity.toFixed(2));
            }
            return getStatusColor(label);
        });
        
        chart.update('none');
    }, 150);
});
    </script>
</body>
</html>