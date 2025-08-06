// Auto-dismiss flash message after 5 seconds
setTimeout(() => {
    const alert = document.querySelector('.alert');
    if (alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }
}, 5000);

// Image error handling
document.querySelectorAll('img').forEach(img => {
    img.addEventListener('error', function() {
        if (!this.classList.contains('img-fallback')) {
            this.classList.add('img-fallback');
            if (this.classList.contains('header-logo') || this.classList.contains('sidebar-logo')) {
                this.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23e9ecef"/><text x="50" y="55" font-family="Arial" font-size="10" text-anchor="middle" fill="%236c757d">MagLine Logo</text></svg>';
            } else if (this.classList.contains('company-logo')) {
                this.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="45" fill="%23e9ecef"/><text x="50" y="55" font-family="Arial" font-size="8" text-anchor="middle" fill="%236c757d">Company Logo</text></svg>';
            }
        }
    });
});

// Smooth animations for cards
document.querySelectorAll('.welcome-section, .stat-card, .card').forEach((element, index) => {
    element.style.opacity = '0';
    element.style.transform = 'translateY(20px)';
    element.style.transition = `all 0.5s ease ${index * 0.08}s`;
    
    setTimeout(() => {
        element.style.opacity = '1';
        element.style.transform = 'translateY(0)';
    }, 100);
});