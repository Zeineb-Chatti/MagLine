<?php
session_start();
$baseURL = '/MagLine/Public';

require_once __DIR__ . '/../Config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'recruiter') {
    header("Location: ../Auth/login.php");
    exit;
}

$employmentTypes = [
    'Full-time',
    'Part-time',
    'Contract'
];

$workTypes = [
    'on-site' => ['label' => 'On-site', 'icon' => 'fas fa-building'],
    'remote' => ['label' => 'Remote', 'icon' => 'fas fa-home'],
    'hybrid' => ['label' => 'Hybrid', 'icon' => 'fas fa-laptop-house']
];

$formData = $_SESSION['form_data'] ?? [
    'title' => '',
    'description' => '',
    'location' => '',
    'employment_type' => '',
    'work_type' => 'on-site', // Default value
    'skills' => []
];
$errors = $_SESSION['form_errors'] ?? [];
$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['form_data'], $_SESSION['form_errors'], $_SESSION['flash_message']);

$userId = $_SESSION['user_id'];
$headerProfilePicture = '../Public/Assets/default-user.png';

$headerManagerName = $headerManagerName ?? $_SESSION['user_name'] ?? 'User';
try {
    $stmt = $pdo->prepare("SELECT name, company_name, company_logo, photo FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $managerName = htmlspecialchars($user['name'] ?? 'Recruiter'); 
        $companyName = htmlspecialchars($user['company_name'] ?? 'Your Company');
        $companyLogo = !empty($user['company_logo']) 
            ? '../Public/Assets/Uploads/Avatars/' . htmlspecialchars($user['company_logo'])
            : '../Public/Assets/default-company.png';
        $profilePicture = !empty($user['photo']) 
            ? '../Public/Uploads/profile_pictures/' . htmlspecialchars($user['photo'])
            : '../Public/Assets/default-user.png';
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
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
    <title>Post Job Offer | MagLine</title>
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
        
        /* Work Type Radio Styles */
        .work-type-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .work-type-item {
            position: relative;
        }
        
        .work-type-radio {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .work-type-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 20px 15px;
            background: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .work-type-label::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(77, 80, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .work-type-radio:checked + .work-type-label::before {
            left: 100%;
        }
        
        .work-type-label:hover {
            border-color: var(--pink-glow);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(77, 80, 255, 0.3);
        }
        
        .work-type-radio:checked + .work-type-label {
            background: linear-gradient(135deg, rgba(77, 80, 255, 0.3), rgba(59, 130, 246, 0.3));
            border-color: var(--blue-glow);
            color: white;
            box-shadow: 0 0 20px rgba(77, 80, 255, 0.6);
            transform: scale(1.05);
        }
        
        .work-type-icon {
            font-size: 1.5rem;
            color: var(--text-muted);
            transition: all 0.3s ease;
        }
        
        .work-type-radio:checked + .work-type-label .work-type-icon {
            color: var(--blue-glow);
            text-shadow: 0 0 10px rgba(59, 130, 246, 0.8);
        }
        
        .work-type-text {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-light);
            transition: all 0.3s ease;
        }
        
        .work-type-radio:checked + .work-type-label .work-type-text {
            color: white;
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.8);
        }
        
        /* Add a subtle pulse animation for the selected option */
        .work-type-radio:checked + .work-type-label {
            animation: workTypePulse 2s infinite;
        }
        
        @keyframes workTypePulse {
            0%, 100% { box-shadow: 0 0 20px rgba(77, 80, 255, 0.6); }
            50% { box-shadow: 0 0 30px rgba(77, 80, 255, 0.8); }
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
        .category-header.active .category-toggle {
            transform: rotate(90deg);
        }
        .category-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
        }
        .category-content.active {
            max-height: 800px;
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
            box-shadow: 0 0 10px rgba(92, 110, 230, 0.53);
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
            /* Fix for autocomplete changing label colors */
.form-input:-webkit-autofill,
.form-input:-webkit-autofill:hover, 
.form-input:-webkit-autofill:focus {
  -webkit-text-fill-color: var(--text-light) !important;
  -webkit-box-shadow: 0 0 0px 1000px var(--input-bg) inset !important;
  transition: background-color 5000s ease-in-out 0s;
}


    </style>
</head>
<body class="dark-theme">
    <?php include __DIR__ . '/Includes/recruiter_header.php'; ?>
    
    <div class="main-wrapper">
        <?php include __DIR__ . '/Includes/recruiter_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="form-container">
                <h1><i class="bi bi-briefcase-fill"></i> Post a Job Offer</h1>

                <?php if($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="PostOffer_Process.php" novalidate>
                    <div class="mb-4">
                        <label class="form-label">Job Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control <?= isset($errors['title'])?'is-invalid':'' ?>"
                            value="<?= htmlspecialchars($formData['title']) ?>" required>
                        <?php if(isset($errors['title'])): ?>
                            <div class="error-text"><?= htmlspecialchars($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Job Description <span class="required">*</span></label>
                        <textarea name="description" rows="5" class="form-control <?= isset($errors['description'])?'is-invalid':'' ?>"
                            required><?= htmlspecialchars($formData['description']) ?></textarea>
                        <?php if(isset($errors['description'])): ?>
                            <div class="error-text"><?= htmlspecialchars($errors['description']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Location <span class="required">*</span></label>
                        <input type="text" name="location" class="form-control <?= isset($errors['location'])?'is-invalid':'' ?>"
                            value="<?= htmlspecialchars($formData['location']) ?>" required>
                        <?php if(isset($errors['location'])): ?>
                            <div class="error-text"><?= htmlspecialchars($errors['location']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Employment Type <span class="required">*</span></label>
                        <select name="employment_type" class="form-control <?= isset($errors['employment_type'])?'is-invalid':'' ?>" required>
                            <option value="">Select Employment Type</option>
                            <?php foreach ($employmentTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" 
                                    <?= ($formData['employment_type'] ?? '') === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(isset($errors['employment_type'])): ?>
                            <div class="error-text"><?= htmlspecialchars($errors['employment_type']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Work Type <span class="required">*</span></label>
                        <div class="work-type-container">
                            <?php foreach ($workTypes as $value => $config): ?>
                                <div class="work-type-item">
                                    <input type="radio" name="work_type" value="<?= htmlspecialchars($value) ?>" 
                                           id="work-type-<?= htmlspecialchars($value) ?>" class="work-type-radio" 
                                           <?= ($formData['work_type'] ?? 'on-site') === $value ? 'checked' : '' ?> required>
                                    <label for="work-type-<?= htmlspecialchars($value) ?>" class="work-type-label">
                                        <i class="<?= htmlspecialchars($config['icon']) ?> work-type-icon"></i>
                                        <span class="work-type-text"><?= htmlspecialchars($config['label']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if(isset($errors['work_type'])): ?>
                            <div class="error-text"><?= htmlspecialchars($errors['work_type']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Required Skills <span class="required">*</span></label>
                        <div class="skills-container">
                            <?php foreach ($skillsByCategory as $category => $skills): ?>
                                <div class="skill-category">
                                    <div class="category-header" onclick="toggleCategory('<?= htmlspecialchars(strtolower(str_replace(' ', '-', $category))) ?>')">
                                        <div class="category-title"><?= htmlspecialchars($category) ?></div>
                                        <div class="category-toggle">
                                            <i class="fas fa-chevron-right"></i>
                                        </div>
                                    </div>
                                    <div class="category-content" id="category-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $category))) ?>">
                                        <div class="skills-grid">
                                            <?php foreach ($skills as $skill): ?>
                                                <div class="skill-item">
                                                    <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>" 
                                                           id="skill-<?= $skill['id'] ?>" class="skill-checkbox" 
                                                           <?= in_array($skill['id'], $formData['skills']) ? 'checked' : '' ?>>
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
                        <?php if(isset($errors['skills'])): ?>
                            <div class="error-text"><?= htmlspecialchars($errors['skills']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send-fill"></i> Post Job Offer
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCategory(categoryName) {
            const header = event.currentTarget;
            const content = document.getElementById(`category-${categoryName}`);
            
            header.classList.toggle('active');
            content.classList.toggle('active');
        }
    </script>
</body>
</html>