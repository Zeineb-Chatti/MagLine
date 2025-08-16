<?php
session_start();
$baseURL = '/MagLine/Public';
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../App/Helpers/notification_functions.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}

$applicationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$candidateId = filter_input(INPUT_GET, 'candidate_id', FILTER_VALIDATE_INT);

if (!$applicationId || !$candidateId) {
    header("Location: Applications.php?error=invalid_id");
    exit;
}

try {
    $stmtApp = $pdo->prepare("
        SELECT a.*, o.title AS offer_title, u.name AS recruiter_name
        FROM applications a
        JOIN offers o ON a.offer_id = o.id
        JOIN users u ON o.recruiter_id = u.id
        WHERE a.id = ? AND o.recruiter_id = ?
    ");
    $stmtApp->execute([$applicationId, $_SESSION['user_id']]);
    $application = $stmtApp->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        header("Location: Applications.php?error=not_found");
        exit;
    }

    $stmtCandidate = $pdo->prepare("
        SELECT id, name, email, phone, location, linkedin, about, photo
        FROM users
        WHERE id = ?
    ");
    $stmtCandidate->execute([$candidateId]);
    $candidate = $stmtCandidate->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) {
        header("Location: Applications.php?error=candidate_not_found");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: Applications.php?error=database_error");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    try {
        $status = $_POST['status'];
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE applications SET status = ?, message = ? WHERE id = ?");
        
        if ($status === 'interview' && isset($_POST['interview_date'], $_POST['meet_link'], $_POST['interview_message'])) {
            $interviewDetails = json_encode([
                'date' => $_POST['interview_date'],
                'link' => $_POST['meet_link'],
                'message' => $_POST['interview_message']
            ]);
            $stmt->execute([$status, $interviewDetails, $applicationId]);
     
            $interviewDate = date('F j, Y \a\t g:i A', strtotime($_POST['interview_date']));
        
            $interviewMessage = "Dear " . htmlspecialchars($candidate['name']) . ",\n\n";
            $interviewMessage .= "We would like to invite you for an interview regarding your application for the position of " . 
                              htmlspecialchars($application['offer_title']) . ".\n\n";
            $interviewMessage .= "Date: " . $interviewDate . "\n";
            $interviewMessage .= "Meeting Link: " . htmlspecialchars($_POST['meet_link']) . "\n\n";
            $interviewMessage .= ". Please let us know if the scheduled time doesn't work for you.";

            $stmtMessage = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmtMessage->execute([
                $_SESSION['user_id'],
                $candidateId,
                $interviewMessage
            ]);
      
            $notificationMessage = "Interview Scheduled\n\n" . 
                                 htmlspecialchars($application['offer_title']) . "\n" .
                                 "Check Messages For Details.";
            
            addNotification(
                $pdo,
                $candidateId,
                $notificationMessage,
                'status_change',
                $applicationId
            );
            
            $flashMessage = "Interview scheduled successfully! Notification sent to candidate.";
        } else {
            $stmt->execute([$status, null, $applicationId]);
      
            $statusDisplay = ucfirst($status);
            addNotification(
                $pdo,
                $candidateId,
                "Your application status changed to: $statusDisplay",
                'status_change',
                $applicationId
            );
            
            $flashMessage = "Application status updated to $statusDisplay!";
        }

        $pdo->commit();

        $_SESSION['flash_message'] = [
            'type' => $status,
            'message' => $flashMessage
        ];
        
        header("Location: application_detail.php?id=$applicationId&candidate_id=$candidateId");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating application status: " . $e->getMessage());
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Failed to update application status: ' . $e->getMessage()
        ];
    }
}

$candidatePhoto = !empty($candidate['photo'])
    ? '../Public/Uploads/profile_photos/' . $candidate['photo']
    : '../Public/Assets/default-user.png';

$headerProfilePicture = '../Public/Assets/default-user.png';
$headerManagerName = 'Recruiter';

