<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'candidate') {
    header("Location: ../Auth/login.php");
    exit;
}

$candidateId = $_SESSION['user_id'];
$jobId = $_GET['id'] ?? null;

if (!$jobId) {
    header("Location: jobs.php");
    exit;
}

// Fetch candidate info
try {
    $stmt = $pdo->prepare("SELECT name, photo FROM users WHERE id = ?");
    $stmt->execute([$candidateId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    $candidateName = $candidate['name'] ?? 'Candidate';
    $candidatePhoto = $candidate['photo'] ?? '../Public/Assets/default-user.png';

    // Fetch job details
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        header("Location: jobs.php");
        exit;
    }

    // Check if already applied
    $stmt = $pdo->prepare("SELECT id, status FROM applications WHERE job_id = ? AND candidate_id = ?");
    $stmt->execute([$jobId, $candidateId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch similar jobs
    $stmt = $pdo->prepare(
        "SELECT id, title, company_name, location, job_type, posted_date 
         FROM jobs 
         WHERE job_type = ? AND id != ? 
         ORDER BY posted_date DESC 
         LIMIT 3"
    );
    $stmt->execute([$job['job_type'], $jobId]);
    $similarJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: jobs.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($job['title']) ?> | MagLine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Public/Assets/CSS/main.css">
    <link rel="stylesheet" href="../Public/Assets/CSS/candidate.css">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
</head>
<body class="dark-theme">
    <?php 
        $headerProfilePicture = $candidatePhoto;
        $headerManagerName = $candidateName;
        include __DIR__ . '/Includes/Candidate_header.php'; 
    ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Candidate_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="bi bi-briefcase me-2"></i>Job Details</h2>
                    <a href="jobs.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Jobs
                    </a>
                </div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="jobs.php">Jobs</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Details</li>
                    </ol>
                </nav>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="content-card">
                        <div class="card-header">
                            <h3><?= htmlspecialchars($job['title']) ?></h3>
                            <p class="company-name"><?= htmlspecialchars($job['company_name']) ?></p>
                        </div>
                        
                        <div class="card-body">
                            <div class="job-meta mb-4">
                                <div class="meta-item">
                                    <i class="bi bi-geo-alt"></i>
                                    <span><?= htmlspecialchars($job['location']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-clock"></i>
                                    <span><?= htmlspecialchars($job['job_type']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-calendar"></i>
                                    <span>Posted <?= date('M j, Y', strtotime($job['posted_date'])) ?></span>
                                </div>
                                <?php if (!empty($job['salary'])): ?>
                                    <div class="meta-item">
                                        <i class="bi bi-cash"></i>
                                        <span><?= htmlspecialchars($job['salary']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="job-section">
                                <h4>Job Description</h4>
                                <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>
                            </div>

                            <?php if (!empty($job['requirements'])): ?>
                                <div class="job-section">
                                    <h4>Requirements</h4>
                                    <ul>
                                        <?php foreach (explode("\n", $job['requirements']) as $requirement): ?>
                                            <?php if (!empty(trim($requirement))): ?>
                                                <li><?= htmlspecialchars(trim($requirement)) ?></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($job['benefits'])): ?>
                                <div class="job-section">
                                    <h4>Benefits</h4>
                                    <ul>
                                        <?php foreach (explode("\n", $job['benefits']) as $benefit): ?>
                                            <?php if (!empty(trim($benefit))): ?>
                                                <li><?= htmlspecialchars(trim($benefit)) ?></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer">
                            <?php if ($application): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> You've already applied to this job. 
                                    Current status: <strong><?= htmlspecialchars(ucfirst($application['status'])) ?></strong>
                                </div>
                            <?php else: ?>
                                <a href="apply_job.php?id=<?= $job['id'] ?>" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send"></i> Apply Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="content-card">
                        <div class="card-header">
                            <h4>About <?= htmlspecialchars($job['company_name']) ?></h4>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($job['company_description'])): ?>
                                <p><?= nl2br(htmlspecialchars($job['company_description'])) ?></p>
                            <?php else: ?>
                                <p>No company description available.</p>
                            <?php endif; ?>
                            
                            <?php if (!empty($job['company_website'])): ?>
                                <a href="<?= htmlspecialchars($job['company_website']) ?>" target="_blank" class="btn btn-outline-primary">
                                    <i class="bi bi-globe"></i> Visit Website
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($similarJobs)): ?>
                        <div class="content-card mt-4">
                            <div class="card-header">
                                <h4>Similar Jobs</h4>
                            </div>
                            <div class="card-body">
                                <div class="similar-jobs">
                                    <?php foreach ($similarJobs as $similarJob): ?>
                                        <div class="similar-job-item">
                                            <h5><a href="Job_Detail.php?id=<?= $similarJob['id'] ?>"><?= htmlspecialchars($similarJob['title']) ?></a></h5>
                                            <p class="company"><?= htmlspecialchars($similarJob['company_name']) ?></p>
                                            <div class="job-meta">
                                                <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($similarJob['location']) ?></span>
                                                <span><i class="bi bi-clock"></i> <?= htmlspecialchars($similarJob['job_type']) ?></span>
                                            </div>
                                            <a href="Job_Detail.php?id=<?= $similarJob['id'] ?>" class="btn btn-sm btn-outline-primary mt-2">
                                                View Details
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
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