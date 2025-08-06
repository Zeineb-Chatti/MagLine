<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarOfferCount = $sidebarOfferCount ?? 0;
$sidebarAppCount = $sidebarAppCount ?? 0;
$recruiterMessageCount = $recruiterMessageCount ?? 0;
?>
<aside class="recruiter-sidebar">
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-item <?= $currentPage === 'Dashboard_Recruiter.php' ? 'active' : '' ?>">
                <a href="Dashboard_Recruiter.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="nav-item <?= $currentPage === 'Post_offer.php' ? 'active' : '' ?>">
                <a href="Post_offer.php">
                    <i class="bi bi-plus-circle"></i>
                    <span>Post New Job</span>
                </a>
            </div>
            
            <div class="nav-item <?= $currentPage === 'manage_jobs.php' ? 'active' : '' ?>">
                <a href="manage_jobs.php" class="d-flex align-items-center">
                    <i class="bi bi-briefcase"></i>
                    <span class="ms-2">Manage Jobs</span>
                    <?php if ($sidebarOfferCount > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $sidebarOfferCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item <?= $currentPage === 'applications.php' ? 'active' : '' ?>">
                <a href="applications.php" class="d-flex align-items-center">
                    <i class="bi bi-file-earmark-text"></i>
                    <span class="ms-2">Applications</span>
                    <?php if ($sidebarAppCount > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $sidebarAppCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <h4 class="section-title">Candidate Management</h4>
            
            <div class="nav-item <?= $currentPage === 'candidates.php' ? 'active' : '' ?>">
                <a href="candidates.php">
                    <i class="bi bi-people"></i>
                    <span>Candidates</span>
                </a>
            </div>
            

            <div class="nav-item <?= $currentPage === 'messages.php' ? 'active' : '' ?>">
                <a href="messages.php" class="d-flex align-items-center">
                    <i class="bi bi-chat-dots"></i>
                    <span class="ms-2">Messages</span>
                    <?php if ($recruiterMessageCount > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $recruiterMessageCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <h4 class="section-title">Company</h4>
            
            <div class="nav-item <?= $currentPage === 'company_profile.php' ? 'active' : '' ?>">
                <a href="company_profile.php">
                    <i class="bi bi-building"></i>
                    <span>Company Profile</span>
                </a>
            </div>
            
            <div class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
                <a href="reports.php">
                    <i class="bi bi-graph-up"></i>
                    <span>Reports</span>
                </a>
            </div>
            
            <div class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                <a href="settings.php">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </nav>
</aside>