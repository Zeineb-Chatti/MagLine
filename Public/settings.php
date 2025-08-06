<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}
$headerManagerName = $headerManagerName ?? $_SESSION['user_name'] ?? 'User';
$recruiterId = $_SESSION['user_id'];
$recruiterData = [];
$errors = [];

try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, location, linkedin, photo,
               company_name, company_size, industry, company_website, company_logo,
               manager_name, manager_photo, manager_job_title, manager_phone, manager_email,
               about, about_company
        FROM users 
        WHERE id = ? AND role = 'recruiter'
    ");
    $stmt->execute([$recruiterId]);
    $recruiterData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recruiterData) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Recruiter profile not found'
        ];
        header("Location: dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'Database error occurred'
    ];
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['personal_name']);
        $location = trim($_POST['personal_location']);
        $phone = trim($_POST['personal_phone']);
        $linkedin = trim($_POST['personal_linkedin']);
        $about = trim($_POST['about_me']);
        $companyName = trim($_POST['company_name']);
        $companySize = trim($_POST['company_size']);
        $industry = trim($_POST['industry']);
        $companyWebsite = trim($_POST['company_website']);
        $aboutCompany = trim($_POST['about_company']);
        $managerJobTitle = trim($_POST['manager_job_title']);
        $managerEmail = trim($_POST['manager_email']);

        if (empty($name)) {
            $errors[] = "Name is required";
        }
        if (empty($companyName)) {
            $errors[] = "Company name is required";
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users SET
                        name = ?,
                        location = ?,
                        phone = ?,
                        linkedin = ?,
                        about = ?,
                        company_name = ?,
                        company_size = ?,
                        industry = ?,
                        company_website = ?,
                        about_company = ?,
                        manager_name = ?,
                        manager_job_title = ?,
                        manager_email = ?,
                        manager_phone = ?
                    WHERE id = ? AND role = 'recruiter'
                ");

                $stmt->execute([
                    $name,
                    $location,
                    $phone,
                    $linkedin,
                    $about,
                    $companyName,
                    $companySize,
                    $industry,
                    $companyWebsite,
                    $aboutCompany,
                    $name,
                    $managerJobTitle,
                    $managerEmail,
                    $phone,
                    $recruiterId
                ]);

                $_SESSION['company_name'] = $companyName;
                $_SESSION['user_name'] = $name;
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$recruiterId]);
                $recruiterData = $stmt->fetch(PDO::FETCH_ASSOC);

                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => 'Profile updated successfully'
                ];
                header("Location: settings.php?tab=profile");
                exit;
            } catch (PDOException $e) {
                error_log("Update error: " . $e->getMessage());
                $errors[] = "Failed to update profile. Please try again.";
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($currentPassword)) {
            $errors[] = "Current password is required";
        }
        if (empty($newPassword) || strlen($newPassword) < 8) {
            $errors[] = "New password must be at least 8 characters";
        } elseif (!preg_match("/[0-9]/", $newPassword) || !preg_match("/[^A-Za-z0-9]/", $newPassword)) {
            $errors[] = "Password must contain at least one number and one special character";
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = "New passwords do not match";
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$recruiterId]);
                $user = $stmt->fetch();

                if ($user && password_verify($currentPassword, $user['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $recruiterId]);

                    $_SESSION['flash_message'] = [
                        'type' => 'success',
                        'message' => 'Password changed successfully'
                    ];
                    header("Location: settings.php?tab=security");
                    exit;
                } else {
                    $errors[] = "Current password is incorrect";
                }
            } catch (PDOException $e) {
                error_log("Password change error: " . $e->getMessage());
                $errors[] = "Failed to change password. Please try again.";
            }
        }
    }

    if (isset($_POST['cropped_manager_image']) && !empty($_POST['cropped_manager_image'])) {
    $imageData = $_POST['cropped_manager_image'];
    
    // Ensure upload directory exists
    $uploadDir = __DIR__ . '/../Public/Uploads/Manager_Photos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $fileName = 'manager_' . $recruiterId . '_' . time() . '.png';
    $targetFile = $uploadDir . $fileName;

    // Process the image data
    $imageData = str_replace('data:image/png;base64,', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $imageData = base64_decode($imageData);

    if ($imageData !== false && file_put_contents($targetFile, $imageData)) {
        try {
            // Get old photo path before updating
            $oldPhoto = $recruiterData['manager_photo'] ?? '';
            
            // Update database with new photo filename
            $stmt = $pdo->prepare("UPDATE users SET manager_photo = ? WHERE id = ?");
            $stmt->execute([$fileName, $recruiterId]);

            // Delete old photo if it exists
            if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
                unlink($uploadDir . $oldPhoto);
            }

            // Update session and local data
            $_SESSION['manager_photo'] = $fileName;
            $recruiterData['manager_photo'] = $fileName;

            // Refresh the recruiter data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$recruiterId]);
            $recruiterData = $stmt->fetch(PDO::FETCH_ASSOC);

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Profile photo updated successfully'
            ];
            
            header("Location: settings.php?tab=profile");
            exit;
        } catch (PDOException $e) {
            // Clean up if database update fails
            if (file_exists($targetFile)) {
                unlink($targetFile);
            }
            error_log("Manager photo update error: " . $e->getMessage());
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Failed to update profile photo in database'
            ];
            header("Location: settings.php?tab=profile");
            exit;
        }
    } else {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Failed to save profile image'
        ];
        header("Location: settings.php?tab=profile");
        exit;
    }
}

    if (isset($_POST['cropped_company_logo']) && !empty($_POST['cropped_company_logo'])) {
        $imageData = $_POST['cropped_company_logo'];
        
        $uploadDir = __DIR__ . '/../Public/Uploads/Company_Logos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'company_' . $recruiterId . '_' . time() . '.png';
        $targetFile = $uploadDir . $fileName;

        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = str_replace(' ', '+', $imageData);
        $imageData = base64_decode($imageData);

        if ($imageData !== false && file_put_contents($targetFile, $imageData)) {
            try {
                $oldLogo = $recruiterData['company_logo'] ?? '';
                
                $stmt = $pdo->prepare("UPDATE users SET company_logo = ? WHERE id = ?");
                $stmt->execute([$fileName, $recruiterId]);

                if ($oldLogo && $oldLogo !== 'default-company.png' && file_exists($uploadDir . $oldLogo)) {
                    unlink($uploadDir . $oldLogo);
                }

                $_SESSION['company_logo'] = $fileName;
                $recruiterData['company_logo'] = $fileName;

                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => 'Company logo updated successfully'
                ];
                
                // Refresh the recruiter data after update
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$recruiterId]);
                $recruiterData = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                if (file_exists($targetFile)) {
                    unlink($targetFile);
                }
                error_log("Company logo update error: " . $e->getMessage());
                $errors[] = "Failed to update company logo in database";
            }
        } else {
            $errors[] = "Failed to save company logo";
        }
        header("Location: settings.php?tab=profile");
        exit;
    }

    if (isset($_POST['export_type'])) {
        $type = $_POST['export_type'];
        
        if ($type === 'excel') {
            try {
                $stmt = $pdo->prepare("
                    SELECT a.id, a.status, a.created_at, u.name AS candidate_name, 
                           o.title AS offer_title, u.email AS candidate_email
                    FROM applications a
                    JOIN users u ON a.candidate_id = u.id
                    JOIN offers o ON a.offer_id = o.id
                    WHERE o.recruiter_id = ?
                    ORDER BY a.created_at DESC
                ");
                $stmt->execute([$recruiterId]);
                $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="applications_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                fputcsv($output, [
                    'ID',
                    'Candidate Name',
                    'Candidate Email',
                    'Job Title',
                    'Status',
                    'Application Date'
                ]);
                
                foreach ($applications as $app) {
                    fputcsv($output, [
                        $app['id'],
                        $app['candidate_name'],
                        $app['candidate_email'],
                        $app['offer_title'],
                        ucfirst($app['status']),
                        date('Y-m-d H:i', strtotime($app['created_at']))
                    ]);
                }
                
                fclose($output);
                exit;
                
            } catch (PDOException $e) {
                error_log("Export error: " . $e->getMessage());
                $errors[] = "Failed to generate export";
            }
        } else {
            $errors[] = "Invalid export type";
        }
    }

    if (isset($_POST['delete_account'])) {
        $password = $_POST['delete_password'] ?? '';
        
        if (empty($password)) {
            $errors[] = "Password is required to delete account";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$recruiterId]);
                $user = $stmt->fetch();
                
                if (!$user || !password_verify($password, $user['password'])) {
                    $errors[] = "Incorrect password";
                } else {
                    $pdo->beginTransaction();
                    
                    try {
                        $stmt = $pdo->prepare("
                            DELETE a FROM applications a
                            JOIN offers o ON a.offer_id = o.id
                            WHERE o.recruiter_id = ?
                        ");
                        $stmt->execute([$recruiterId]);
                        
                        $stmt = $pdo->prepare("DELETE FROM offers WHERE recruiter_id = ?");
                        $stmt->execute([$recruiterId]);
                        
                        if (!empty($recruiterData['company_logo']) && $recruiterData['company_logo'] !== 'default-company.png') {
                            $logoPath = __DIR__ . '/../Public/Uploads/Company_Logos/' . $recruiterData['company_logo'];
                            if (file_exists($logoPath)) {
                                unlink($logoPath);
                            }
                        }
                        
                        if (!empty($recruiterData['manager_photo'])) {
                            $photoPath = __DIR__ . '/../Public/Uploads/Manager_Photos/' . $recruiterData['manager_photo'];
                            if (file_exists($photoPath)) {
                                unlink($photoPath);
                            }
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$recruiterId]);
                        
                        $pdo->commit();
                        
                        session_unset();
                        session_destroy();
                        
                        // Start new session for flash message
                        session_start();
                        $_SESSION['flash_message'] = [
                            'type' => 'success',
                            'message' => 'Your account has been permanently deleted'
                        ];
                        
                        // Redirect to login page with success message
                        header("Location: ../Auth/SignUp.php");
                        exit;
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        error_log("Account deletion failed: " . $e->getMessage());
                        $errors[] = "Failed to delete account. Please try again.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error during account deletion: " . $e->getMessage());
                $errors[] = "Database error occurred";
            }
        }
    }
}

