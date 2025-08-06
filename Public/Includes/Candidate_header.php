<?php
$headerProfilePicture = isset($_SESSION['photo']) 
    ? '/Public/Uploads/profile_photos/' . $_SESSION['photo']
    : '../Public/Assets/default-user.png';
$headerCandidateName = $_SESSION['user_name'] ?? 'Candidate';
?>

<header class="candidate-header">
    <div class="header-left">
        <button class="menu-toggle" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <span class="header-brand">MagLine</span>
    </div>

    <!-- Profile Search Section -->
    <div class="profile-search-container">
        <div class="search-input-wrapper">
            <i class="bi bi-search search-icon"></i>
            <input type="text" 
                   id="profileSearchInput" 
                   class="profile-search-input" 
                   placeholder="Search profiles, companies..."
                   autocomplete="off">
            <button class="clear-search" id="clearSearch" style="display: none;">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <div class="search-results" id="searchResults"></div>
    </div>

    <div class="header-actions">
        <!-- Messages Icon -->
        <div class="messages-dropdown">
            <button class="messages-icon" id="messagesButton" title="Messages">
                <i class="bi bi-chat-dots"></i>
                <span class="messages-badge" id="messagesBadge" style="display: none;">0</span>
            </button>
        </div>

        <!-- Notification Dropdown -->
        <div class="notification-dropdown">
            <button class="notification-icon" id="notificationButton">
                <i class="bi bi-bell"></i>
                <span class="notification-badge" id="notificationBadge">0</span>
            </button>
            <div class="notification-panel" id="notificationPanel">
                <div class="notification-header">
                    <h5>Notifications</h5>
                    <div class="notification-actions">
                        <button class="mark-all-read">Mark all read</button>
                        <button class="view-all-btn" id="viewAllNotifications">View All</button>
                    </div>
                </div>
                <div class="notification-list" id="notificationList">
                    <div class="notification-loader">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Dropdown -->
        <div class="profile-dropdown">
            <div class="profile-dropdown-toggle">
                <img src="<?= $headerProfilePicture ?>" alt="Profile" class="profile-avatar" 
                     onerror="this.onerror=null;this.src='../Public/Assets/default-user.png'">
                <span class="profile-name"><?= htmlspecialchars($headerCandidateName) ?></span>
                <i class="bi bi-chevron-down"></i>
            </div>
            <div class="profile-dropdown-menu">
                <a href="Candidate_settings.php"><i class="bi bi-gear me-2"></i> Settings</a>
                <a href="Candidate_profile.php"><i class="bi bi-person me-2"></i> Profile</a>
                <a href="Logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- All Notifications Modal -->
    <div class="all-notifications-modal" id="allNotificationsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>All Notifications</h3>
                <button class="close-modal" id="closeModal">&times;</button>
            </div>
            <div class="modal-body" id="allNotificationsContainer">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
/* ========================================
   PROFILE SEARCH STYLES
   ======================================== */

.profile-search-container {
    position: relative;
    flex: 1;
    max-width: 400px;
    margin: 0 2rem;
    margin-right: 30%;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    left: 12px;
    color: var(--dark-text-secondary);
    font-size: 1rem;
    z-index: 2;
}

.profile-search-input {
    width: 100%;
    padding: 0.75rem 2.5rem 0.75rem 2.5rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid var(--dark-border);
    border-radius: 25px;
    color: var(--dark-text);
    font-size: 0.9rem;
    transition: all 0.3s ease;
    outline: none;
}

.profile-search-input::placeholder {
    color: var(--dark-text-secondary);
}

.profile-search-input:focus {
    background: rgba(255, 255, 255, 0.12);
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(58, 134, 255, 0.1);
}

.clear-search {
    position: absolute;
    right: 8px;
    background: none;
    border: none;
    color: var(--dark-text-secondary);
    padding: 4px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.clear-search:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--dark-text);
}

.search-results {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: var(--dark-card);
    border: 1px solid var(--dark-border);
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    max-height: 400px;
    overflow-y: auto;
    z-index: 1002;
    display: none;
}

.search-results.show {
    display: block;
}

.search-result-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--dark-border);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background: rgba(58, 134, 255, 0.1);
}

.search-result-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--dark-border);
}

.search-result-info {
    flex: 1;
}

.search-result-name {
    font-weight: 600;
    color: var(--dark-text);
    margin-bottom: 0.25rem;
}

