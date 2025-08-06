document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const logoUpload = document.getElementById('logoUpload');
    const logoPreview = document.getElementById('logoPreview');
    const cropperContainer = document.getElementById('cropperContainer');
    const chooseLogoBtn = document.getElementById('chooseLogoBtn');
    const saveCropBtn = document.getElementById('saveCropBtn');
    const cancelCropBtn = document.getElementById('cancelCropBtn');
    const croppedImage = document.getElementById('croppedImage');
    const profileForm = document.getElementById('profileForm');
    const fileError = document.getElementById('fileError');
    
    let cropper; // Variable to hold the Cropper instance

    // Show error message
    function showError(message) {
        fileError.textContent = message;
        fileError.classList.remove('d-none');
        // Hide after 5 seconds
        setTimeout(() => fileError.classList.add('d-none'), 5000); 
    }

    // Trigger file input when button clicked
    chooseLogoBtn.addEventListener('click', function() {
        logoUpload.value = ''; // Reset to allow re-selecting the same file
        logoUpload.click();
    });

    // Handle file selection
    logoUpload.addEventListener('change', function(e) {
        const file = e.target.files[0];
        fileError.classList.add('d-none'); // Hide previous errors
        
        if (!file) return;

        // Validate file type and size
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!validTypes.includes(file.type)) {
            showError('Please select a JPG, PNG, or WEBP image (max 5MB).');
            return;
        }
        
        if (file.size > maxSize) {
            showError('Image must be smaller than 5MB.');
            return;
        }

        const reader = new FileReader();
        
        reader.onload = function(event) {
            // Destroy any existing cropper instance before creating a new one
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }

            // Hide logo preview and show cropper container
            logoPreview.classList.add('d-none');
            cropperContainer.classList.remove('d-none');
            
            // Clear previous content in cropperContainer and add the image for cropping
            cropperContainer.innerHTML = `
                <div class="cropper-wrapper">
                    <img id="imageToCrop" src="${event.target.result}" alt="Cropping preview">
                </div>
            `;
            
            // Initialize cropper
            const imageToCropElement = document.getElementById('imageToCrop');
            cropper = new Cropper(imageToCropElement, {
                aspectRatio: 1, // Forces a square crop box
                viewMode: 1,    // Restricts the crop box to not exceed the canvas
                autoCropArea: 0.8, // 80% of the image will be cropped by default
                responsive: true,
                background: false, // Hide the grid background
                zoomable: true,
                zoomOnTouch: true,
                zoomOnWheel: true,
                // minContainerWidth: 150, // Should be handled by CSS
                // minContainerHeight: 150 // Should be handled by CSS
            });
            
            // Show crop control buttons
            chooseLogoBtn.classList.add('d-none'); // Hide "Change Logo" button
            saveCropBtn.classList.remove('d-none');
            cancelCropBtn.classList.remove('d-none');
        };
        
        reader.onerror = function() {
            showError('Error reading image file.');
        };
        
        reader.readAsDataURL(file);
    });

    // Handle crop save
    saveCropBtn.addEventListener('click', function() {
        if (!cropper) {
            showError('Cropper not initialized.');
            return;
        }
        
        // Get cropped canvas
        const canvas = cropper.getCroppedCanvas({
            width: 300, // Desired width for the output image
            height: 300, // Desired height for the output image
            fillColor: '#fff', // Fill transparent areas with white
            imageSmoothingQuality: 'high'
        });
        
        if (!canvas) {
            showError('Error cropping image. Canvas not created.');
            return;
        }

        // Convert canvas to blob (PNG format for transparency, or JPEG for smaller size)
        canvas.toBlob((blob) => {
            if (!blob) {
                showError('Error processing image. Blob not created.');
                return;
            }

            // Convert blob to base64 for form submission
            const reader = new FileReader();
            reader.readAsDataURL(blob);
            
            reader.onloadend = function() {
                croppedImage.value = reader.result; // Set hidden input value
                
                // Update visible preview
                logoPreview.src = reader.result;
                logoPreview.classList.remove('d-none');
                
                // Hide cropper and crop control buttons
                cropperContainer.classList.add('d-none');
                saveCropBtn.classList.add('d-none');
                cancelCropBtn.classList.add('d-none');
                
                // Show "Change Logo" button again
                chooseLogoBtn.classList.remove('d-none');

                // Destroy cropper instance
                cropper.destroy();
                cropper = null;

                // Optionally, submit the form here if you want immediate upload
                // profileForm.submit(); 
            };
            
            reader.onerror = function() {
                showError('Error converting cropped image to Data URL.');
            };
        }, 'image/png', 1); // Use 'image/png' with quality 1 for lossless, or 'image/jpeg' with e.g. 0.9 for compression
    });

    // Handle crop cancellation
    cancelCropBtn.addEventListener('click', function() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        
        // Restore logo preview and hide cropper elements
        logoPreview.classList.remove('d-none');
        cropperContainer.classList.add('d-none');
        saveCropBtn.classList.add('d-none');
        cancelCropBtn.classList.add('d-none');
        
        // Show "Change Logo" button again
        chooseLogoBtn.classList.remove('d-none');

        // Clear the file input value to allow re-selecting the same file
        logoUpload.value = '';
        croppedImage.value = ''; // Also clear the hidden input
    });

    // Form submission handler to prevent double submits
    // This will only apply if the form is submitted for profile updates, not logo
    profileForm.addEventListener('submit', function(event) {
        // Only disable if it's the profile update form, not a hidden logo upload
        if (event.submitter && event.submitter.name === 'update_profile') {
            const submitBtn = event.submitter;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        }
    });
});