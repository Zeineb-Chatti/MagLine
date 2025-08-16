<?php
session_start();
$baseURL = '/MagLine/Public';
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: Login.php");
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT name, company_name, company_logo, photo FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        error_log("User data not found for ID: " . $userId . " during dashboard load. Logging out.");
        header("Location: ../Auth/logout.php?error=user_data_missing");
        exit;
    }

    $fullName = $user['name'] ?? 'Recruiter';
    $firstName = explode(' ', $fullName)[0];
    $managerName = htmlspecialchars($firstName); 
    $companyName = htmlspecialchars($user['company_name'] ?? 'Your Company');

    $companyLogo = !empty($user['company_logo']) 
        ? '../Public/Uploads/Company_Logos/' . htmlspecialchars($user['company_logo'])
        : '../Public/Assets/default-company.png'; // Default logo

    $profilePicture = !empty($user['photo']) 
        ? '../Public/Uploads/profile_pictures/' . htmlspecialchars($user['photo'])
        : '../Public/Assets/default-user.png'; // Default avatar
        
} catch (PDOException $e) {
    error_log("Database error fetching recruiter info: " . $e->getMessage());
    die("Database Error (Recruiter Info): " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE recruiter_id = ? AND deleted_at IS NULL");
    $stmt->execute([$userId]);
    $offerCount = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN offers o ON a.offer_id = o.id WHERE o.recruiter_id = ? AND o.deleted_at IS NULL");
    $stmt->execute([$userId]);
    $appCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, title, created_at FROM offers WHERE recruiter_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
    $stmt = $pdo->prepare("
        SELECT a.id, a.status, a.created_at, u.name AS candidate_name, o.title AS offer_title 
        FROM applications a
        JOIN users u ON a.candidate_id = u.id
        JOIN offers o ON a.offer_id = o.id
        WHERE o.recruiter_id = ? AND o.deleted_at IS NULL
        ORDER BY a.created_at DESC LIMIT 5
    ");
    $stmt->execute([$userId]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    die("Dashboard Stats Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $managerName ?>'s Dashboard | MagLine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Assets/CSS/main.css">
    <link rel="icon" href="Assets/favicon.png" type="image/x-icon">
    <style>
        .status-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    display: inline-block !important;
}

</style>
</head>
<body class="dark-theme">
    <?php 
        $headerProfilePicture = $profilePicture;
        $headerManagerName = $managerName;
        include __DIR__ . '/Includes/recruiter_header.php'; 
    ?>
    
    <div class="main-wrapper">
        <?php 
           
            include __DIR__ . '/Includes/recruiter_sidebar.php'; 
        ?>
        
        <main class="main-content">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>Welcome back, <?= $managerName ?>!</h2>
                        <p class="company-name"><?= $companyName ?></p>
                    </div>
                    <img src="<?= $companyLogo ?>" alt="Company Logo" class="company-logo">
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-briefcase"></i>
                    </div>
                    <h3><?= $offerCount ?></h3>
                    <p>Job Offers</p>
                    <a href="Post_offer.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i> Post New
                    </a>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h3><?= $appCount ?></h3>
                    <p>Applications</p>
                    <a href="applications.php" class="btn btn-primary">
                        <i class="bi bi-eye me-2"></i> View All
                    </a>
                </div>
            </div>

            <div class="content-row">
                <div class="content-card">
                    <div class="card-header">
                        <h4><i class="bi bi-briefcase me-2"></i>Recent Job Offers</h4>
                        <span class="badge"><?= count($offers) ?></span>
                    </div>
                    <?php if (!empty($offers)): ?>
                        <div class="list-group">
                            <?php foreach ($offers as $offer): ?>
                                <div class="list-item">
                                    <div class="item-content">
                                        <h5><?= htmlspecialchars($offer['title']) ?></h5>
                                        <small>Posted on <?= date('M j, Y', strtotime($offer['created_at'])) ?></small>
                                    </div>
                                    <a href="offer_detail.php?id=<?= $offer['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-briefcase"></i>
                            <p>No job offers yet</p>
                            <a href="Post_offer.php" class="btn btn-primary">Post Your First Job</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="content-card">
    <div class="card-header">
        <h4><i class="bi bi-file-earmark-text me-2"></i>Recent Applications</h4>
        <span class="badge"><?= count($applications) ?></span>
    </div>
    <?php if (!empty($applications)): ?>
        <div class="list-group">
            <?php foreach ($applications as $app): ?>
                <div class="list-item d-flex justify-content-between align-items-center">
                    <div class="item-content flex-grow-1">
                        <h5><?= htmlspecialchars($app['candidate_name']) ?></h5>
                        <p class="mb-1">Applied for <?= htmlspecialchars($app['offer_title']) ?></p>
                        <small class="application-date"><?= date('M j, Y', strtotime($app['created_at'])) ?></small>
                    </div>
                    <div class="status-container">
                        <span class="status-badge status-<?= strtolower($app['status']) ?>">
                            <?= ucfirst(str_replace('_', ' ', $app['status'])) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-file-earmark-excel"></i>
            <p>No applications yet</p>
        </div>
    <?php endif; ?>
</div>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/Includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Assets/Js/main.js"></script>
</body>
</html>
