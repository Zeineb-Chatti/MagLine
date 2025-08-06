<?php
session_start();
$role = $_GET['role'] ?? $_SESSION['user_role'] ?? 'candidate';

// Display errors if any
if (isset($_SESSION['profile_errors'])) {
    echo "<div class='alert alert-danger'>";
    foreach ($_SESSION['profile_errors'] as $error) {
        echo "<p>" . htmlspecialchars($error) . "</p>";
    }
    echo "</div>";
    unset($_SESSION['profile_errors']);
}

// Restore form data if available
$formData = $_SESSION['profile_form_data'] ?? [];
if (!empty($formData)) {
    unset($_SESSION['profile_form_data']);
}

// Database connection to fetch skills
$host = 'localhost';
$dbname = 'magline';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch all skills to map names to IDs (case-insensitive)
    $stmt = $pdo->query("SELECT id, LOWER(name) as lower_name, name FROM skills");
    $allSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create lookup arrays
    $skillNamesToIds = [];
    $skillIdToNames = [];
    foreach ($allSkills as $skill) {
        $skillNamesToIds[$skill['lower_name']] = $skill['id'];
        $skillIdToNames[$skill['id']] = $skill['name'];
    }

    // Skill categories with their associated skills
    $skillCategories = [
        'Programming Languages' => ['Python', 'JavaScript', 'TypeScript', 'Java', 'C#', 'Go', 'Rust', 'Swift', 'Kotlin', 'Ruby', 'PHP', 'R', 'Scala'],
        'Web Development' => ['React.js', 'Vue.js', 'Angular', 'Node.js', 'Next.js', 'Nuxt.js', 'Express.js', 'Django', 'Spring Boot', 'Laravel', 'GraphQL', 'REST API', 'WebSockets'],
        'Data & AI' => ['Machine Learning', 'TensorFlow', 'PyTorch', 'SQL', 'NoSQL', 'Data Analysis', 'Computer Vision', 'NLP (Natural Language Processing)', 'Big Data', 'Data Visualization', 'Reinforcement Learning', 'Time Series Analysis', 'Data Engineering'],
        'Cloud & DevOps' => ['AWS', 'Azure', 'GCP (Google Cloud Platform)', 'Docker', 'Kubernetes', 'Terraform', 'CI/CD', 'Serverless', 'Ansible', 'Prometheus', 'Grafana', 'Helm', 'GitOps'],
        'Mobile Development' => ['React Native', 'Flutter', 'iOS Development', 'Android Development', 'Ionic', 'Xamarin', 'Kotlin Multiplatform'],
        'Cybersecurity' => ['Ethical Hacking', 'Penetration Testing', 'Cryptography', 'Network Security', 'Security Compliance'],
        'Design & UX' => ['UI/UX Design', 'Figma', 'Sketch', 'Adobe Creative Suite', 'User Research', 'Prototyping'],
        'Project Management' => ['Agile/Scrum', 'JIRA', 'Trello', 'Product Management', 'Risk Management']
    ];

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Complete Your Profile - MagLine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link rel="icon" href="../Public/Assets/favicon.png" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --dark-blue: #0a0a2a;
            --deep-indigo: #1a1a5a;
            --purple-glow: #7c4dff;
            --pink-glow: #d16aff;
            --blue-glow: #3b82f6;
            --text-light: #e0e7ff;
            --text-muted: #8a9eff;
            --glass-bg: rgba(10, 10, 42, 0.75);
            --glass-border: rgba(124, 77, 255, 0.25);
            --shadow-glow: 0 0 15px rgba(124, 77, 255, 0.45);
            --input-bg: rgba(10, 10, 42, 0.85);
            --input-border: rgba(124, 77, 255, 0.3);
            --input-focus: rgba(124, 77, 255, 0.25);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--dark-blue), var(--deep-indigo), var(--pink-glow));
            color: var(--text-light);
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
            scrollbar-width: none;
        }
        body::-webkit-scrollbar {
            display: none;
        }

        #aiBackgroundCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -10;
            background: transparent;
            display: block;
            will-change: transform;
        }

        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            z-index: 10;
        }

        .profile-card {
            background: var(--glass-bg);
            backdrop-filter: blur(25px);
            border: 1.5px solid var(--glass-border);
            border-radius: 20px;
            padding: 3rem 2.8rem;
            max-width: 900px;
            width: 100%;
            position: relative;
            box-shadow: var(--shadow-glow);
            overflow: visible;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .profile-card::before,
        .profile-card::after {
            content: '';
            position: absolute;
            left: 10%;
            right: 10%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--purple-glow), transparent);
            box-shadow: 0 0 15px var(--purple-glow);
            border-radius: 10px;
            opacity: 0.65;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .profile-card::before {
            top: 0;
        }

        .profile-card::after {
            bottom: 0;
        }

        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 30px rgba(124, 77, 255, 0.7), 0 10px 40px rgba(209, 106, 255, 0.3);
            border-color: var(--pink-glow);
        }

        .profile-card:hover::before,
        .profile-card:hover::after {
            opacity: 1;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }

        .profile-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            letter-spacing: 0.03em;
            position: relative;
            display: block;
            text-shadow: 0 0 8px var(--pink-glow);
        }

        .profile-subtitle {
            font-size: 1rem;
            color: var(--pink-glow);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: block;
            text-shadow: 0 0 5px rgba(209, 106, 255, 0.5);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: rgba(209, 106, 255, 0.15);
            color: var(--pink-glow);
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            border: 1.5px solid rgba(209, 106, 255, 0.3);
            backdrop-filter: blur(5px);
            box-shadow: 0 0 10px rgba(209, 106, 255, 0.3);
            transition: all 0.3s ease;
            margin: 0 auto;
        }

        .role-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(209, 106, 255, 0.5);
        }

        .role-badge i {
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.95rem;
            letter-spacing: 0.02em;
            transition: color 0.3s ease;
            user-select: none;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1.5rem;
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: 10px;
            color: var(--text-light);
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(5px);
            box-shadow: inset 0 0 8px rgba(124, 77, 255, 0.25);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--pink-glow);
            background: var(--input-focus);
            box-shadow: 0 0 8px var(--pink-glow), inset 0 0 12px var(--pink-glow);
            color: var(--text-light);
        }

        .form-input::placeholder {
            color: var(--text-muted);
            opacity: 0.8;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: all 0.3s ease;
            pointer-events: none;
            filter: drop-shadow(0 0 1.5px rgba(209, 106, 255, 0.6));
        }

        .form-input:focus ~ .input-icon {
            color: var(--pink-glow);
            filter: drop-shadow(0 0 5px var(--pink-glow));
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-display {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem 1.5rem;
            border: 1.5px dashed var(--input-border);
            border-radius: 10px;
            background: var(--input-bg);
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: inset 0 0 8px rgba(124, 77, 255, 0.25);
        }

        .file-input-display:hover {
            border-color: var(--pink-glow);
            background: var(--input-focus);
        }

        .file-input-display i {
            font-size: 1.2rem;
            color: var(--pink-glow);
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
            box-shadow: inset 0 0 8px rgba(124, 77, 255, 0.15);
        }

        .skill-category:hover {
            border-color: var(--pink-glow);
            box-shadow: 0 0 15px rgba(209, 106, 255, 0.3);
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
            background: rgba(124, 77, 255, 0.15);
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
            background: linear-gradient(135deg, rgba(209, 106, 255, 0.3), rgba(59, 130, 246, 0.3));
            border-color: var(--pink-glow);
            color: white;
            box-shadow: 0 0 10px rgba(209, 106, 255, 0.3);
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
            background: var(--pink-glow);
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

        .submit-container {
            text-align: center;
            margin-top: 30px;
        }

        .submit-button {
            width: 100%;
            padding: 1.1rem 2rem;
            background: linear-gradient(135deg, var(--pink-glow), var(--blue-glow));
            border: none;
            border-radius: 10px;
            color: var(--text-light);
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(209, 106, 255, 0.3);
            user-select: none;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
        }

        .submit-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            transition: left 0.6s ease;
            pointer-events: none;
        }

        .submit-button:hover::before {
            left: 100%;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(209, 106, 255, 0.5);
        }

        .submit-button:active {
            transform: translateY(0);
        }

        .section-title {
            font-family: 'Inter', sans-serif;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--pink-glow);
            margin: 1.5rem 0 1rem;
            text-shadow: 0 0 5px rgba(209, 106, 255, 0.5);
        }

        select.form-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238a9eff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        @media (max-width: 768px) {
            .profile-card {
                padding: 2.5rem 1.5rem;
                margin: 1rem;
            }

            .profile-title {
                font-size: 2rem;
            }

            .skills-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid var(--pink-glow);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
.form-group label.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.95rem;
    letter-spacing: 0.02em;
    transition: color 0.3s ease;
    user-select: none;
}

