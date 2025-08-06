document.addEventListener('DOMContentLoaded', function() {
    // ===== Initialize Bootstrap Tooltips =====
    const initializeTooltips = () => {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover focus',
                animation: true,
                delay: { show: 100, hide: 50 }
            });
        });
    };

    // ===== Mobile Sidebar Toggle =====
    const setupSidebar = () => {
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.recruiter-sidebar');
        let sidebarOverlay = document.querySelector('.sidebar-overlay');

        if (!sidebarOverlay) {
            sidebarOverlay = document.createElement('div');
            sidebarOverlay.classList.add('sidebar-overlay');
            document.body.appendChild(sidebarOverlay);
        }

        if (menuToggle) {
            menuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.classList.toggle('sidebar-open');
            });
        }

        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        });
    };

    // ===== Profile Dropdown =====
    const setupProfileDropdown = () => {
        const profileDropdown = document.querySelector('.profile-dropdown');
        if (profileDropdown) {
            profileDropdown.addEventListener('click', function(event) {
                event.stopPropagation();
                const dropdownMenu = this.querySelector('.profile-dropdown-menu');
                if (dropdownMenu) {
                    dropdownMenu.classList.toggle('show');
                }
            });

            document.addEventListener('click', function() {
                const dropdownMenus = document.querySelectorAll('.profile-dropdown-menu.show');
                dropdownMenus.forEach(menu => menu.classList.remove('show'));
            });
        }
    };

    // ===== Table Enhancements =====
    const enhanceTables = () => {
        const tableRows = document.querySelectorAll('.custom-table-dark tbody tr');
        tableRows.forEach((row, index) => {
            row.style.setProperty('--row-index', index);
            
            row.addEventListener('mouseenter', () => {
                row.style.boxShadow = 'inset 5px 0 0 0 var(--primary-color)';
                row.querySelectorAll('.table-actions .btn').forEach(btn => {
                    btn.style.transform = 'translateY(-2px)';
                });
            });
            
            row.addEventListener('mouseleave', () => {
                row.style.boxShadow = 'none';
                row.querySelectorAll('.table-actions .btn').forEach(btn => {
                    btn.style.transform = '';
                });
            });
        });
    };

    // ===== Delete Confirmation =====
    const setupDeleteButtons = () => {
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this job offer? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    };

    // ===== Floating Action Button (Mobile) =====
    const createFAB = () => {
        const fab = document.createElement('button');
        fab.className = 'fab d-lg-none';
        fab.innerHTML = '<i class="bi bi-plus-lg"></i>';
        fab.setAttribute('aria-label', 'Create new job offer');
        fab.addEventListener('click', () => {
            window.location.href = 'Post_offer.php';
        });
        document.body.appendChild(fab);
    };

    // ===== Responsive Table Actions =====
    const handleTableActions = () => {
        const tables = document.querySelectorAll('.custom-table-dark');
        tables.forEach(table => {
            const actions = table.querySelectorAll('.table-actions');
            actions.forEach(actionGroup => {
                if (window.innerWidth < 768) {
                    actionGroup.classList.add('flex-column');
                    actionGroup.querySelectorAll('.btn').forEach(btn => {
                        btn.classList.add('w-100', 'mb-1');
                    });
                } else {
                    actionGroup.classList.remove('flex-column');
                    actionGroup.querySelectorAll('.btn').forEach(btn => {
                        btn.classList.remove('w-100', 'mb-1');
                    });
                }
            });
        });
    };

    // ===== Smooth Scrolling for Anchor Links =====
    const setupSmoothScrolling = () => {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    };

    // ===== Flash Message Auto-dismiss =====
    const autoDismissFlashMessages = () => {
        document.querySelectorAll('.alert').forEach(message => {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            }, 5000);
        });
    };

    // ===== Scroll Animations =====
    const setupScrollAnimations = () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.candidate-card, .custom-table-dark tbody tr').forEach(element => {
            observer.observe(element);
        });
    };

    // ===== Initialize All Components =====
    initializeTooltips();
    setupSidebar();
    setupProfileDropdown();
    enhanceTables();
    setupDeleteButtons();
    createFAB();
    setupSmoothScrolling();
    autoDismissFlashMessages();
    setupScrollAnimations();

    // Run responsive table handler on load and resize
    handleTableActions();
    window.addEventListener('resize', handleTableActions);
});

// ===== Add CSS for Pulse Animation =====
document.head.insertAdjacentHTML('beforeend', `
<style>
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(13, 110, 253, 0); }
        100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
    }
    
    /* Enhanced table actions for mobile */
    @media (max-width: 767.98px) {
        .table-actions.flex-column {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.5rem !important;
        }
        
        .table-actions.flex-column .btn {
            width: 100% !important;
            margin-bottom: 0.5rem !important;
        }
    }
    
    /* Body class when sidebar is open */
    body.sidebar-open {
        overflow: hidden;
    }
</style>
`);



document.addEventListener('DOMContentLoaded', function() {
    // Theme toggle functionality
    const themeToggle = document.createElement('button');
    themeToggle.className = 'btn btn-sm btn-outline-secondary theme-toggle';
    themeToggle.innerHTML = '<i class="mdi mdi-theme-light-dark"></i>';
    
    // Find a suitable place to add the theme toggle (e.g., in the header)
    const headerActions = document.querySelector('.page-actions');
    if (headerActions) {
        headerActions.prepend(themeToggle);
    }
    
    themeToggle.addEventListener('click', function() {
        document.body.classList.toggle('dark-theme');
        document.body.classList.toggle('light-theme');
        // You might want to save the preference to localStorage here
    });
    
    // Initialize Charts
    const applicationsCtx = document.getElementById('applicationsChart').getContext('2d');
    const applicationsChart = new Chart(applicationsCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Applications',
                data: [12, 19, 15, 27, 34, 42],
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.7)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.7)'
                    }
                }
            }
        }
    });
    
    const statusCtx = document.getElementById('offerStatusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Closed', 'Draft', 'Pending'],
            datasets: [{
                data: [12, 5, 3, 2],
                backgroundColor: [
                    '#4361ee',
                    '#4cc9f0',
                    '#f8961e',
                    '#6c757d'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: 'rgba(255, 255, 255, 0.7)'
                    }
                }
            }
        }
    });
    
    // Tooltip initialization
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

