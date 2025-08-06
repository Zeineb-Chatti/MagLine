<?php
session_start();
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../App/Helpers/notification_functions.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}

$recruiterId = $_SESSION['user_id'];
$offerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$offerId) {
    header("Location: manage_jobs.php");
    exit;
}

// Define valid employment types
$employmentTypes = ['Full-time', 'Part-time', 'Contract'];

// Fetch offer details
try {
    $stmt = $pdo->prepare("SELECT * FROM offers WHERE id = ? AND recruiter_id = ?");
    $stmt->execute([$offerId, $recruiterId]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$offer) {
        $_SESSION['flash_message'] = ['type'=>'danger','message'=>'Offer not found or access denied'];
        header("Location: manage_jobs.php");
        exit;
    }
    
    // Fetch associated skills
    $stmt = $pdo->prepare("SELECT skill_id FROM offer_skills WHERE offer_id = ?");
    $stmt->execute([$offerId]);
    $currentSkills = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: manage_jobs.php");
    exit;
}

// On form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $employmentType = trim($_POST['employment_type']);
    $skills = isset($_POST['skills']) ? array_map('intval', $_POST['skills']) : [];
    
    // Initialize coordinates
    $latitude = null;
    $longitude = null;
    
    // Skip geocoding for "Remote" jobs
    if (!empty($location) && strtolower($location) !== 'remote') {
        // Prepare geocoding request
        $geocodeUrl = "https://nominatim.openstreetmap.org/search?format=json&q=" . 
                     urlencode($location) . "&limit=1";
        
        // Configure cURL request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $geocodeUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'JobPlatform/1.0 (contact@yourdomain.com)',
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Process geocoding response
        if (!$curlError && $httpCode === 200) {
            $geoData = json_decode($response, true);
            if (!empty($geoData) && isset($geoData[0]['lat']) && isset($geoData[0]['lon'])) {
                $latitude = (float)$geoData[0]['lat'];
                $longitude = (float)$geoData[0]['lon'];
                error_log("Geocoding success: {$location} => {$latitude},{$longitude}");
            } else {
                error_log("Geocoding failed - no coordinates found for: {$location}");
            }
        } else {
            error_log("Geocoding API error: HTTP {$httpCode}, Error: {$curlError}");
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update offer details
        $update = $pdo->prepare("UPDATE offers SET 
                                title = ?, 
                                description = ?, 
                                location = ?, 
                                employment_type = ?,
                                latitude = ?,
                                longitude = ?
                                WHERE id = ?");
        $update->execute([
            $title,
            $description,
            $location,
            $employmentType,
            $latitude,
            $longitude,
            $offerId
        ]);
        
        // Update skills
        $delete = $pdo->prepare("DELETE FROM offer_skills WHERE offer_id = ?");
        $delete->execute([$offerId]);
        
        if (!empty($skills)) {
            $insertSkill = $pdo->prepare("INSERT INTO offer_skills (offer_id, skill_id) VALUES (?, ?)");
            foreach ($skills as $skillId) {
                $insertSkill->execute([$offerId, $skillId]);
            }
        }
        
        // Call Flask API for skill extraction if description changed
        if ($description !== $offer['description'] || $location !== $offer['location']) {
            $flaskApiUrl = 'http://localhost:5001/extract_skills_from_text';
            $postData = json_encode([
                'text' => $description,
                'is_job_description' => true,
                'location' => $location,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);

            $ch = curl_init($flaskApiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!$curlError && $httpCode === 200) {
                $json = json_decode($response, true);
                if ($json && !empty($json['skills']) && is_array($json['skills'])) {
                    $extractedSkills = $json['skills'];
                    
                    $stmtSelectSkill = $pdo->prepare("SELECT id FROM skills WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1");
                    $stmtInsertSkill = $pdo->prepare("INSERT INTO skills (name) VALUES (:name)");
                    $stmtLinkSkill = $pdo->prepare("INSERT IGNORE INTO offer_skills (offer_id, skill_id) VALUES (:offer_id, :skill_id)");
                    
                    foreach ($extractedSkills as $skillName) {
                        $skillNameClean = trim($skillName);
                        
                        if (empty($skillNameClean) || is_numeric($skillNameClean) || strlen($skillNameClean) < 2) {
                            continue;
                        }
                        
                        $stmtSelectSkill->execute([':name' => $skillNameClean]);
                        $skillId = $stmtSelectSkill->fetchColumn();
                        
                        if (!$skillId) {
                            $stmtInsertSkill->execute([':name' => $skillNameClean]);
                            $skillId = $pdo->lastInsertId();
                        }
                        
                        $stmtLinkSkill->execute([
                            ':offer_id' => $offerId,
                            ':skill_id' => $skillId
                        ]);
                    }
                }
            }
        }
        
             $pdo->commit();

        // Notify all applicants about the job update
        $stmt = $pdo->prepare("SELECT candidate_id FROM applications WHERE offer_id = ?");
        $stmt->execute([$offerId]);
        $applicants = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // After job update
        foreach ($applicants as $applicantId) {
            addNotification(
                $pdo,
                $applicantId,
                "Job '".htmlspecialchars($title)."' has been updated",
                'job_update',  // Type
                $offerId       // related_id = job_id
            );
        }
        
        $_SESSION['flash_message'] = ['type'=>'success','message'=>'Offer updated successfully'];
        header("Location: manage_jobs.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating offer: " . $e->getMessage());
        $error = 'Error updating offer: ' . $e->getMessage();
    }
}

$skillsByCategory = [
    'Programming Languages' => [
        ['id' => 1, 'name' => 'Python'],
        ['id' => 2, 'name' => 'JavaScript'],
        ['id' => 3, 'name' => 'TypeScript'],
        ['id' => 4, 'name' => 'Java'],
        ['id' => 5, 'name' => 'C#'],
        ['id' => 6, 'name' => 'Go'],
        ['id' => 7, 'name' => 'Rust'],
        ['id' => 8, 'name' => 'Swift'],
        ['id' => 9, 'name' => 'Kotlin'],
        ['id' => 10, 'name' => 'Ruby'],
        ['id' => 11, 'name' => 'PHP'],
        ['id' => 12, 'name' => 'R'],
        ['id' => 13, 'name' => 'Scala']
    ],
    'Web Development' => [
        ['id' => 14, 'name' => 'React.js'],
        ['id' => 15, 'name' => 'Vue.js'],
        ['id' => 16, 'name' => 'Angular'],
        ['id' => 17, 'name' => 'Node.js'],
        ['id' => 18, 'name' => 'Next.js'],
        ['id' => 19, 'name' => 'Nuxt.js'],
        ['id' => 20, 'name' => 'Express.js'],
        ['id' => 21, 'name' => 'Django'],
        ['id' => 22, 'name' => 'Spring Boot'],
        ['id' => 23, 'name' => 'Laravel'],
        ['id' => 24, 'name' => 'GraphQL'],
        ['id' => 25, 'name' => 'REST API'],
        ['id' => 26, 'name' => 'WebSockets']
    ],
    'Data & AI' => [
        ['id' => 27, 'name' => 'Machine Learning'],
        ['id' => 28, 'name' => 'TensorFlow'],
        ['id' => 29, 'name' => 'PyTorch'],
        ['id' => 30, 'name' => 'SQL'],
        ['id' => 31, 'name' => 'NoSQL'],
        ['id' => 32, 'name' => 'Data Analysis'],
        ['id' => 33, 'name' => 'Computer Vision'],
        ['id' => 34, 'name' => 'NLP'],
        ['id' => 35, 'name' => 'Big Data'],
        ['id' => 36, 'name' => 'Data Visualization'],
        ['id' => 37, 'name' => 'Reinforcement Learning'],
        ['id' => 38, 'name' => 'Time Series Analysis'],
        ['id' => 39, 'name' => 'Data Engineering']
    ],
    'Cloud & DevOps' => [
        ['id' => 40, 'name' => 'AWS'],
        ['id' => 41, 'name' => 'Azure'],
        ['id' => 42, 'name' => 'GCP'],
        ['id' => 43, 'name' => 'Docker'],
        ['id' => 44, 'name' => 'Kubernetes'],
        ['id' => 45, 'name' => 'Terraform'],
        ['id' => 46, 'name' => 'CI/CD'],
        ['id' => 47, 'name' => 'Serverless'],
        ['id' => 48, 'name' => 'Ansible'],
        ['id' => 49, 'name' => 'Prometheus'],
        ['id' => 50, 'name' => 'Grafana'],
        ['id' => 51, 'name' => 'Helm'],
        ['id' => 52, 'name' => 'GitOps']
    ],
    'Mobile Development' => [
        ['id' => 53, 'name' => 'React Native'],
        ['id' => 54, 'name' => 'Flutter'],
        ['id' => 55, 'name' => 'iOS Development'],
        ['id' => 56, 'name' => 'Android Development'],
        ['id' => 57, 'name' => 'Ionic'],
        ['id' => 58, 'name' => 'Xamarin'],
        ['id' => 59, 'name' => 'Kotlin Multiplatform']
    ],
    'Cybersecurity' => [
        ['id' => 60, 'name' => 'Ethical Hacking'],
        ['id' => 61, 'name' => 'Penetration Testing'],
        ['id' => 62, 'name' => 'Cryptography'],
        ['id' => 63, 'name' => 'Network Security'],
        ['id' => 64, 'name' => 'Security Compliance']
    ],
    'Design & UX' => [
        ['id' => 65, 'name' => 'UI/UX Design'],
        ['id' => 66, 'name' => 'Figma'],
        ['id' => 67, 'name' => 'Sketch'],
        ['id' => 68, 'name' => 'Adobe Creative Suite'],
        ['id' => 69, 'name' => 'User Research'],
        ['id' => 70, 'name' => 'Prototyping']
    ],
    'Project Management' => [
        ['id' => 71, 'name' => 'Agile/Scrum'],
        ['id' => 72, 'name' => 'JIRA'],
        ['id' => 73, 'name' => 'Trello'],
        ['id' => 74, 'name' => 'Product Management'],
        ['id' => 75, 'name' => 'Risk Management']
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Job Offer | MagLine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../Public/Assets/CSS/main.css" rel="stylesheet">
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <style>
        :root {
            --dark-blue: #0a0a2a;
            --deep-indigo: #1a1a5a;
            --purple-glow: #4d50ffff;
            --pink-glow: #d16aff;
            --blue-glow: #3b82f6;
            --text-light: #e0e7ff;
            --text-muted: #8a9eff;
            --glass-bg: rgba(10, 10, 42, 0.75);
            --glass-border: rgba(77, 107, 255, 0.51);
            --shadow-glow: 0 0 15px rgba(124, 77, 255, 0.45);
            --input-bg: rgba(10, 10, 42, 0.85);
            --input-border: rgba(77, 80, 255, 0.46);
            --input-focus: rgba(77, 130, 255, 0.45);
        }
        
        .skills-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        
        .skill-category {
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 8px rgba(61, 90, 252, 0.4);
        }
        
        .skill-category:hover {
            border-color: var(--pink-glow);
            box-shadow: 0 0 15px rgba(71, 74, 254, 0.61);
        }
        
        .category-header {
            padding: 15px 20px;
            background: rgba(124, 77, 255, 0.1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        
        .category-header:hover {
            background: rgba(77, 95, 255, 0.15);
        }
        
        .category-title {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-light);
        }
        
        .category-toggle {
            font-size: 1rem;
            color: var(--pink-glow);
            transition: transform 0.3s ease;
        }
        
        .category-header.active .category-toggle i {
            transform: rotate(90deg);
        }
        
        .category-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
            will-change: max-height;
        }
        
        .category-content.active {
            max-height: 2000px;
            transition: max-height 0.6s ease;
        }
        
        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            padding: 15px;
        }
        
        .skill-item {
            position: relative;
        }
        
        .skill-checkbox {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .skill-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1.5px solid var(--input-border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .skill-checkbox:checked + .skill-label {
            background: linear-gradient(135deg, rgba(106, 138, 255, 0.3), rgba(59, 130, 246, 0.3));
            border-color: var(--blue-glow);
            color: white;
            box-shadow: 0 0 10px rgba(92, 110, 230, 0.53));
        }
        
        .skill-label:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .skill-checkmark {
            width: 16px;
            height: 16px;
            border: 1.5px solid var(--text-muted);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .skill-checkbox:checked + .skill-label .skill-checkmark {
            background: var(--blue-glow);
            border-color: var(--pink-glow);
        }
        
        .skill-checkmark::after {
            content: '✓';
            opacity: 0;
            color: white;
            font-weight: bold;
            font-size: 0.7rem;
            transition: opacity 0.3s ease;
        }
        
        .skill-checkbox:checked + .skill-label .skill-checkmark::after {
            opacity: 1;
        }
        
        .form-control {
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            color: var(--text-light);
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: var(--input-bg);
            border-color: var(--input-focus);
            color: var(--text-light);
            box-shadow: var(--shadow-glow);
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='%238a9eff' d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
        }
        
        .edit-offer-form {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--shadow-glow);
        }
        
        .page-title {
            color: var(--text-light);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .form-label {
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .required {
            color: #ff6b6b;
        }
        
        .error-text {
            color: #ff6b6b;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--purple-glow), var(--pink-glow));
            border: none;
            color: white;
            font-weight: 500;
            padding: 10px 25px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(209, 106, 255, 0.4);
        }
    </style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/recruiter_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/recruiter_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid">
                <h1 class="page-title"><i class="bi bi-pencil-fill me-2"></i>Edit Job Offer</h1>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="edit-offer-form">
                    <div class="mb-4">
                        <label class="form-label">Job Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" 
                               value="<?= htmlspecialchars($offer['title']) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Job Description <span class="required">*</span></label>
                        <textarea name="description" rows="6" class="form-control" required><?= htmlspecialchars($offer['description']) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Location <span class="required">*</span></label>
                        <input type="text" name="location" class="form-control" 
                               value="<?= htmlspecialchars($offer['location']) ?>" required>
                        <small class="text-muted">Enter "Remote" for remote positions</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Employment Type <span class="required">*</span></label>
                        <select name="employment_type" class="form-control" required>
                            <option value="">Select Employment Type</option>
                            <?php foreach ($employmentTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" 
                                    <?= $offer['employment_type'] === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Required Skills <span class="required">*</span></label>
                        <div class="skills-container">
                            <?php foreach ($skillsByCategory as $category => $skills): 
                                $categorySlug = htmlspecialchars(strtolower(str_replace(' ', '-', $category)));
                            ?>
                                <div class="skill-category">
                                    <div class="category-header" onclick="toggleCategory('<?= $categorySlug ?>', event)">
                                        <div class="category-title"><?= htmlspecialchars($category) ?></div>
                                        <div class="category-toggle">
                                            <i class="fas fa-chevron-right"></i>
                                        </div>
                                    </div>
                                    <div class="category-content" id="category-<?= $categorySlug ?>">
                                        <div class="skills-grid">
                                            <?php foreach ($skills as $skill): ?>
                                                <div class="skill-item">
                                                    <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>" 
                                                           id="skill-<?= $skill['id'] ?>" class="skill-checkbox" 
                                                           <?= in_array($skill['id'], $currentSkills) ? 'checked' : '' ?>>
                                                    <label for="skill-<?= $skill['id'] ?>" class="skill-label">
                                                        <div class="skill-checkmark"></div>
                                                        <span><?= htmlspecialchars($skill['name']) ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <a href="manage_jobs.php" class="btn btn-outline-light me-2">
                            <i class="bi bi-x-circle me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-gradient">
                            <i class="bi bi-check-circle me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCategory(categoryName, event) {
            event.preventDefault();
            event.stopPropagation();
            
            const header = event.currentTarget;
            const content = document.getElementById(`category-${categoryName}`);
            
            if (!content) {
                console.error('Content element not found for category:', categoryName);
                return;
            }
            
            header.classList.toggle('active');
            content.classList.toggle('active');
            
            // Rotate the chevron icon
            const chevron = header.querySelector('.category-toggle i');
            if (chevron) {
                chevron.style.transform = header.classList.contains('active') ? 'rotate(90deg)' : 'rotate(0deg)';
            }
        }
        
        // Initialize categories with selected skills on page load
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.skill-checkbox:checked');
            
            checkboxes.forEach(checkbox => {
                const categoryItem = checkbox.closest('.skill-category');
                if (!categoryItem) return;
                
                const header = categoryItem.querySelector('.category-header');
                const content = categoryItem.querySelector('.category-content');
                const chevron = categoryItem.querySelector('.category-toggle i');
                
                if (header && content) {
                    header.classList.add('active');
                    content.classList.add('active');
                    if (chevron) {
                        chevron.style.transform = 'rotate(90deg)';
                    }
                }
            });
        });
    </script>
</body>
</html>