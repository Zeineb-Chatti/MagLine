<?php
session_start();
$baseURL = '/MagLine/Public';
require_once __DIR__ . '/../Config/Database.php';

// Authentication check
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}

$headerManagerName = $_SESSION['user_name'] ?? 'User';
$recruiterId = $_SESSION['user_id'];
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = $_POST['company_name'] ?? '';
    $company_website = $_POST['company_website'] ?? '';
    $location = $_POST['location'] ?? '';
    $about_company = $_POST['about_company'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $manager_email = $_POST['manager_email'] ?? '';
    $linkedin = $_POST['linkedin'] ?? '';
    
    // Validation
    if (empty($company_name)) {
        $errors[] = "Company name is required";
    }
    
    if (!empty($manager_email) && !filter_var($manager_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid manager email format";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET company_name = ?, company_website = ?, location = ?, 
                    about_company = ?, phone = ?, manager_email = ?, linkedin = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $company_name, 
                $company_website, 
                $location, 
                $about_company, 
                $phone,
                $manager_email,
                $linkedin,
                $recruiterId
            ]);
            
            $_SESSION['company_name'] = $company_name;
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Company profile updated successfully!'
            ];
            header("Location: company_profile.php");
            exit;
        } catch (PDOException $e) {
            error_log("Error updating company profile: " . $e->getMessage());
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Failed to update company profile. Please try again.'
            ];
        }
    }
}

// Get company data
try {
    $stmt = $pdo->prepare("
        SELECT company_name, company_logo, company_website, about_company, 
               location, manager_email, phone, linkedin
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$recruiterId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Company profile not found'
        ];
        header("Location: dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching company data: " . $e->getMessage());
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'Error loading company data'
    ];
    header("Location: dashboard.php");
    exit;
}

// FIXED: Logo handling using same logic as Dashboard
$defaultLogo = '../Public/Assets/default-company.png';
$logoUrl = !empty($company['company_logo']) 
    ? '../Public/Uploads/Company_Logos/' . htmlspecialchars($company['company_logo'])
    : $defaultLogo;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['company_name']) ?> | Company Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/company-profile.css" rel="stylesheet">
    <style>
        /* FIXED: Modal buttons styling - simplified */
        .modal-footer {
            display: flex !important;
            justify-content: flex-end !important;
            padding: 1rem !important;
            border-top: 1px solid #495057 !important;
        }
        
        .modal-footer .btn + .btn {
            margin-left: 0.5rem;
        }
        
        .btn-close-white {
            filter: invert(1);
        }
        
        /* Logo container */
        .company-logo-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 3px solid #495057;
        }
        
        .company-logo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Modal dark theme */
        .modal-content {
            background-color: #1a1d20 !important;
            border: 1px solid #495057 !important;
        }
        
        .modal-header {
            border-bottom: 1px solid #495057 !important;
            color: #ffffff !important;
        }
        
        .modal-title {
            color: #ffffff !important;
        }
        
        .form-control {
            background-color: #2b3035 !important;
            border-color: #495057 !important;
            color: #ffffff !important;
        }
        
        .form-control:focus {
            background-color: #2b3035 !important;
            border-color: #0d6efd !important;
            color: #ffffff !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
        }
        
        .form-label {
            color: #ffffff !important;
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
                        <?= $_SESSION['flash_message']['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="company-profile-container">
                    <div class="company-header">
                        <div class="company-logo-container">
                            <img src="<?= $logoUrl ?>" 
                                 alt="<?= htmlspecialchars($company['company_name']) ?>" 
                                 class="company-logo"
                                 onerror="this.onerror=null;this.src='<?= $defaultLogo ?>'">
                        </div>
                        
                        <h1 class="company-name"><?= htmlspecialchars($company['company_name']) ?></h1>
                        
                        <?php if ($company['location']): ?>
                        <div class="company-location">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span><?= htmlspecialchars($company['location']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="company-actions mt-3">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editCompanyModal">
                                <i class="bi bi-pencil-square"></i> Edit Profile
                            </button>
                        </div>
                    </div>
                    
                    <div class="company-card">
                        <h3 class="card-title">
                            <i class="bi bi-building"></i> About Our Company
                        </h3>
                        <div class="company-description">
                            <?= $company['about_company'] ? nl2br(htmlspecialchars($company['about_company'])) : 
                                '<div class="empty-description">
                                    <i class="bi bi-info-circle"></i>
                                    <p>No description provided yet</p>
                                </div>' 
                            ?>
                        </div>
                    </div>
                    
                    <div class="company-card">
                        <h3 class="card-title">
                            <i class="bi bi-envelope"></i> Contact Information
                        </h3>
                        <div class="contact-info">
                            <?php if ($company['manager_email']): ?>
                            <div class="contact-item">
                                <i class="bi bi-envelope-fill"></i>
                                <span><?= htmlspecialchars($company['manager_email']) ?></span>
                                <small class="text-muted ms-2"></small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($company['phone']): ?>
                            <div class="contact-item">
                                <i class="bi bi-telephone-fill"></i>
                                <span><?= htmlspecialchars($company['phone']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($company['company_website']): ?>
                            <div class="contact-item">
                                <i class="bi bi-globe"></i>
                                <a href="<?= htmlspecialchars($company['company_website']) ?>" target="_blank">
                                    <?= htmlspecialchars($company['company_website']) ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($company['linkedin']): ?>
                            <div class="contact-item">
                                <i class="bi bi-linkedin"></i>
                                <a href="<?= htmlspecialchars($company['linkedin']) ?>" target="_blank">
                                    LinkedIn Profile
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- FIXED: Simplified modal structure -->
    <div class="modal fade" id="editCompanyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Company Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Company Name*</label>
                                <input type="text" class="form-control" name="company_name" 
                                       value="<?= htmlspecialchars($company['company_name']) ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Manager Email</label>
                                <input type="email" class="form-control" name="manager_email" 
                                       value="<?= htmlspecialchars($company['manager_email'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Website</label>
                                <input type="url" class="form-control" name="company_website" 
                                       value="<?= htmlspecialchars($company['company_website'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" 
                                       value="<?= htmlspecialchars($company['location']) ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">LinkedIn URL</label>
                                <input type="url" class="form-control" name="linkedin" 
                                       value="<?= htmlspecialchars($company['linkedin'] ?? '') ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Company Description</label>
                                <textarea class="form-control" name="about_company" rows="5"><?= htmlspecialchars($company['about_company'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/Includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // FIXED: Enhanced modal initialization with debugging
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing modal...');
            
            var modalElement = document.getElementById('editCompanyModal');
            var modal = new bootstrap.Modal(modalElement);
            
            // Check if buttons exist
            var buttons = modalElement.querySelectorAll('.modal-footer .btn');
            console.log('Found buttons:', buttons.length);
            
            // Force display the modal footer
            var modalFooter = modalElement.querySelector('.modal-footer');
            if (modalFooter) {
                modalFooter.style.display = 'flex';
                modalFooter.style.justifyContent = 'flex-end';
                modalFooter.style.padding = '1rem';
                console.log('Modal footer forced to display');
            }
            
            // Add click handlers
            modalElement.addEventListener('shown.bs.modal', function() {
                console.log('Modal shown successfully');
                var footer = this.querySelector('.modal-footer');
                if (footer) {
                    footer.style.display = 'flex';
                    console.log('Footer display enforced on modal show');
                }
            });
        });
    </script>
</body>
</html>