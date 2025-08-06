document.addEventListener('DOMContentLoaded', function() {
    // Logo preview functionality
    const logoUpload = document.querySelector('#logoModal input[type="file"]');
    const logoPreview = document.querySelector('#logoModal .logo-preview img');
    
    if (logoUpload && logoPreview) {
        logoUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    logoPreview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Logo hover animation
    const logo = document.querySelector('.company-logo');
    if (logo) {
        logo.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        logo.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    }
});

document.querySelector('#logoModal input[type="file"]').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const maxSize = 2 * 1024 * 1024; // 2MB
    
    if (file && file.size > maxSize) {
        alert(`File is too large (${(file.size/1024/1024).toFixed(2)}MB). Maximum size is 2MB.`);
        e.target.value = ''; // Clear the file input
    }
});