.search-result-details {
    font-size: 0.8rem;
    color: var(--dark-text-secondary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.search-result-role {
    background: rgba(58, 134, 255, 0.2);
    color: var(--primary-color);
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 500;
}

.search-result-role.recruiter {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.search-no-results {
    padding: 2rem;
    text-align: center;
    color: var(--dark-text-secondary);
    font-size: 0.9rem;
}

.search-loading {
    padding: 1.5rem;
    text-align: center;
    color: var(--dark-text-secondary);
}

/* Add to existing styles */
.search-result-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
    margin-right: 0.5rem;
}

.recruiter-badge {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.candidate-badge {
    background: rgba(58, 134, 255, 0.2);
    color: var(--primary-color);
}

.search-result-meta {
    font-size: 0.75rem;
    color: var(--dark-text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
/* ========================================
   MESSAGES SYSTEM - DARK THEME
   ======================================== */

/* Messages dropdown container */
.messages-dropdown {
    position: relative;
    margin-right: 15px;
}

/* Messages icon */
.messages-icon {
    position: relative;
    background: none;
    border: none;
    font-size: 1.3rem;
    color: var(--dark-text-secondary);
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.messages-icon:hover {
    background: rgba(140, 184, 252, 0.1);
    color: #8CB4FC;
}

/* Messages badge */
.messages-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    background: var(--danger-color);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: none;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    border: 2px solid var(--dark-card);
}

/* ========================================
   NOTIFICATION SYSTEM - DARK THEME
   ======================================== */

/* Notification dropdown container */
.notification-dropdown {
    position: relative;
    margin-right: 15px;
}

/* Notification bell icon */
.notification-icon {
    position: relative;
    background: none;
    border: none;
    font-size: 1.3rem;
    color: var(--dark-text-secondary);
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-icon:hover {
    background: rgba(58, 134, 255, 0.1);
    color: var(--primary-color);
}

/* Notification badge */
.notification-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    background: var(--danger-color);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    border: 2px solid var(--dark-card);
}

/* Notification panel */
.notification-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    width: 380px;
    max-height: 500px;
    background: var(--dark-card);
    border: 1px solid var(--dark-border);
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    z-index: 1001;
    transform: translateY(10px);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.notification-panel.active {
    transform: translateY(0);
    opacity: 1;
    visibility: visible;
}

/* Notification header */
.notification-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--dark-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h5 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark-text);
}

.notification-actions {
    display: flex;
    gap: 0.75rem;
}

.mark-all-read, .view-all-btn {
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}

.mark-all-read {
    background: rgba(255, 255, 255, 0.05);
    color: var(--dark-text-secondary);
}

.mark-all-read:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--dark-text);
}

.view-all-btn {
    background: rgba(58, 134, 255, 0.2);
    color: var(--primary-color);
}

.view-all-btn:hover {
    background: rgba(58, 134, 255, 0.3);
}

/* Notification list */
.notification-list {
    max-height: 400px;
    overflow-y: auto;
    padding: 0;
    margin: 0;
}

/* Individual notification item */
.notification-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--dark-border);
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.notification-item.unread {
    background: rgba(58, 134, 255, 0.08);
    position: relative;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--primary-color);
    border-radius: 0 4px 4px 0;
}

.notification-item.clickable {
    cursor: pointer;
}

.notification-item.clickable:hover {
    background: rgba(58, 134, 255, 0.1) !important;
}

.notification-message {
    font-size: 0.9rem;
    color: var(--dark-text);
    line-height: 1.4;
}

.notification-time {
    font-size: 0.75rem;
    color: var(--dark-text-secondary);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.notification-time i {
    font-size: 0.7rem;
}

/* Empty state */
.notification-empty {
    padding: 2rem;
    text-align: center;
    color: var(--dark-text-secondary);
    font-size: 0.9rem;
}

/* Loader */
.notification-loader {
    padding: 2rem;
    text-align: center;
}

/* All notifications modal */
.all-notifications-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
}

.all-notifications-modal.active {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background: var(--dark-card);
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    overflow: hidden;
    border: 1px solid var(--dark-border);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
}

.modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--dark-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark-text);
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--dark-text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    padding: 0.25rem;
    border-radius: 4px;
}

.close-modal:hover {
    color: var(--danger-color);
    background: rgba(239, 71, 111, 0.1);
}

.modal-body {
    padding: 1rem;
    max-height: calc(80vh - 70px);
    overflow-y: auto;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .profile-search-container {
        max-width: 250px;
        margin: 0 1rem;
    }
    
    .notification-panel {
        width: 320px;
        right: -50px;
    }
    
    .notification-item {
        padding: 0.75rem 1rem;
    }
    
    .modal-content {
        width: 95%;
    }
}

