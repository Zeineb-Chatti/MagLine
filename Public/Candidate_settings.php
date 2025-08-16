<?php
session_start();
require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'candidate') {
    header("Location: Login.php");
    exit;
}

$candidateId = $_SESSION['user_id'];
$candidateData = [];
$errors = [];

define('PROFILE_PHOTOS_DIR', '/Public/Uploads/profile_photos/');
define('SERVER_UPLOAD_DIR', __DIR__ . '/..' . PROFILE_PHOTOS_DIR);
define('DEFAULT_PROFILE', '/Public/Assets/default-user.png');

try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, location, created_at, photo as profile_photo
        FROM users 
        WHERE id = ? AND role = 'candidate'
    ");
    $stmt->execute([$candidateId]);
    $candidateData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$candidateData) {
        setFlashMessage('danger', 'Candidate profile not found');
        header("Location: Dashboard_Candidate.php");
        exit;
    }

    $_SESSION['user_photo'] = $candidateData['profile_photo'] ?? null;
    $_SESSION['user_name'] = $candidateData['name'] ?? 'Candidate';

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    setFlashMessage('danger', 'Database error occurred');
    header("Location: Dashboard_Candidate.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {   
    if (isset($_POST['update_profile_photo'])) {
        handleProfilePhotoUpload();
    }

    if (isset($_POST['change_email'])) {
        handleEmailChange();
    }

    if (isset($_POST['change_password'])) {
        handlePasswordChange();
    }

    if (isset($_POST['delete_account'])) {
        handleAccountDeletion();
    }
}

$profileUrl = getProfilePhotoUrl($candidateData['profile_photo'] ?? null);
$activeTab = $_GET['tab'] ?? 'profile';

// Helper functions
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function getProfilePhotoUrl($filename) {
    if (empty($filename)) {
        return DEFAULT_PROFILE . '?v=' . time();
    }
    return PROFILE_PHOTOS_DIR . $filename . '?v=' . time();
}

function handleProfilePhotoUpload() {
    global $pdo, $candidateId, $candidateData, $errors;

    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading file";
        return;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    $file = $_FILES['profile_photo'];

    if (!in_array($file['type'], $allowedTypes)) {
        $errors[] = "Only JPG, PNG, and GIF images are allowed";
        return;
    }

    if ($file['size'] > $maxSize) {
        $errors[] = "Image size must be less than 2MB";
        return;
    }

    if (!file_exists(SERVER_UPLOAD_DIR)) {
        mkdir(SERVER_UPLOAD_DIR, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'profile_' . $candidateId . '_' . time() . '.' . $extension;
    $targetFile = SERVER_UPLOAD_DIR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        $errors[] = "Failed to upload profile photo";
        return;
    }

    try {
        if (!empty($candidateData['profile_photo'])) {
            $oldFile = SERVER_UPLOAD_DIR . $candidateData['profile_photo'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?");
        $stmt->execute([$fileName, $candidateId]);

        $_SESSION['photo'] = $fileName;
        $candidateData['profile_photo'] = $fileName;

        setFlashMessage('success', 'Profile photo updated successfully');
        header("Location: Candidate_settings.php?tab=profile");
        exit;
    } catch (PDOException $e) {
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }
        error_log("Profile photo update error: " . $e->getMessage());
        $errors[] = "Failed to update profile photo in database";
    }
}

function handleEmailChange() {
    global $pdo, $candidateId, $candidateData, $errors;

    $newEmail = trim($_POST['new_email'] ?? '');
    $currentPassword = $_POST['password_for_email'] ?? '';

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
        return;
    }

    if (empty($currentPassword)) {
        $errors[] = "Current password is required";
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$candidateId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $errors[] = "Current password is incorrect";
            return;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $candidateId]);
        if ($stmt->fetch()) {
            $errors[] = "This email is already in use by another account";
            return;
        }

        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$newEmail, $candidateId]);

        $_SESSION['user_email'] = $newEmail;
        $candidateData['email'] = $newEmail;

        setFlashMessage('success', 'Email updated successfully');
    } catch (PDOException $e) {
        error_log("Email change error: " . $e->getMessage());
        $errors[] = "Failed to change email. Please try again.";
    }
}

