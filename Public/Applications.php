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

// Handle application deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application'])) {
    $applicationId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    
    if ($applicationId) {
        try {
            // Verify the application belongs to this recruiter
            $stmt = $pdo->prepare("
                DELETE a FROM applications a
                JOIN offers o ON a.offer_id = o.id
                WHERE a.id = ? AND o.recruiter_id = ?
            ");
            $stmt->execute([$applicationId, $recruiterId]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => 'Application deleted successfully'
                ];
            }
        } catch (PDOException $e) {
            error_log("Error deleting application: " . $e->getMessage());
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Failed to delete application'
            ];
        }
        
        header("Location: Applications.php");
        exit;
    }
}

// --- Header Data ---
$headerProfilePicture = $baseURL . '/Assets/default-avatar.png';
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
    error_log("Error fetching user data in Applications.php: " . $e->getMessage());
}

// --- Fetch Applications ---
$applications = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.status, a.created_at, u.name AS candidate_name, 
               o.title AS offer_title, u.email AS candidate_email,
               u.id AS candidate_id, u.photo AS candidate_photo,
               u.phone, u.linkedin, u.location, u.about
        FROM applications a
        JOIN users u ON a.candidate_id = u.id
        JOIN offers o ON a.offer_id = o.id
        WHERE o.recruiter_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$recruiterId]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching applications: " . $e->getMessage());
}

// --- Set $currentPage for sidebar active state ---
$currentPage = basename($_SERVER['PHP_SELF']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applications | MagLine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Public/Assets/CSS/main.css">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    
    <style>
        .application-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: visible;
            height: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
        }
        
        .application-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .badge-status {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
        }
        
        .status-pending {
            color: #ffc107;
        }
        
        .status-reviewed {
            color: #17a2b8;
        }
        
        .status-interview {
            color: #6610f2;
        }
        
        .status-rejected {
            color: #dc3545;
        }
        
        .status-hired {
            color: #28a745;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: rgba(255,255,255,0.2);
            margin-bottom: 1.5rem;
        }
        
        .application-actions .btn {
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .application-actions .btn:hover {
            opacity: 1;
        }
        
        /* Updated styles for better text visibility */
        .application-details h6 {
            color: rgba(255,255,255,0.7) !important;
            margin-bottom: 0.5rem;
        }
        
        .application-details p {
            color: rgba(255,255,255,0.9);
            margin-bottom: 1rem;
        }
        
        .application-date {
            color: rgba(255, 255, 255, 0.82) !important;     
        }
        
        .candidate-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4c2aac;
            margin-right: 1rem;
        }
        
        .candidate-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .candidate-info {
            flex: 1;
        }
        
    .delete-application {
        position: absolute;
        top: -10px;
        right: -10px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background-color: #dc3545;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.3s;
        border: none;
        z-index: 1000;
        font-size: 16px;
        font-weight: bold;
        padding: 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
.delete-application::before {
    content: '';
    position: absolute;
    top: -5px;
    left: -5px;
    right: -5px;
    bottom: -5px;
    border-radius: 50%;
    z-index: -1;
}

.delete-application .btn-close {
    margin: 0; /* Remove any margin from the close button */
    font-size: 0.8rem; /* Adjust the size of the X */
    padding: 0.5rem; /* Add some padding to make it easier to click */
}
        
        .application-card:hover .delete-application {
            opacity: 1;
        }
        
        .delete-application:hover {
            background-color: #c82333;
        }

        .status-interview { background-color: rgba(9, 132, 227, 0.2); color: var(--info-color); }
    </style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/Recruiter_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Recruiter_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid py-4">
                <div class="applications-container p-4">
                    <div class="page-header d-flex justify-content-between align-items-center mb-4">
                        <h1 class="page-title">
                            <i class="bi bi-people-fill me-2"></i>Applications
                        </h1>
                        <div class="applications-count">
                            <span class="badge bg-primary"><?= count($applications) ?> Total</span>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
                            <i class="bi <?= $_SESSION['flash_message']['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                            <?= $_SESSION['flash_message']['message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['flash_message']); ?>
                    <?php endif; ?>

                    <?php if (empty($applications)): ?>
                        <div class="empty-state animate__animated animate__fadeIn">
                            <i class="bi bi-person-x empty-state-icon"></i>
                            <h3 class="empty-state-title">No Applications Yet</h3>
                            <p class="empty-state-text">You haven't received any applications yet. Promote your job offers to attract candidates.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($applications as $app): 
                                // Determine candidate photo
                                $candidatePhoto = !empty($app['candidate_photo'])
                                    ? '../Public/Uploads/profile_photos/' . $app['candidate_photo']
                                    : '../Public/Assets/default-user.png';
                            ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="application-card p-3 h-100">
                                        <!-- Delete button -->
                                        <form method="post" class="delete-application" 
      onsubmit="return confirm('Are you sure you want to delete this application?');">
    <input type="hidden" name="delete_application" value="1">
    <input type="hidden" name="application_id" value="<?= htmlspecialchars($app['id']) ?>">
    <button type="submit" class="btn-close btn-close-white" aria-label="Delete"></button>
</form>
                                        
                                        <div class="candidate-header">
                                            <img src="<?= htmlspecialchars($candidatePhoto) ?>" 
                                                 alt="<?= htmlspecialchars($app['candidate_name']) ?>" 
                                                 class="candidate-photo"
                                                 onerror="this.onerror=null;this.src='../Public/Assets/default-user.png'">
                                            <div class="candidate-info">
                                                <h5 class="mb-0"><?= htmlspecialchars($app['candidate_name']) ?></h5>
                                                <div class="application-date text-muted small">
                                                    <?= date('M j, Y', strtotime($app['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="application-details mb-3">
                                            <h6 class="text-muted mb-1">Applied for:</h6>
                                            <p class="mb-2"><?= htmlspecialchars($app['offer_title']) ?></p>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge rounded-pill status-<?= htmlspecialchars($app['status']) ?>">
                                                    <?= ucfirst(htmlspecialchars($app['status'])) ?>
                                                </span>
                                                
                                                <div class="application-actions">
                                                    <a href="mailto:<?= htmlspecialchars($app['candidate_email']) ?>" 
                                                       class="btn btn-sm btn-outline-primary me-1"
                                                       data-bs-toggle="tooltip" 
                                                       title="Contact candidate">
                                                        <i class="bi bi-envelope"></i>
                                                    </a>
                                                    <a href="view_profile.php?user_id=<?= htmlspecialchars($app['candidate_id']) ?>" 
                                                       class="btn btn-sm btn-outline-primary me-1"
                                                       data-bs-toggle="tooltip" 
                                                       title="View profile">
                                                        <i class="bi bi-person"></i>
                                                    </a>
                                                    <a href="application_detail.php?id=<?= htmlspecialchars($app['id']) ?>&candidate_id=<?= htmlspecialchars($app['candidate_id']) ?>" 
                                                       class="btn btn-sm btn-outline-secondary"
                                                       data-bs-toggle="tooltip" 
                                                       title="View details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    <?php include __DIR__ . '/Includes/footer.php'; ?>
</body>
</html>