// Set correct image paths
$defaultLogo = '/Public/Assets/default-company.png';
$logoUrl = !empty($recruiterData['company_logo']) 
    ? '/Public/Uploads/Company_Logos/' . $recruiterData['company_logo']
    : $defaultLogo;

$defaultUserPhoto = '/Public/Assets/default-user.png';
$managerPhotoUrl = !empty($recruiterData['manager_photo']) 
    ? '/Public/Uploads/Manager_Photos/' . $recruiterData['manager_photo']
    : $defaultUserPhoto;

$activeTab = $_GET['tab'] ?? 'profile';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | <?= htmlspecialchars($recruiterData['company_name'] ?? 'Recruiter Portal') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.5.95/css/materialdesignicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <link href="../Public/Assets/CSS/settings.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <style>
        /* Add this to your settings.css file or main.css */

/* Company Size Dropdown - Dark Theme */
select.form-control {
    background-color: #2a3042;
    color: #e0e0e0;
    border: 1px solid #3a4256;
    border-radius: 6px;
    padding: 0.5rem 1rem;
    transition: all 0.3s ease;
}

select.form-control:focus {
    background-color: #2a3042;
    color: #ffffff;
    border-color: #4a8cff;
    box-shadow: 0 0 0 0.25rem rgba(74, 140, 255, 0.25);
    outline: none;
}