select.form-input option {
    background-color: var(--dark-blue);
    color: var(--text-light);
    padding: 10px 15px;
}
/* Fix for autofill overriding focus styles */
#email:-webkit-autofill,
#email:-webkit-autofill:hover, 
#email:-webkit-autofill:focus {
  -webkit-text-fill-color: var(--text-light) !important;
  -webkit-box-shadow: 
    0 0 0px 1000px var(--input-focus) inset, /* Background */
    0 0 8px var(--pink-glow),               /* Outer glow */
    inset 0 0 12px var(--pink-glow) !important; /* Inner glow */
  border: 1.5px solid var(--pink-glow) !important;
  transition: all 5000s ease-in-out 0s; /* Prevent auto-revert */
}
    </style>
</head>
<body>
    <canvas id="aiBackgroundCanvas"></canvas>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="main-container">
        <div class="profile-card">
            <div class="profile-header">
                <h1 class="profile-title">Complete Your Profile</h1>
                <div class="role-badge">
                    <i class="fas <?= $role === 'recruiter' ? 'fa-user-shield' : 'fa-user-tie' ?>"></i>
                    <span><?= ucfirst($role) ?></span>
                </div>
            </div>

            <form id="profileForm" action="SetupProfile_Process.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?? '' ?>" />
                <input type="hidden" name="role" value="<?= $role ?>" />

                <?php if ($role === 'candidate'): ?>
                    <div class="form-group">
                        <label class="form-label" for="photo">Profile Photo</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="photo" id="photo" class="file-input" accept="image/*" />
                            <div class="file-input-display">
                                <i class="fas fa-camera"></i>
                                <span>Choose profile photo</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input type="text" name="full_name" id="full_name" class="form-input" placeholder="Enter your full name" value="<?= htmlspecialchars($formData['full_name'] ?? '') ?>" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" name="phone" id="phone" class="form-input" placeholder="Enter your phone number" value="<?= htmlspecialchars($formData['phone'] ?? '') ?>" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="location">Location</label>
                        <input type="text" name="location" id="location" class="form-input" placeholder="Enter your location" value="<?= htmlspecialchars($formData['location'] ?? '') ?>" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="linkedin">LinkedIn Profile</label>
                        <input type="url" name="linkedin" id="linkedin" class="form-input" placeholder="https://linkedin.com/in/yourprofile" value="<?= htmlspecialchars($formData['linkedin'] ?? '') ?>" />
                    </div>

                    <div class="form-group"> 
                        <label class="form-label" for="cv">CV/Resume (PDF)</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="cv" id="cv" class="file-input" accept=".pdf" required />
                            <div class="file-input-display">
                                <i class="fas fa-file-pdf"></i>
                                <span>Upload your CV/Resume</span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($_SESSION['extracted_skills'])): ?>
                        <div class="alert alert-info">
                            <strong>Skills detected in your CV:</strong>
                            <ul>
                                <?php foreach ($_SESSION['extracted_skills'] as $skill): ?>
                                    <li><?= htmlspecialchars($skill) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php unset($_SESSION['extracted_skills']); ?>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label" for="about">About You</label>
                        <textarea name="about" id="about" class="form-input" placeholder="Tell us about yourself, your experience, and what you're looking for..."><?= htmlspecialchars($formData['about'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Skills & Expertise</label>
                        <div class="skills-container">
                            <?php
                            // Track if we need to open the first category with selected skills
                            $firstCategoryWithSelectionOpened = false;
                            
                            foreach ($skillCategories as $categoryName => $skillsInThisCategory) {
                                // Filter skills to only include those found in the database
                                $validSkillsForCategory = [];
                                foreach ($skillsInThisCategory as $skillName) {
                                    $lowerName = strtolower($skillName);
                                    if (isset($skillNamesToIds[$lowerName])) {
                                        $validSkillsForCategory[$skillName] = $skillNamesToIds[$lowerName];
                                    }
                                }

                                if (!empty($validSkillsForCategory)) {
                                    $categoryId = 'category-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($categoryName));
                                    $hasSelectedSkills = false;
                                    ?>
                                    <div class="skill-category">
                                        <div class="category-header" onclick="toggleCategory('<?= $categoryId ?>')">
                                            <div class="category-title"><?= htmlspecialchars($categoryName) ?></div>
                                            <div class="category-toggle">
                                                <i class="fas fa-chevron-right"></i>
                                            </div>
                                        </div>
                                        <div class="category-content" id="<?= $categoryId ?>">
                                            <div class="skills-grid">
                                                <?php foreach ($validSkillsForCategory as $skillName => $skillId): 
                                                    $isChecked = (isset($formData['skills']) && is_array($formData['skills']) && in_array($skillId, $formData['skills']));
                                                    $hasSelectedSkills = $hasSelectedSkills || $isChecked;
                                                    $input_id = 'skill-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($skillName));
                                                    ?>
                                                    <div class="skill-item">
                                                        <input type="checkbox" name="skills[]" value="<?= $skillId ?>" id="<?= $input_id ?>" class="skill-checkbox" <?= $isChecked ? 'checked' : '' ?>>
                                                        <label for="<?= $input_id ?>" class="skill-label">
                                                            <div class="skill-checkmark"></div>
                                                            <span><?= htmlspecialchars($skillName) ?></span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    // Open this category if it has selected skills and we haven't opened one yet
                                    if ($hasSelectedSkills && !$firstCategoryWithSelectionOpened) {
                                        echo "<script>document.addEventListener('DOMContentLoaded', function() { toggleCategory('$categoryId'); });</script>";
                                        $firstCategoryWithSelectionOpened = true;
                                    }
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                <!-- Recruiter Profile Fields -->
                    <h3 class="section-title">Company Information</h3>

                    <div class="form-group">
                        <label class="form-label" for="company_name">Company Name</label>
                        <input type="text" name="company_name" id="company_name" class="form-input" placeholder="Enter your company name" required />
                    </div>

                    <div class="form-group">
    <label class="form-label" for="company_size">Company Size</label>
    <select name="company_size" id="company_size" class="form-input" required>
        <option value="" disabled selected>Select company size</option>
        <option value="1-10">1-10 employees</option>
        <option value="11-50">11-50 employees</option>
        <option value="51-200">51-200 employees</option>
        <option value="201-500">201-500 employees</option>
        <option value="501-1000">501-1000 employees</option>
        <option value="1000+">1000+ employees</option>
    </select>
</div>

                    <div class="form-group">
                        <label class="form-label" for="industry">Industry</label>
                        <input type="text" name="industry" id="industry" class="form-input" placeholder="Enter your industry" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="company_website">Company Website</label>
                        <input type="url" name="company_website" id="company_website" class="form-input" placeholder="https://yourcompany.com" />
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="company_logo">Company Logo</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="company_logo" id="company_logo" class="file-input" accept="image/*" />
                            <div class="file-input-display">
                                <i class="fas fa-image"></i>
                                <span>Upload company logo</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="about_company">About Company</label>
                        <textarea name="about_company" id="about_company" class="form-input" placeholder="Tell us about your company..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="location">Company Location</label>
                        <input type="text" name="location" id="location" class="form-input" placeholder="Enter company location" required />
                    </div>

                    <h3 class="section-title">Your Information</h3>

                    <div class="form-group">
                        <label class="form-label" for="manager_name">Your Name</label>
                        <input type="text" name="manager_name" id="manager_name" class="form-input" placeholder="Enter your full name" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="manager_job_title">Job Title</label>
                        <input type="text" name="manager_job_title" id="manager_job_title" class="form-input" placeholder="Enter your job title" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="manager_phone">Phone Number</label>
                        <input type="tel" name="manager_phone" id="manager_phone" class="form-input" placeholder="Enter your phone number" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="manager_email">Email</label>
                        <input type="email" name="manager_email" id="manager_email" class="form-input" placeholder="Enter your email" required />
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="manager_photo">Your Photo</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="manager_photo" id="manager_photo" class="file-input" accept="image/*" />
                            <div class="file-input-display">
                                <i class="fas fa-camera"></i>
                                <span>Upload your photo</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>


                <div class="submit-container">
                    <button type="submit" class="submit-button">
                        Complete Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Canvas animation
        const canvas = document.getElementById('aiBackgroundCanvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        const numParticles = 50;
        const particleSize = 2;
        const connectionDistance = 150;
        const animationSpeed = 0.5;

        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        function Particle(x, y) {
            this.x = x;
            this.y = y;
            this.vx = (Math.random() - 0.5) * animationSpeed;
            this.vy = (Math.random() - 0.5) * animationSpeed;
            this.alpha = Math.random();
            this.color = `rgba(209, 106, 255, ${this.alpha})`;
        }

        Particle.prototype.update = function() {
            this.x += this.vx;
            this.y += this.vy;

            if (this.x < 0 || this.x > canvas.width) this.vx *= -1;
            if (this.y < 0 || this.y > canvas.height) this.vy *= -1;
        };

        Particle.prototype.draw = function() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, particleSize, 0, Math.PI * 2);
            ctx.fillStyle = this.color;
            ctx.fill();
        };

        function initParticles() {
            particles = [];
            for (let i = 0; i < numParticles; i++) {
                const x = Math.random() * canvas.width;
                const y = Math.random() * canvas.height;
                particles.push(new Particle(x, y));
            }
        }

        function animate() {
            requestAnimationFrame(animate);
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();

                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const distance = Math.sqrt(dx * dx + dy * dy);

                    if (distance < connectionDistance) {
                        ctx.beginPath();
                        ctx.strokeStyle = `rgba(124, 77, 255, ${1 - (distance / connectionDistance)})`;
                        ctx.lineWidth = 0.5;
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.stroke();
                    }
                }
            }
        }

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            initParticles();
        });

        initParticles();
        animate();

        // Category toggling
        function toggleCategory(categoryId) {
            const categoryContent = document.getElementById(categoryId);
            const categoryHeader = categoryContent.previousElementSibling;

            if (categoryContent.style.maxHeight) {
                categoryContent.style.maxHeight = null;
                categoryHeader.classList.remove('active');
            } else {
                categoryContent.style.maxHeight = categoryContent.scrollHeight + "px";
                categoryHeader.classList.add('active');
            }
        }
        
        // Form submission loading overlay
        document.getElementById('profileForm').addEventListener('submit', function(event) {
            // Basic validation check before showing overlay
            let isValid = true;
            document.querySelectorAll('.form-input[required]').forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                }
            });

            // Specific check for CV if role is candidate and it's required
            const roleInput = document.querySelector('input[name="role"]').value;
            if (roleInput === 'candidate') {
                const cvInput = document.getElementById('cv');
                if (cvInput && cvInput.required && cvInput.files.length === 0) {
                    isValid = false;
                }
            }

            if (isValid === false) {
                // Scroll to the first invalid field for better UX
                document.querySelectorAll('.form-input[required]').forEach(field => {
                    if (!field.value.trim() && isValid !== null) {
                        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        isValid = null;
                    }
                });
                if (roleInput === 'candidate') {
                    const cvInput = document.getElementById('cv');
                    if (cvInput && cvInput.required && cvInput.files.length === 0 && isValid !== null) {
                        cvInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        isValid = null;
                    }
                }
            }
            
            if (!isValid) {
                event.preventDefault();
                return;
            }
            
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.add('active');
            
            setTimeout(() => {
                this.submit();
            }, 1000);
        });

        // Form validation with visual feedback (blur event)
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.required && !this.value.trim()) {
                    this.style.borderColor = '#ff4757';
                    this.style.boxShadow = '0 0 0 3px rgba(255, 71, 87, 0.1)';
                } else {
                    this.style.borderColor = 'rgba(124, 77, 255, 0.3)';
                    this.style.boxShadow = 'none';
                }
            });
        });

        // Initialize file input displays
        document.addEventListener('DOMContentLoaded', function() {
            // Restore file input display names
            document.querySelectorAll('.file-input').forEach(input => {
                input.addEventListener('change', function() {
                    const displaySpan = this.nextElementSibling.querySelector('span');
                    if (this.files.length > 0) {
                        displaySpan.textContent = this.files[0].name;
                        this.nextElementSibling.style.borderColor = 'rgba(124, 77, 255, 0.3)';
                        this.nextElementSibling.style.boxShadow = 'none';
                    } else {
                        if (this.id === 'photo') {
                            displaySpan.textContent = 'Choose profile photo';
                        } else if (this.id === 'cv') {
                            displaySpan.textContent = 'Upload your CV/Resume';
                        } else if (this.id === 'company_logo') {
                            displaySpan.textContent = 'Upload company logo';
                        } else if (this.id === 'manager_photo') {
                            displaySpan.textContent = 'Upload manager\'s photo';
                        }
                    }
                });
                // Set initial display name if a file was previously selected
                if (input.files.length > 0) {
                    input.dispatchEvent(new Event('change'));
                }
            });
        });
    </script>
</body>
</html>