<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'candidate') {
    header("Location: Login.php");
    exit;
}

$candidateId = $_SESSION['user_id'];

$totalApplications = 0;
$applications = [];
$totalPages = 1;
$candidateName = 'Candidate';
$error = '';

try {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$candidateId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($candidate) {
        $candidateName = $candidate['name'] ?? 'Candidate';
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $query = "SELECT a.id, o.title, u.company_name, a.status, a.created_at, o.id as offer_id
              FROM applications a
              JOIN offers o ON a.offer_id = o.id
              JOIN users u ON o.recruiter_id = u.id
              WHERE a.candidate_id = :candidate_id
              ORDER BY a.created_at DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
 
    $stmt->bindValue(':candidate_id', $candidateId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE candidate_id = ?");
    $stmt->execute([$candidateId]);
    $totalApplications = (int)$stmt->fetchColumn();
    $totalPages = max(1, ceil($totalApplications / $limit));

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log($error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications | MagLine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Public/Assets/CSS/main.css">
    <link rel="stylesheet" href="../Public/Assets/CSS/candidate.css">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
   <style>
    .content-card {
        background: var(--dark-card);
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        padding: 25px;
        margin-bottom: 30px;
    }
    

    .table-responsive {
        background-color: 
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }

        .table {
            color: white;
            margin: 0;
            background-color: transparent; 
            border-collapse: separate;
            border-spacing: 0;

            --bs-table-bg: #1a2035; 
            --bs-table-color: white; 
        }
    .table thead th {
        background-color: #0f1427;
        color: white;
        padding: 15px;
        border-bottom: 2px solid #2a3042;
        font-weight: 600;
    }

    .table tbody tr {
        background-color: #1a2035;
        transition: background-color 0.2s ease;
    }

    .table tbody tr:nth-child(even) {
        background-color: #1e2439;
    }

    .table tbody tr:hover {
        background-color: #232940;
    }

    .table td {
        padding: 15px;
        vertical-align: middle;
        border-bottom: 1px solid #2a3042; 
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        color:rgb(12,12,29);
    }
    
    .status-en_attente {
        background-color: #3a3a6a; 
        color:rgb(12, 12, 29);
    }
    
    .status-accepte {
        background-color: #2d4a3d;
        color: #a8dfc1; 
    }
    
    .status-refuse {
        background-color: #4a2d3d; 
        color: #dfa8c1;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: white;
    }
    
    .empty-state i {
        font-size: 3rem;
        color: #b8b8ff; 
        margin-bottom: 20px;
    }
    
    .empty-state h5 {
        font-weight: 600;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #b8b8ff; 
        margin-bottom: 25px;
    }
    
.pagination {
    margin-top: 30px;
}

.page-item {
    margin: 0 5px;
}

.page-link {
    background-color: #1a2035;
    border: 1px solid #2a3042;
    color: #b8b8ff;
    min-width: 40px;
    text-align: center;
    transition: all 0.3s ease;
    border-radius: 6px !important;
}

.page-link:hover {
    background-color: #232940;
    border-color: #3a3a6a;
    color: white;
}

.page-item.active .page-link {
    background-color: #6c63ff;
    border-color: #6c63ff;
    color: white;
    box-shadow: 0 2px 10px rgba(108, 99, 255, 0.3);
}

.page-item.disabled .page-link {
    background-color: #1a2035;
    border-color: #2a3042;
    color: #4a4a6a;
}

.page-link:focus {
    box-shadow: 0 0 0 0.25rem rgba(108, 99, 255, 0.25);
}


@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.page-item.active .page-link {
    animation: pulse 1.5s infinite;
}


</style>
</head>
<body class="dark-theme">
    <?php 
        $headerManagerName = $candidateName;
        include __DIR__ . '/Includes/Candidate_header.php'; 
    ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Candidate_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="bi bi-file-earmark-text me-2"></i>My Applications</h2>
                    <span class="badge bg-primary"><?= $totalApplications ?> total</span>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h4>Application History</h4>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($applications)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Job Title</th>
                                        <th>Company</th>
                                        <th>Status</th>
                                        <th>Date Applied</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $app): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($app['title']) ?></td>
                                            <td><?= htmlspecialchars($app['company_name']) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $app['status'] ?>">
                                                    <?= htmlspecialchars($app['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($app['created_at'])) ?></td>
                                            <td>
                                                <a href="Candidate_Job_detail.php?id=<?= $app['offer_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

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
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
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
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-file-earmark-excel"></i>
                            <h5>No applications found</h5>
                            <p>You haven't applied to any jobs yet.</p>
                            <a href="jobs.php" class="btn btn-primary">
                                <i class="bi bi-search me-2"></i> Browse Jobs
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/Includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../Public/Assets/Js/main.js"></script>
</body>
</html>