select.form-control option {
    background-color: #2a3042;
    color: #e0e0e0;
    padding: 0.5rem 1rem;
}

select.form-control option:hover {
    background-color: #3a4256 !important;
    color: #ffffff;
}

select.form-control option:checked {
    background-color: #3a4256;
    color: #4a8cff;
    font-weight: 500;
}

/* Dropdown arrow styling */
select.form-control {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23e0e0e0' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
    padding-right: 2.5rem;
}

/* Hover state */
select.form-control:hover {
    border-color: #4a8cff;
    background-color: #31384d;
}

/* Disabled state */
select.form-control:disabled {
    background-color: #252a3a;
    color: #6c757d;
    border-color: #3a4256;
    opacity: 0.7;
}

/* Error state */
select.form-control.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3E%3Ccircle cx='6' cy='6' r='4.5'/%3E%3Cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3E%3Ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3E%3C/svg%3E"), 
                      url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23dc3545' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-position: right 2.5rem center, right 0.75rem center;
    background-size: 16px 12px, 16px 12px;
    padding-right: 4.25rem;
}

select.form-control.is-invalid:focus {
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
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
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="page-title">
                            <i class="mdi mdi-cog-outline me-2"></i>Account Settings
                        </h1>
                        <p class="page-subtitle">
                            Manage your profile and preferences
                        </p>
                    </div>
                </div>
                
                <div class="settings-tabs mb-4">
                    <a href="?tab=profile" class="settings-tab <?= $activeTab === 'profile' ? 'active' : '' ?>">
                        <i class="mdi mdi-account-outline"></i> Profile
                    </a>
                    <a href="?tab=security" class="settings-tab <?= $activeTab === 'security' ? 'active' : '' ?>">
                        <i class="mdi mdi-lock-outline"></i> Security
                    </a>
                    <a href="?tab=data" class="settings-tab <?= $activeTab === 'data' ? 'active' : '' ?>">
                        <i class="mdi mdi-database-outline"></i> Data
                    </a>
                </div>
                
                <?php if ($activeTab === 'profile'): ?>
                <div class="centered-form">
                    <div class="settings-card mb-4">
                        <div class="settings-header">
                            <h2 class="settings-title">
                                <i class="mdi mdi-account-circle-outline"></i> Your Personal Information
                            </h2>
                        </div>
                        <div class="p-4">
                            <form method="POST" enctype="multipart/form-data" id="profileForm">
                                <div class="mb-4 text-center">
                                    <div class="logo-preview-container">
                                        <img src="<?= $managerPhotoUrl ?>" 
                                             id="managerPhotoPreview"
                                             class="logo-preview"
                                             onerror="this.onerror=null;this.src='<?= $defaultUserPhoto ?>'">
                                        <div class="cropper-container d-none" id="managerCropperContainer"></div>
                                    </div>
                                    <div class="mt-3">
                                        <input type="file" id="managerPhotoUpload" accept="image/*" class="d-none">
                                        <input type="hidden" name="cropped_manager_image" id="croppedManagerImage">
                                        <button type="button" class="btn btn-outline-primary" id="chooseManagerPhotoBtn">
                                            <i class="bi bi-camera-fill me-1"></i> Change Profile Photo
                                        </button>
                                        <button type="button" class="btn btn-success d-none" id="saveManagerCropBtn">
                                            <i class="bi bi-check me-1"></i> Apply Crop
                                        </button>
                                        <button type="button" class="btn btn-outline-danger d-none" id="cancelManagerCropBtn">
                                            <i class="bi bi-x me-1"></i> Cancel
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Your Name</label>
                                    <input type="text" class="form-control" name="personal_name" 
                                           value="<?= htmlspecialchars($recruiterData['name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Your Login Email</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($recruiterData['email'] ?? '') ?>" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Your Phone Number</label>
                                    <input type="text" class="form-control" name="personal_phone" 
                                           value="<?= htmlspecialchars($recruiterData['phone'] ?? '') ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Your Location</label>
                                    <input type="text" class="form-control" name="personal_location" 
                                           value="<?= htmlspecialchars($recruiterData['location'] ?? '') ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Your LinkedIn Profile URL</label>
                                    <input type="url" class="form-control" name="personal_linkedin" 
                                           value="<?= htmlspecialchars($recruiterData['linkedin'] ?? '') ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">About You</label>
                                    <textarea class="form-control" name="about_me" rows="3"><?= htmlspecialchars($recruiterData['about'] ?? '') ?></textarea>
                                </div>

                                <hr class="my-4">

                                <h2 class="settings-title mb-3">
                                    <i class="mdi mdi-office-building-outline"></i> Company Information
                                </h2>
                                
                                <div class="logo-upload-section mb-4 text-center">
                                    <div class="logo-preview-container">
                                        <img src="<?= $logoUrl ?>" 
                                             id="logoPreview"
                                             class="logo-preview"
                                             onerror="this.onerror=null;this.src='<?= $defaultLogo ?>'">
                                        <div class="cropper-container d-none" id="logoCropperContainer"></div>
                                    </div>
                                    <div class="mt-3">
                                        <input type="file" id="logoUpload" accept="image/*" class="d-none">
                                        <input type="hidden" name="cropped_company_logo" id="croppedCompanyLogo">
                                        <button type="button" class="btn btn-outline-primary" id="chooseLogoBtn">
                                            <i class="bi bi-camera-fill me-1"></i> Change Company Logo
                                        </button>
                                        <button type="button" class="btn btn-success d-none" id="saveLogoCropBtn">
                                            <i class="bi bi-check me-1"></i> Apply Crop
                                        </button>
                                        <button type="button" class="btn btn-outline-danger d-none" id="cancelLogoCropBtn">
                                            <i class="bi bi-x me-1"></i> Cancel
                                        </button>
                                    </div>
                                    <div id="fileError" class="alert alert-danger d-none mt-2"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" class="form-control" name="company_name" 
                                           value="<?= htmlspecialchars($recruiterData['company_name'] ?? '') ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Company Website URL</label>
                                    <input type="url" class="form-control" name="company_website" 
                                           value="<?= htmlspecialchars($recruiterData['company_website'] ?? '') ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Industry</label>
                                    <input type="text" class="form-control" name="industry" 
                                           value="<?= htmlspecialchars($recruiterData['industry'] ?? '') ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Company Size</label>
                                    <select class="form-control" name="company_size" required>
                                        <option value="">Select company size</option>
                                        <option value="1-10" <?= ($recruiterData['company_size'] ?? '') === '1-10' ? 'selected' : '' ?>>1-10 employees</option>
                                        <option value="11-50" <?= ($recruiterData['company_size'] ?? '') === '11-50' ? 'selected' : '' ?>>11-50 employees</option>
                                        <option value="51-200" <?= ($recruiterData['company_size'] ?? '') === '51-200' ? 'selected' : '' ?>>51-200 employees</option>
                                        <option value="201-500" <?= ($recruiterData['company_size'] ?? '') === '201-500' ? 'selected' : '' ?>>201-500 employees</option>
                                        <option value="501-1000" <?= ($recruiterData['company_size'] ?? '') === '501-1000' ? 'selected' : '' ?>>501-1000 employees</option>
                                        <option value="1000+" <?= ($recruiterData['company_size'] ?? '') === '1000+' ? 'selected' : '' ?>>1000+ employees</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Your Job Title</label>
                                    <input type="text" class="form-control" name="manager_job_title" 
                                           value="<?= htmlspecialchars($recruiterData['manager_job_title'] ?? '') ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Professional Email (Contact)</label>
                                    <input type="email" class="form-control" name="manager_email" 
                                           value="<?= htmlspecialchars($recruiterData['manager_email'] ?? '') ?>">
                                    <small class="text-muted">This will be visible to candidates</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">About The Company</label>
                                    <textarea class="form-control" name="about_company" rows="3"><?= htmlspecialchars($recruiterData['about_company'] ?? '') ?></textarea>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary w-100">
                                    <i class="mdi mdi-content-save-outline me-2"></i> Save All Profile Changes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($activeTab === 'security'): ?>
                <div class="centered-form">
                    <div class="settings-card mb-4">
                        <div class="settings-header">
                            <h2 class="settings-title">
                                <i class="mdi mdi-lock-outline"></i> Password & Security
                            </h2>
                        </div>
                        <div class="p-4">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                    <div class="form-text">Minimum 8 characters with at least one number and special character</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary w-100">
                                    <i class="mdi mdi-lock-reset me-2"></i> Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($activeTab === 'data'): ?>
                <div class="centered-form">
                    <div class="settings-card">
                        <div class="settings-header">
                            <h2 class="settings-title">
                                <i class="mdi mdi-database-outline"></i> Data Management
                            </h2>
                        </div>
                        <div class="p-4">
                            <div class="mb-4 text-center">
                                <h5 class="mb-3">Export Applications Data</h5>
                                <form method="POST">
                                    <button type="submit" name="export_type" value="excel" class="btn btn-outline-primary">
                                        <i class="mdi mdi-file-excel-outline me-2"></i> Export to Excel (CSV)
                                    </button>
                                </form>
                                <div class="mt-3 text-muted small">
                                    <i class="mdi mdi-information-outline me-1"></i>
                                    Downloads as CSV (compatible with Excel)
                                </div>
                            </div>
                            
                            <hr class="my-4 bg-dark-border">
                            
                            <div>
                                <h5 class="mb-3 text-danger">Danger Zone</h5>
                                <div class="alert alert-danger">
                                    <i class="mdi mdi-alert-outline me-2"></i>
                                    These actions are irreversible. Please proceed with caution.
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Delete Account</h6>
                                        <small class="text-muted">Permanently remove your account and all data</small>
                                    </div>
                                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                        <i class="mdi mdi-delete-outline me-2"></i> Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-danger">
                        <i class="mdi mdi-alert-circle-outline me-2"></i> Confirm Account Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-light">
                    <div class="alert alert-danger">
                        <strong>⚠️ Warning:</strong> This action cannot be undone. All your data will be permanently deleted.
                    </div>
                    <p class="mb-3">The following will be permanently deleted:</p>
                    <ul class="mb-4">
                        <li>Your recruiter profile</li>
                        <li>All job postings</li>
                        <li>Candidate applications</li>
                        <li>Company information</li>
                    </ul>
                    <form method="POST" id="deleteAccountForm">
                        <div class="mb-3">
                            <label class="form-label">Enter your password to confirm deletion:</label>
                            <input type="password" class="form-control bg-dark text-light border-secondary" 
                                   name="delete_password" placeholder="Your password" required>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete_account" class="btn btn-danger">
                                <i class="mdi mdi-delete-outline me-1"></i> Delete My Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manager Photo Upload and Cropping
        const chooseManagerPhotoBtn = document.getElementById('chooseManagerPhotoBtn');
        const managerPhotoUpload = document.getElementById('managerPhotoUpload');
        const managerPhotoPreview = document.getElementById('managerPhotoPreview');
        const managerCropperContainer = document.getElementById('managerCropperContainer');
        const saveManagerCropBtn = document.getElementById('saveManagerCropBtn');
        const cancelManagerCropBtn = document.getElementById('cancelManagerCropBtn');
        const croppedManagerImage = document.getElementById('croppedManagerImage');
        
        let managerCropper;

        chooseManagerPhotoBtn.addEventListener('click', function() {
            managerPhotoUpload.click();
        });

        managerPhotoUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.type.match('image.*')) {
                alert('Please select an image file');
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('Image must be less than 2MB');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                managerPhotoPreview.classList.add('d-none');
                managerCropperContainer.classList.remove('d-none');
                saveManagerCropBtn.classList.remove('d-none');
                cancelManagerCropBtn.classList.remove('d-none');
                
                const image = document.createElement('img');
                image.id = 'managerImageToCrop';
                image.src = event.target.result;
                managerCropperContainer.innerHTML = '';
                managerCropperContainer.appendChild(image);
                
                managerCropper = new Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 0.8,
                    responsive: true,
                    guides: false
                });
            };
            reader.readAsDataURL(file);
        });

        saveManagerCropBtn.addEventListener('click', function() {
            if (managerCropper) {
                const canvas = managerCropper.getCroppedCanvas({
                    width: 300,
                    height: 300,
                    minWidth: 256,
                    minHeight: 256,
                    maxWidth: 1024,
                    maxHeight: 1024,
                    fillColor: '#fff',
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                
                croppedManagerImage.value = canvas.toDataURL('image/png');
                
                managerPhotoPreview.src = canvas.toDataURL('image/png');
                managerPhotoPreview.classList.remove('d-none');
                managerCropperContainer.classList.add('d-none');
                saveManagerCropBtn.classList.add('d-none');
                cancelManagerCropBtn.classList.add('d-none');
                
                // Create a temporary form for submission
                const tempForm = document.createElement('form');
                tempForm.method = 'POST';
                tempForm.action = window.location.href;
                tempForm.style.display = 'none';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'cropped_manager_image';
                input.value = croppedManagerImage.value;
                
                tempForm.appendChild(input);
                document.body.appendChild(tempForm);
                tempForm.submit();
            }
        });

        cancelManagerCropBtn.addEventListener('click', function() {
            managerPhotoPreview.classList.remove('d-none');
            managerCropperContainer.classList.add('d-none');
            saveManagerCropBtn.classList.add('d-none');
            cancelManagerCropBtn.classList.add('d-none');
            managerPhotoUpload.value = '';
        });

        // Company Logo Upload and Cropping
        const chooseLogoBtn = document.getElementById('chooseLogoBtn');
        const logoUpload = document.getElementById('logoUpload');
        const logoPreview = document.getElementById('logoPreview');
        const logoCropperContainer = document.getElementById('logoCropperContainer');
        const saveLogoCropBtn = document.getElementById('saveLogoCropBtn');
        const cancelLogoCropBtn = document.getElementById('cancelLogoCropBtn');
        const croppedCompanyLogo = document.getElementById('croppedCompanyLogo');
        const fileError = document.getElementById('fileError');
        
        let logoCropper;

        chooseLogoBtn.addEventListener('click', function() {
            logoUpload.click();
        });

        logoUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.type.match('image.*')) {
                fileError.textContent = 'Please select an image file';
                fileError.classList.remove('d-none');
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                fileError.textContent = 'Image must be less than 2MB';
                fileError.classList.remove('d-none');
                return;
            }

            fileError.classList.add('d-none');
            
            const reader = new FileReader();
            reader.onload = function(event) {
                logoPreview.classList.add('d-none');
                logoCropperContainer.classList.remove('d-none');
                saveLogoCropBtn.classList.remove('d-none');
                cancelLogoCropBtn.classList.remove('d-none');
                
                const image = document.createElement('img');
                image.id = 'logoImageToCrop';
                image.src = event.target.result;
                logoCropperContainer.innerHTML = '';
                logoCropperContainer.appendChild(image);
                
                logoCropper = new Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 0.8,
                    responsive: true,
                    guides: false
                });
            };
            reader.readAsDataURL(file);
        });

        saveLogoCropBtn.addEventListener('click', function() {
            if (logoCropper) {
                const canvas = logoCropper.getCroppedCanvas({
                    width: 300,
                    height: 300,
                    minWidth: 256,
                    minHeight: 256,
                    maxWidth: 1024,
                    maxHeight: 1024,
                    fillColor: '#fff',
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                
                croppedCompanyLogo.value = canvas.toDataURL('image/png');
                
                logoPreview.src = canvas.toDataURL('image/png');
                logoPreview.classList.remove('d-none');
                logoCropperContainer.classList.add('d-none');
                saveLogoCropBtn.classList.add('d-none');
                cancelLogoCropBtn.classList.add('d-none');
                
                // Create a temporary form for submission
                const tempForm = document.createElement('form');
                tempForm.method = 'POST';
                tempForm.action = window.location.href;
                tempForm.style.display = 'none';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'cropped_company_logo';
                input.value = croppedCompanyLogo.value;
                
                tempForm.appendChild(input);
                document.body.appendChild(tempForm);
                tempForm.submit();
            }
        });

        cancelLogoCropBtn.addEventListener('click', function() {
            logoPreview.classList.remove('d-none');
            logoCropperContainer.classList.add('d-none');
            saveLogoCropBtn.classList.add('d-none');
            cancelLogoCropBtn.classList.add('d-none');
            logoUpload.value = '';
        });

        // Delete account form submission
        document.getElementById('deleteAccountForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
    </script>
    
    <?php include __DIR__ . '/Includes/footer.php'; ?>
</body>
</html>