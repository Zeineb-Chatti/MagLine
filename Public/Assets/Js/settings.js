document.addEventListener('DOMContentLoaded', function() {

    const logoUpload = document.getElementById('logoUpload');
    const logoPreview = document.getElementById('logoPreview');
    const cropperContainer = document.getElementById('cropperContainer');
    const chooseLogoBtn = document.getElementById('chooseLogoBtn');
    const saveCropBtn = document.getElementById('saveCropBtn');
    const cancelCropBtn = document.getElementById('cancelCropBtn');
    const croppedImage = document.getElementById('croppedImage');
    const profileForm = document.getElementById('profileForm');
    const fileError = document.getElementById('fileError');
    
    let cropper;

    function showError(message) {
        fileError.textContent = message;
        fileError.classList.remove('d-none');
        // Hide after 5 seconds
        setTimeout(() => fileError.classList.add('d-none'), 5000); 
    }

    chooseLogoBtn.addEventListener('click', function() {
        logoUpload.value = ''; 
        logoUpload.click();
    });

    logoUpload.addEventListener('change', function(e) {
        const file = e.target.files[0];
        fileError.classList.add('d-none');
        
        if (!file) return;

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
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }

            logoPreview.classList.add('d-none');
            cropperContainer.classList.remove('d-none');
      
            cropperContainer.innerHTML = `
                <div class="cropper-wrapper">
                    <img id="imageToCrop" src="${event.target.result}" alt="Cropping preview">
                </div>
            `;

            const imageToCropElement = document.getElementById('imageToCrop');
            cropper = new Cropper(imageToCropElement, {
                aspectRatio: 1,
                viewMode: 1,  
                autoCropArea: 0.8, 
                responsive: true,
                background: false, 
                zoomable: true,
                zoomOnTouch: true,
                zoomOnWheel: true,
                
            });
            
  
            chooseLogoBtn.classList.add('d-none'); 
            saveCropBtn.classList.remove('d-none');
            cancelCropBtn.classList.remove('d-none');
        };
        
        reader.onerror = function() {
            showError('Error reading image file.');
        };
        
        reader.readAsDataURL(file);
    });

    saveCropBtn.addEventListener('click', function() {
        if (!cropper) {
            showError('Cropper not initialized.');
            return;
        }
        
        const canvas = cropper.getCroppedCanvas({
            width: 300, 
            height: 300, 
            fillColor: '#fff', 
            imageSmoothingQuality: 'high'
        });
        
        if (!canvas) {
            showError('Error cropping image. Canvas not created.');
            return;
        }

        canvas.toBlob((blob) => {
            if (!blob) {
                showError('Error processing image. Blob not created.');
                return;
            }

            const reader = new FileReader();
            reader.readAsDataURL(blob);
            
            reader.onloadend = function() {
                croppedImage.value = reader.result;
                

                logoPreview.src = reader.result;
                logoPreview.classList.remove('d-none');
*
                cropperContainer.classList.add('d-none');
                saveCropBtn.classList.add('d-none');
                cancelCropBtn.classList.add('d-none');

                chooseLogoBtn.classList.remove('d-none');

                cropper.destroy();
                cropper = null;
 
            };
            
            reader.onerror = function() {
                showError('Error converting cropped image to Data URL.');
            };
        }, 'image/png', 1); // Use 'image/png' with quality 1 for lossless, or 'image/jpeg' with e.g. 0.9 for compression
    });


    cancelCropBtn.addEventListener('click', function() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        
        logoPreview.classList.remove('d-none');
        cropperContainer.classList.add('d-none');
        saveCropBtn.classList.add('d-none');
        cancelCropBtn.classList.add('d-none');
        
        chooseLogoBtn.classList.remove('d-none');

        logoUpload.value = '';
        croppedImage.value = ''; // Also clear the hidden input
    });

    profileForm.addEventListener('submit', function(event) {
        if (event.submitter && event.submitter.name === 'update_profile') {
            const submitBtn = event.submitter;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        }
    });
});