@media (max-width: 480px) {
    .profile-search-container {
        display: none; /* Hide search on very small screens */
    }
    
    .notification-panel {
        width: 280px;
        right: -80px;
    }
    
    .notification-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .notification-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .mark-all-read, .view-all-btn {
        flex: 1;
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const messagesButton = document.getElementById('messagesButton');
    const messagesBadge = document.getElementById('messagesBadge');
    const notificationButton = document.getElementById('notificationButton');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationList = document.getElementById('notificationList');
    const markAllReadBtn = document.querySelector('.mark-all-read');
    const viewAllBtn = document.getElementById('viewAllNotifications');
    const allNotificationsModal = document.getElementById('allNotificationsModal');
    const allNotificationsContainer = document.getElementById('allNotificationsContainer');
    const closeModalBtn = document.getElementById('closeModal');
    const profileToggle = document.querySelector('.profile-dropdown-toggle');
    const profileMenu = document.querySelector('.profile-dropdown-menu');
    
    // Profile Search Elements
    const profileSearchInput = document.getElementById('profileSearchInput');
    const searchResults = document.getElementById('searchResults');
    const clearSearchBtn = document.getElementById('clearSearch');
    
    let searchTimeout = null;

    // Profile Search Functionality
    profileSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length === 0) {
            hideSearchResults();
            clearSearchBtn.style.display = 'none';
            return;
        }
        
        clearSearchBtn.style.display = 'block';
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Debounce search
        searchTimeout = setTimeout(() => {
            performProfileSearch(query);
        }, 300);
    });

    clearSearchBtn.addEventListener('click', function() {
        profileSearchInput.value = '';
        hideSearchResults();
        clearSearchBtn.style.display = 'none';
        profileSearchInput.focus();
    });

    async function performProfileSearch(query) {
        showSearchLoading();
        
        try {
            const response = await fetch('search_profiles.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ query: query }),
                credentials: 'include'
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Network response was not ok');
            }
            
            const data = await response.json();
            
            console.log('Search response:', data);
            
            if (data.success) {
                if (data.results && data.results.length > 0) {
                    displaySearchResults(data.results);
                } else {
                    showNoResults();
                }
            } else {
                throw new Error(data.error || 'Search failed');
            }
        } catch (error) {
            console.error('Search error:', error);
            showSearchError(error.message);
        }
    }

    function showSearchLoading() {
        searchResults.innerHTML = `
            <div class="search-loading">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Searching...</span>
                </div>
                <span class="ms-2">Searching profiles...</span>
            </div>
        `;
        searchResults.classList.add('show');
    }

   function displaySearchResults(results) {
    searchResults.innerHTML = results.map(profile => {
        const roleClass = profile.role === 'recruiter' ? 'recruiter-badge' : 'candidate-badge';
        
        return `
            <a href="${profile.profile_url}" class="search-result-item">
                <img src="${profile.photo}" alt="Profile" class="search-result-avatar"
                     onerror="this.onerror=null;this.src='/Public/Assets/default-user.png'">
                <div class="search-result-info">
                    <div class="search-result-name">${profile.name}</div>
                    <div class="search-result-details">
                        <span class="search-result-badge ${roleClass}">
                            ${profile.role_badge}
                        </span>
                        ${profile.location ? `
                        <span class="search-result-meta">
                            <i class="bi bi-geo-alt"></i> ${profile.location}
                        </span>
                        ` : ''}
                    </div>
                </div>
            </a>
        `;
    }).join('');
    
    searchResults.classList.add('show');
}
    function showNoResults() {
        searchResults.innerHTML = `
            <div class="search-no-results">
                <i class="bi bi-search mb-2"></i>
                <p>No profiles found matching your search.</p>
            </div>
        `;
        searchResults.classList.add('show');
    }

    function showSearchError(message) {
        searchResults.innerHTML = `
            <div class="search-no-results">
                <i class="bi bi-exclamation-triangle mb-2"></i>
                <p>${message || 'Error occurred while searching. Please try again.'}</p>
            </div>
        `;
        searchResults.classList.add('show');
    }

    function hideSearchResults() {
        searchResults.classList.remove('show');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Messages button click handler
    messagesButton.addEventListener('click', function(e) {
        e.stopPropagation();
        window.location.href = 'messages.php';
    });

    // Toggle notification panel
    notificationButton.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationPanel.classList.toggle('active');
        if (notificationPanel.classList.contains('active')) {
            loadNotifications();
        }
    });

    // Toggle profile dropdown
    profileToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle('show');
    });

    // View all notifications
    viewAllBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationPanel.classList.remove('active');
        showAllNotificationsModal();
    });

    // Close modal
    closeModalBtn.addEventListener('click', function() {
        allNotificationsModal.classList.remove('active');
    });

    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationPanel.contains(e.target) && !notificationButton.contains(e.target)) {
            notificationPanel.classList.remove('active');
        }
        
        if (!profileMenu.contains(e.target) && !profileToggle.contains(e.target)) {
            profileMenu.classList.remove('show');
        }
        
        if (e.target === allNotificationsModal) {
            allNotificationsModal.classList.remove('active');
        }
        
        // Hide search results when clicking outside
        if (!searchResults.contains(e.target) && !profileSearchInput.contains(e.target)) {
            hideSearchResults();
        }
    });

    // Function to get notification URL based on type and data
    function getNotificationUrl(notif) {
        if (!notif.type) return null;

        switch (notif.type) {
            case 'job_update':
            case 'system': // New job posted
                return `Candidate_Job_detail.php?id=${notif.related_id}`;

            case 'application':
            case 'status_change':
                return `Candidate_applications.php`;

            // You can add more types later
            default:
                return null;
        }
    }

    // Load unread messages count
    async function loadUnreadMessagesCount() {
        try {
            const response = await fetch('get_unread_messages_count.php', {
                credentials: 'include'
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success) {
                updateMessagesBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Error loading unread messages count:', error);
        }
    }

    // Update messages badge
    function updateMessagesBadge(count) {
        count = count || 0;
        if (count > 0) {
            messagesBadge.textContent = count;
            messagesBadge.style.display = 'flex';
        } else {
            messagesBadge.style.display = 'none';
        }
    }

    // Load notifications
    async function loadNotifications() {
        try {
            notificationList.innerHTML = `
                <div class="notification-loader">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            const response = await fetch('get_notifications.php?limit=5', {
                credentials: 'include'
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success) {
                updateNotificationBadge(data.unread_count);
                renderNotifications(data.notifications, notificationList);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="bi bi-exclamation-triangle"></i>
                    <p>Failed to load notifications</p>
                </div>
            `;
        }
    }

    // Show all notifications modal
    async function showAllNotificationsModal() {
        allNotificationsModal.classList.add('active');
        allNotificationsContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        try {
            const response = await fetch('get_notifications.php', {
                credentials: 'include'
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success) {
                renderNotifications(data.notifications, allNotificationsContainer);
                updateNotificationBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Error loading all notifications:', error);
            allNotificationsContainer.innerHTML = `
                <div class="notification-empty">
                    <i class="bi bi-exclamation-triangle"></i>
                    <p>Failed to load notifications</p>
                </div>
            `;
        }
    }

    // Render notifications
    function renderNotifications(notifications, container) {
        if (!notifications || notifications.length === 0) {
            container.innerHTML = `
                <div class="notification-empty">
                    <i class="bi bi-bell-slash"></i>
                    <p>No notifications found</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = notifications.map(notif => {
            const url = getNotificationUrl(notif);
            const clickableClass = url ? 'clickable' : '';
            
            return `
                <div class="notification-item ${notif.is_read ? '' : 'unread'} ${clickableClass}" 
                     data-id="${notif.id}" 
                     ${url ? `data-url="${url}"` : ''}>
                    <div class="notification-message">${notif.message}</div>
                    <div class="notification-time">
                        <i class="bi bi-clock"></i>
                        ${notif.formatted_date}
                    </div>
                </div>
            `;
        }).join('');
        
        // Add click handlers
        container.querySelectorAll('.notification-item.clickable').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-id');
                const url = this.getAttribute('data-url');
                
                // Mark as read
                if (this.classList.contains('unread')) {
                    markAsRead(notificationId);
                    this.classList.remove('unread');
                    
                    // Update badge count
                    const currentCount = parseInt(notificationBadge.textContent);
                    if (currentCount > 0) {
                        notificationBadge.textContent = currentCount - 1;
                    }
                }
                
                // Navigate to URL if available
                if (url) {
                    // Close notification panel and modal
                    notificationPanel.classList.remove('active');
                    allNotificationsModal.classList.remove('active');
                    
                    // Navigate to the page
                    window.location.href = url;
                }
            });
        });
    }

    // Update notification badge
    function updateNotificationBadge(count) {
        count = count || 0;
        notificationBadge.textContent = count;
        notificationBadge.style.display = count > 0 ? 'flex' : 'none';
    }

    // Mark as read
    async function markAsRead(notificationId) {
        try {
            await fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: notificationId }),
                credentials: 'include'
            });
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    // Mark all as read
    markAllReadBtn.addEventListener('click', async function(e) {
        e.stopPropagation();
        try {
            const response = await fetch('mark_all_read.php', {
                method: 'POST',
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                notificationBadge.textContent = '0';
                notificationBadge.style.display = 'none';
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    });

    // Poll for new notifications and messages every 30 seconds
    setInterval(() => {
        if (!notificationPanel.classList.contains('active')) {
            // Check notifications
            fetch('get_notifications.php?limit=1', {
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationBadge(data.unread_count);
                }
            })
            .catch(error => console.error('Polling error:', error));
            
            // Check messages
            loadUnreadMessagesCount();
        }
    }, 30000);

    // Initial load
    loadNotifications();
    loadUnreadMessagesCount();
});
</script>