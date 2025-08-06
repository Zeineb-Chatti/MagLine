<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$candidateMessageCount = $candidateMessageCount ?? 0;
$candidateApplicationCount = $candidateApplicationCount ?? 0;
?>

<aside class="candidate-sidebar">
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-item <?= $currentPage === 'Dashboard_Candidate.php' ? 'active' : '' ?>">
                <a href="Dashboard_Candidate.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="nav-item <?= $currentPage === 'jobs.php' ? 'active' : '' ?>">
                <a href="jobs.php">
                    <i class="bi bi-search"></i>
                    <span>Search Jobs</span>
                </a>
            </div>

            <div class="nav-item <?= $currentPage === 'Candidate_applications.php' ? 'active' : '' ?>">
                <a href="Candidate_applications.php">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>My Applications</span>
                    <?php if ($candidateApplicationCount > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $candidateApplicationCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <div class="nav-section">
            <h4 class="section-title">Messages & Profile</h4>

            <div class="nav-item <?= $currentPage === 'messages.php' ? 'active' : '' ?>">
                <a href="messages.php" class="d-flex align-items-center">
                    <i class="bi bi-chat-dots"></i>
                    <span class="ms-2">Messages</span>
                    <?php if ($candidateMessageCount > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $candidateMessageCount ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="nav-item <?= $currentPage === 'Candidate_profile.php' ? 'active' : '' ?>">
                <a href="Candidate_profile.php">
                    <i class="bi bi-person-circle"></i>
                    <span>Profile</span>
                </a>
            </div>
        </div>

        <div class="nav-section">
            <h4 class="section-title">Settings</h4>

            <div class="nav-item <?= $currentPage === 'Candidate_settings.php' ? 'active' : '' ?>">
                <a href="Candidate_settings.php">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </nav>
</aside>