function handlePasswordChange() {
    global $pdo, $candidateId, $errors;

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword)) {
        $errors[] = "Current password is required";
    }

    if (strlen($newPassword) < 8) {
        $errors[] = "Password must be at least 8 characters";
    } elseif (!preg_match('/[0-9]/', $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
        $errors[] = "Password must contain at least one number and one special character";
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match";
    }

    if (!empty($errors)) {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$candidateId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $errors[] = "Current password is incorrect";
            return;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $candidateId]);

        setFlashMessage('success', 'Password changed successfully');
        header("Location: Candidate_settings.php?tab=security");
        exit;
    } catch (PDOException $e) {
        error_log("Password change error: " . $e->getMessage());
        $errors[] = "Failed to change password. Please try again.";
    }
}

function handleAccountDeletion() {
    global $pdo, $candidateId, $errors;
    
    $password = $_POST['delete_password'] ?? '';
    
    if (empty($password)) {
        $errors[] = "Password is required";
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$candidateId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = "Incorrect password";
            return;
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
        $stmt->execute([$candidateId]);
        $userPhoto = $stmt->fetch();
        
        if ($userPhoto && !empty($userPhoto['photo'])) {
            $photoPath = SERVER_UPLOAD_DIR . $userPhoto['photo'];
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$candidateId]);
        
        $pdo->commit();

        session_destroy();
        setFlashMessage('success', 'Your account has been deleted successfully');
        header("Location: Login.php");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Account deletion error: " . $e->getMessage());
        $errors[] = "Failed to delete account. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | <?= htmlspecialchars($candidateData['name'] ?? 'Candidate Portal') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@6.5.95/css/materialdesignicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/candidate.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <style>
        .account-delete-section {
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
            margin-top: 2rem;
        }
        .security-checklist {
            list-style-type: none;
            padding-left: 0;
        }
        .security-checklist li {
            margin-bottom: 0.5rem;
        }
        .security-checklist .valid {
            color: var(--success);
        }
        .security-checklist .invalid {
            color: var(--danger);
        }
        .profile-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
        }
        .cropper-container {
            max-width: 100%;
            max-height: 300px;
        }
    </style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/Candidate_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/Candidate_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid py-4">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
                        <?= htmlspecialchars($_SESSION['flash_message']['message']) ?>
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
                
                <div class="profile-header bg-dark-card rounded-3 p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title">
                                <i class="mdi mdi-cog-outline me-2"></i>Account Settings
                            </h1>
                            <p class="page-subtitle">
                                Manage your account preferences and security
                            </p>
                        </div>
                    </div>
                </div>

                <div class="settings-tabs mb-4">
                    <a href="?tab=profile" class="settings-tab <?= $activeTab === 'profile' ? 'active' : '' ?>">
                        <i class="mdi mdi-account-outline"></i> Profile
                    </a>
                    <a href="?tab=security" class="settings-tab <?= $activeTab === 'security' ? 'active' : '' ?>">
                        <i class="mdi mdi-lock-outline"></i> Security
                    </a>
                </div>
                
                <?php if ($activeTab === 'profile'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="settings-card mb-4">
                            <div class="settings-header">
                                <h2 class="settings-title">
                                    <i class="mdi mdi-account-circle-outline"></i> Profile Photo
                                </h2>
                            </div>
                            <div class="p-4 text-center">
                                <form method="POST" enctype="multipart/form-data" id="profilePhotoForm">
                                    <div class="profile-picture-section mb-4">
                                        <div class="profile-preview-container mx-auto">
                                            <img src="<?= $profileUrl ?>" 
                                                 id="profilePreview"
                                                 class="profile-preview rounded-circle"
                                                 onerror="this.onerror=null;this.src='<?= DEFAULT_PROFILE ?>'">
                                            <div class="cropper-container d-none" id="cropperContainer"></div>
                                        </div>
                                        <div class="mt-3">
                                            <input type="file" id="profileUpload" name="profile_photo" accept="image/*" class="d-none">
                                            <button type="button" class="btn btn-outline-primary" id="chooseProfileBtn">
                                                <i class="mdi mdi-camera me-1"></i> Change Photo
                                            </button>
                                            <button type="button" class="btn btn-success d-none" id="saveCropBtn">
                                                <i class="mdi mdi-check me-1"></i> Apply
                                            </button>
                                            <button type="button" class="btn btn-outline-danger d-none" id="cancelCropBtn">
                                                <i class="mdi mdi-close me-1"></i> Cancel
                                            </button>
                                        </div>
                                        <div id="fileError" class="alert alert-danger d-none mt-2"></div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="settings-card">
                            <div class="settings-header">
                                <h2 class="settings-title">
                                    <i class="mdi mdi-email-outline"></i> Email Address
                                </h2>
                            </div>
                            <div class="p-4">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Current Email</label>
                                        <input type="email" class="form-control" value="<?= htmlspecialchars($candidateData['email'] ?? '') ?>" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">New Email Address</label>
                                        <input type="email" class="form-control" name="new_email" required>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label">Confirm Current Password</label>
                                        <input type="password" class="form-control" name="password_for_email" required>
                                    </div>
                                    
                                    <button type="submit" name="change_email" class="btn btn-primary w-100">
                                        <i class="mdi mdi-email-send-outline me-2"></i> Update Email Address
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($activeTab === 'security'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="settings-card mb-4">
                            <div class="settings-header">
                                <h2 class="settings-title">
                                    <i class="mdi mdi-lock-outline"></i> Change Password
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
                                        <div class="form-text">Password requirements:</div>
                                        <ul class="security-checklist">
                                            <li id="length-check" class="invalid">At least 8 characters</li>
                                            <li id="number-check" class="invalid">Contains a number</li>
                                            <li id="special-check" class="invalid">Contains a special character</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                        <div id="password-match" class="form-text invalid"></div>
                                    </div>
                                    
                                    <button type="submit" name="change_password" class="btn btn-primary w-100" id="change-password-btn" disabled>
                                        <i class="mdi mdi-lock-reset me-2"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="settings-card">
                            <div class="settings-header">
                                <h2 class="settings-title">
                                    <i class="mdi mdi-shield-account-outline"></i> Security Information
                                </h2>
                            </div>
                            <div class="p-4">
                                <div class="account-delete-section">
                                    <h5 class="mb-3"><i class="mdi mdi-alert-outline me-2"></i>Danger Zone</h5>
                                    <div class="alert alert-warning">
                                        <p><strong>Delete Your Account</strong></p>
                                        <p>This will permanently delete your account and all associated data. This action cannot be undone.</p>
                                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                            <i class="mdi mdi-delete-outline me-1"></i> Delete Account
                                        </button>
                                    </div>
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
            <div class="modal-content">
                <form method="POST" id="deleteAccountForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Account Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete your account? All your data will be permanently removed.</p>
                        <p>To confirm, please enter your password:</p>
                        <input type="password" class="form-control" name="delete_password" id="deleteAccountPassword" placeholder="Your password" required>
                        <div id="deleteAccountError" class="text-danger mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_account" class="btn btn-danger">
                            <i class="mdi mdi-delete-outline me-1"></i> Delete Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Profile photo upload and cropping
        const chooseProfileBtn = document.getElementById('chooseProfileBtn');
        const profileUpload = document.getElementById('profileUpload');
        const profilePreview = document.getElementById('profilePreview');
        const cropperContainer = document.getElementById('cropperContainer');
        const saveCropBtn = document.getElementById('saveCropBtn');
        const cancelCropBtn = document.getElementById('cancelCropBtn');
        const fileError = document.getElementById('fileError');
        
        let cropper;

        chooseProfileBtn.addEventListener('click', function() {
            profileUpload.click();
        });

        profileUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.type.match('image.*')) {
                showFileError('Please select an image file');
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                showFileError('Image must be less than 2MB');
                return;
            }

            hideFileError();
            
            const reader = new FileReader();
            reader.onload = function(event) {
                profilePreview.classList.add('d-none');
                cropperContainer.classList.remove('d-none');
                saveCropBtn.classList.remove('d-none');
                cancelCropBtn.classList.remove('d-none');
                
                const image = document.createElement('img');
                image.id = 'imageToCrop';
                image.src = event.target.result;
                cropperContainer.innerHTML = '';
                cropperContainer.appendChild(image);
                
                cropper = new Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 0.8,
                    responsive: true,
                    guides: false
                });
            };
            reader.readAsDataURL(file);
        });

        saveCropBtn.addEventListener('click', function() {
            if (cropper) {
                const canvas = cropper.getCroppedCanvas({
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
                
                canvas.toBlob(function(blob) {
                    const formData = new FormData();
                    formData.append('profile_photo', blob, 'profile.png');
                    formData.append('update_profile_photo', '1');
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        if (response.redirected) {
                            window.location.href = response.url;
                        }
                    });
                }, 'image/png');
            }
        });

        cancelCropBtn.addEventListener('click', function() {
            profilePreview.classList.remove('d-none');
            cropperContainer.classList.add('d-none');
            saveCropBtn.classList.add('d-none');
            cancelCropBtn.classList.add('d-none');
            profileUpload.value = '';
        });

        const newPasswordInput = document.querySelector('input[name="new_password"]');
        const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
        const changePasswordBtn = document.getElementById('change-password-btn');
        const passwordMatchText = document.getElementById('password-match');
        
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', validatePassword);
            confirmPasswordInput.addEventListener('input', validatePassword);
        }

        function validatePassword() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            let isValid = true;
            
            toggleValidationClass('length-check', password.length >= 8);
            isValid = isValid && password.length >= 8;

            toggleValidationClass('number-check', /\d/.test(password));
            isValid = isValid && /\d/.test(password);
   
            toggleValidationClass('special-check', /[^A-Za-z0-9]/.test(password));
            isValid = isValid && /[^A-Za-z0-9]/.test(password);
   
            if (password && confirmPassword) {
                if (password === confirmPassword) {
                    passwordMatchText.textContent = 'Passwords match';
                    passwordMatchText.classList.add('valid');
                    passwordMatchText.classList.remove('invalid');
                } else {
                    passwordMatchText.textContent = 'Passwords do not match';
                    passwordMatchText.classList.add('invalid');
                    passwordMatchText.classList.remove('valid');
                    isValid = false;
                }
            } else {
                passwordMatchText.textContent = '';
            }

            changePasswordBtn.disabled = !isValid;
        }
        
        function toggleValidationClass(elementId, isValid) {
            const element = document.getElementById(elementId);
            if (isValid) {
                element.classList.add('valid');
                element.classList.remove('invalid');
            } else {
                element.classList.add('invalid');
                element.classList.remove('valid');
            }
        }
        
        function showFileError(message) {
            fileError.textContent = message;
            fileError.classList.remove('d-none');
        }
        
        function hideFileError() {
            fileError.classList.add('d-none');
        }
    
        document.getElementById('deleteAccountForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const formData = new FormData(form);
            const errorElement = document.getElementById('deleteAccountError');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                } else {
                    return response.text();
                }
            })
            .then(text => {
                errorElement.textContent = 'Incorrect password or server error';
            })
            .catch(error => {
                errorElement.textContent = 'An error occurred. Please try again.';
                console.error('Error:', error);
            });
        });
    });
    </script>
    
    <?php include __DIR__ . '/Includes/footer.php'; ?>
</body>
</html>