try {
    $stmtUser = $pdo->prepare("SELECT name, photo FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]); 
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($currentUser) {
        $headerManagerName = htmlspecialchars($currentUser['name'] ?? 'Recruiter');
        $headerProfilePicture = !empty($currentUser['photo'])
            ? '../Public/Uploads/profile_pictures/' . htmlspecialchars($currentUser['photo'])
            : '../Public/Assets/default-user.png';
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($candidate['name']) ?> | MagLine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Public/Assets/CSS/main.css">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    
    <!-- Flatpickr for beautiful datetime picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary-color: #4c2aacff;
            --secondary-color: #c023a6ff;
            --success-color: #00b894;
            --danger-color: #d63031;
            --warning-color: #fdcb6e;
            --info-color: #0984e3;
            --dark-bg: #0f0f13;
            --card-bg: rgba(255,255,255,0.05);
            --card-border: rgba(255,255,255,0.1);
            --text-primary: #ffffff;
            --text-secondary: rgba(255,255,255,0.8);
            --text-light: rgba(255,255,255,0.95);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-primary);
        }
        
        /* Status-specific alert colors */
        .alert-approved {
            background-color: rgba(0, 184, 148, 0.2);
            border-color: rgba(0, 184, 148, 0.3);
            color: var(--success-color);
        }
        
        .alert-rejected {
            background-color: rgba(214, 48, 49, 0.2);
            border-color: rgba(214, 48, 49, 0.3);
            color: var(--danger-color);
        }
        
        .alert-interview {
            background-color: rgba(9, 132, 227, 0.2);
            border-color: rgba(9, 132, 227, 0.3);
            color: var(--info-color);
        }
        
        .flatpickr-calendar {
            background: #2d3748 !important;
            border: 1px solid #4a5568 !important;
            box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important;
        }
        
        .flatpickr-day {
            color: #e2e8f0 !important;
        }
        
        .flatpickr-day.selected {
            background: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        
        .flatpickr-time input {
            color: #e2e8f0 !important;
        }
        
        .flatpickr-weekday {
            color: #a0aec0 !important;
        }
        
        .flatpickr-current-month .flatpickr-monthDropdown-months {
            background: #2d3748 !important;
            color: #e2e8f0 !important;
            border: none !important;
        }
        
        .flatpickr-months .flatpickr-month {
            color: #e2e8f0 !important;
            background: #2d3748 !important;
        }
        
        .flatpickr-monthDropdown-month {
            background: #2d3748 !important;
            color: #e2e8f0 !important;
        }
        
        .flatpickr-monthDropdown-month:hover {
            background: #4a5568 !important;
        }
        
        .flatpickr-weekdays {
            background: #2d3748 !important;
        }
        
        /* Interview details styling */
        .interview-details {
            white-space: pre-line;
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-light);
        }
        
        .interview-card {
            background: rgba(9, 132, 227, 0.1);
            border-left: 4px solid var(--info-color);
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        .candidate-profile {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid var(--card-border);
        }
        
        .contact-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .contact-info-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            background: rgba(255,255,255,0.1);
        }
        
        .application-status {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }
        
        .status-pending { background-color: rgba(253, 203, 110, 0.2); color: var(--warning-color); }
        .status-approved { background-color: rgba(0, 184, 148, 0.2); color: var(--success-color); }
        .status-rejected { background-color: rgba(214, 48, 49, 0.2); color: var(--danger-color); }
        .status-interview { background-color: rgba(9, 132, 227, 0.2); color: var(--info-color); }
        
        .about-section {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
            border: 1px solid var(--card-border);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .contact-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .application-detail-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .application-detail-value {
            color: var(--text-light);
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
        }
        
        .status-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-outline-danger {
            color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn-outline-danger:hover {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-outline-info {
            color: var(--info-color);
            border-color: var(--info-color);
        }
        
        .btn-outline-info:hover {
            background-color: var(--info-color);
            color: white;
        }
        
        .sticky-section {
            position: sticky;
            top: 20px;
        }
        
        .offer-title {
            color: var(--text-light);
            font-weight: 500;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .application-title {
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        
        .candidate-name {
            color: var(--text-light); 
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }
        
        .candidate-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            margin: 0 auto 1.5rem;
            display: block;
        }
        
        .modal-content {
            background-color: var(--dark-bg);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
        }
        
        .form-control, .form-control:focus {
            background-color: rgba(255,255,255,0.05);
            border-color: var(--card-border);
            color: var(--text-primary);
        }
        
        .form-label {
            color: var(--text-secondary);
        }
        
    </style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/Recruiter_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Recruiter_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid py-4">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
                        <i class="bi <?= $_SESSION['flash_message']['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                        <?= $_SESSION['flash_message']['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="candidate-profile sticky-section">
                            <div class="text-center">
                                <img src="<?= htmlspecialchars($candidatePhoto) ?>" 
                                     alt="<?= htmlspecialchars($candidate['name']) ?>" 
                                     class="candidate-photo"
                                     onerror="this.onerror=null;this.src='../Public/Assets/default-user.png'">
                                <h3 class="candidate-name"><?= htmlspecialchars($candidate['name']) ?></h3>
                                <p class="application-detail-label">Candidate for</p>
                                <h5 class="offer-title"><?= htmlspecialchars($application['offer_title']) ?></h5>
                                
                                <div class="mb-4">
                                    <div class="status-container">
                                        <span class="application-detail-label">Current Status</span>
                                        <span class="application-status status-<?= htmlspecialchars($application['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($application['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="contact-buttons">
                                    <a href="mailto:<?= htmlspecialchars($candidate['email']) ?>" 
                                       class="btn btn-primary">
                                        <i class="bi bi-envelope me-1"></i> Contact
                                    </a>
                                    <a href="view_profile.php?user_id=<?= $candidateId ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="bi bi-person me-1"></i> View Profile
                                    </a>
                                    <?php if (!empty($candidate['linkedin'])): ?>
                                    <a href="<?= htmlspecialchars($candidate['linkedin']) ?>" 
                                       target="_blank" 
                                       class="btn btn-outline-primary">
                                        <i class="bi bi-linkedin"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3" style="color: var(--text-light);">Contact Information</h5>
                            <div class="contact-info">
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="bi bi-envelope"></i>
                                    </div>
                                    <div>
                                        <small class="application-detail-label">Email</small>
                                        <div class="application-detail-value"><?= htmlspecialchars($candidate['email']) ?></div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($candidate['phone'])): ?>
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="bi bi-telephone"></i>
                                    </div>
                                    <div>
                                        <small class="application-detail-label">Phone</small>
                                        <div class="application-detail-value"><?= htmlspecialchars($candidate['phone']) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($candidate['location'])): ?>
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="bi bi-geo-alt"></i>
                                    </div>
                                    <div>
                                        <small class="application-detail-label">Location</small>
                                        <div class="application-detail-value"><?= htmlspecialchars($candidate['location']) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h4 class="application-title">Application Details</h4>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <small class="application-detail-label">Application Date</small>
                                        <p class="application-detail-value"><?= date('M j, Y \a\t g:i A', strtotime($application['created_at'])) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="application-detail-label">Status</small>
                                        <p class="application-detail-value">
                                            <span class="application-status status-<?= htmlspecialchars($application['status']) ?>">
                                                <?= ucfirst(htmlspecialchars($application['status'])) ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <small class="application-detail-label">Applied for</small>
                                        <h5 class="offer-title"><?= htmlspecialchars($application['offer_title']) ?></h5>
                                    </div>
                                </div>
                                
                                <?php if ($application['status'] === 'interview'): ?>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="interview-card">
                                            <div class="interview-details">
                                                Interview scheduled
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <hr class="my-4">
                                
                                <div class="action-buttons">
                                    <form action="application_detail.php?id=<?= htmlspecialchars($application['id']) ?>&candidate_id=<?= htmlspecialchars($candidate['id']) ?>" method="post" class="d-inline">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i> Reject
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#interviewModal">
                                        <i class="bi bi-calendar-check me-1"></i> Interview
                                    </button>
                                    
                                    <form action="application_detail.php?id=<?= htmlspecialchars($application['id']) ?>&candidate_id=<?= htmlspecialchars($candidate['id']) ?>" method="post" class="d-inline">
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check-circle me-1"></i> Approve
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($candidate['about'])): ?>
                        <div class="about-section">
                            <h4 class="mb-3" style="color: var(--text-light);">About the Candidate</h4>
                            <p style="color: var(--text-light);"><?= nl2br(htmlspecialchars($candidate['about'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Interview Modal -->
    <!-- Interview Modal -->
<div class="modal fade" id="interviewModal" tabindex="-1" aria-labelledby="interviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="interviewModalLabel">Schedule Interview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="application_detail.php?id=<?= htmlspecialchars($application['id']) ?>&candidate_id=<?= htmlspecialchars($candidate['id']) ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" name="status" value="interview">
                    
                    <div class="mb-3">
                        <label for="interview_date" class="form-label">Date & Time</label>
                        <input type="text" class="form-control flatpickr" id="interview_date" name="interview_date" placeholder="Select date and time" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="meet_link" class="form-label">Meeting Link</label>
                        <input type="url" class="form-control" id="meet_link" name="meet_link" placeholder="https://meet.google.com/..." required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="interview_message" class="form-label">Message to Candidate</label>
                        <textarea class="form-control" id="interview_message" name="interview_message" rows="5" required>Dear <?= htmlspecialchars($candidate['name']) ?>, 

We would like to invite you for an interview regarding your application for the position of <?= htmlspecialchars($application['offer_title']) ?>.

Please let us know if the scheduled time doesn't work for you.</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Interview</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr for datetime picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize flatpickr with English localization and time
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".flatpickr", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: false,
                minuteIncrement: 15,
                defaultDate: new Date().fp_incr(1), // Tomorrow
                defaultHour: 10, // 10 AM
                locale: "en",
                onReady: function(selectedDates, dateStr, instance) {
                    instance.element.value = dateStr; // Set initial value
                }
            });
            
            // Focus on message field
            const interviewMessage = document.getElementById('interview_message');
            if (interviewMessage) {
                interviewMessage.focus();
                interviewMessage.setSelectionRange(0, 0);
            }
        });
    </script>
    <?php include __DIR__ . '/Includes/footer.php'; ?>
</body>
</html>
