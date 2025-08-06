<?php
session_start();
$baseURL = '/MagLine/Public';
require_once __DIR__ . '/../Config/database.php';

// Auth check
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}
$recruiterId = $_SESSION['user_id'];
global $pdo;

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $offerId = (int)$_POST['delete_id'];
    try {
        $stmt = $pdo->prepare("SELECT id FROM offers WHERE id = ? AND recruiter_id = ?");
        $stmt->execute([$offerId, $recruiterId]);
        if ($stmt->fetch()) {
            $pdo->prepare("UPDATE offers SET deleted_at = NOW() WHERE id = ?")->execute([$offerId]);
            $_SESSION['flash_message'] = ['type' => 'success','message' => 'Job offer successfully deleted'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'danger','message' => 'Offer not found or no permission'];
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['flash_message'] = ['type' => 'danger','message' => 'Error deleting job offer'];
    }
    header("Location: manage_jobs.php");
    exit;
}

$jobs = [];
$limit = 10; // 10 offres par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

try {
    // Nombre total d'offres
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE recruiter_id = ? AND deleted_at IS NULL");
    $stmtCount->execute([$recruiterId]);
    $totalJobs = $stmtCount->fetchColumn();
    $totalPages = ceil($totalJobs / $limit);

    // Récupère juste les offres de la page courante
    $stmtJobs = $pdo->prepare("SELECT id, title, created_at FROM offers WHERE recruiter_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmtJobs->bindValue(1, $recruiterId, PDO::PARAM_INT);
    $stmtJobs->bindValue(2, $limit, PDO::PARAM_INT);
    $stmtJobs->bindValue(3, $offset, PDO::PARAM_INT);
    $stmtJobs->execute();
    $jobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching job offers: " . $e->getMessage());
}


$headerProfilePicture = '../Public/Assets/default-avatar.png';
$headerManagerName = 'Recruiter';
try {
    $stmtUser = $pdo->prepare("SELECT name, photo FROM users WHERE id = ?");
    $stmtUser->execute([$recruiterId]);
    if ($currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $headerManagerName = htmlspecialchars($currentUser['name'] ?: 'Recruiter');
        $headerProfilePicture = !empty($currentUser['photo'])
            ? '../Public/Uploads/profile_pictures/' . htmlspecialchars($currentUser['photo'])
            : '../Public/Assets/default-user.png';
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Jobs | MagLine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CDN Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../Public/Assets/css/manage_jobs.css" rel="stylesheet">
    <link href="../Public/Assets/css/main.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <style>
        /* Pagination styling for dark theme */
.pagination {
    margin-top: 30px;
}

.pagination .page-item .page-link {
    background-color: #1e1e2f; /* dark button */
    border: 1px solid #444;
    color: #ccc;
    transition: 0.3s ease-in-out;
}

.pagination .page-item.active .page-link {
    background-color: #00bcd4; /* active page color */
    border-color: #00bcd4;
    color: #fff;
}

.pagination .page-item .page-link:hover {
    background-color: #333;
    color: #fff;
    border-color: #666;
}
</style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/Recruiter_header.php'; ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Recruiter_sidebar.php'; ?>
        <main class="main-content p-4">
            <div class="page-header mb-4">
                <h1 class="page-title"><i class="bi bi-briefcase-fill me-2"></i>Your Job Offers</h1>
            </div>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
                    <i class="bi <?= $_SESSION['flash_message']['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                    <?= htmlspecialchars($_SESSION['flash_message']['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <?php if (empty($jobs)): ?>
                <div class="empty-state">
                    <i class="bi bi-briefcase display-1 text-muted mb-4"></i>
                    <h3>No Job Offers Yet</h3>
                    <p>You haven't posted any job offers yet. Create your first job posting to get started.</p>
                    <a href="post_offer.php" class="btn btn-primary mt-3">Post a Job</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="job-table table table-hover text-white">
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Posted Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><?= htmlspecialchars($job['title']) ?></td>
                                <td><?= (new DateTime($job['created_at']))->format('F j, Y \a\t H:i') ?></td>
                                <td class="text-end">
                                    <a href="offer_detail.php?id=<?= $job['id'] ?>" class="btn btn-outline-info btn-sm me-1"><i class="bi bi-eye"></i> View</a>
                                    <a href="edit_offer.php?id=<?= $job['id'] ?>" class="btn btn-outline-warning btn-sm me-1"><i class="bi bi-pencil"></i> Edit</a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                        <input type="hidden" name="delete_id" value="<?= $job['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center">
                    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

                    <a href="post_offer.php" class="btn btn-gradient my-3"><i class="bi bi-lightning-charge-fill me-1"></i>Post a New Opportunity</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Assets/Js/main.js"></script>
    
    <?php include __DIR__ . '/Includes/footer.php'; ?>
</body>
</html>
