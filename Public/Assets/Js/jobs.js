class JobApplication {
    constructor() {
        this.pendingRequests = new Set();
        this.retryAttempts = new Map(); // Track retry attempts per job
        this.MAX_RETRIES = 2;
        this.init();
    }

    init() {
        this.createToastContainer();
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.addEventListener('click', (e) => {
            const applyBtn = e.target.closest('[data-apply-job]');
            if (applyBtn && !this.pendingRequests.has(applyBtn.dataset.jobId)) {
                this.handleApplication(e, applyBtn);
            }
        });
    }

    async handleApplication(event, button) {
        event.preventDefault();
        const jobId = button.dataset.jobId;

        // Prevent duplicate requests
        if (this.pendingRequests.has(jobId)) return;
        this.pendingRequests.add(jobId);

        // Set loading state
        this.setButtonState(button, 'loading');

        try {
            const response = await this.makeApplicationRequest(jobId);
            const data = await response.json();

            if (!data || typeof data !== 'object') {
                throw new Error('Invalid server response format');
            }

            if (data.success) {
                this.handleSuccess(button, data.message);
            } else {
                throw new Error(data.message || 'Application failed');
            }
        } catch (error) {
            console.error('Application error:', error);
            this.handleError(button, jobId, error);
        } finally {
            this.pendingRequests.delete(jobId);
        }
    }

    async makeApplicationRequest(jobId) {
        try {
            const response = await fetch('apply_job.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ job_id: jobId })
            });

            if (!response.ok) {
                throw new Error(`Server responded with status ${response.status}`);
            }

            return response;
        } catch (error) {
            // Distinguish between network errors and other errors
            if (error.message.includes('Failed to fetch')) {
                error.isNetworkError = true;
            }
            throw error;
        }
    }

    handleSuccess(button, message) {
        this.setButtonState(button, 'success');
        this.showToast(message || 'Application submitted!', 'success');
        
        // Disable button after successful application
        button.disabled = true;
        button.removeAttribute('data-apply-job');
        this.retryAttempts.delete(button.dataset.jobId);
    }

    handleError(button, jobId, error) {
        const attempts = (this.retryAttempts.get(jobId) || 0) + 1;
        this.retryAttempts.set(jobId, attempts);

        let errorMessage = error.message;
        let canRetry = false;

        if (error.isNetworkError) {
            errorMessage = 'Network error. Please check your connection.';
            canRetry = attempts <= this.MAX_RETRIES;
        } else if (error.message.includes('Server responded')) {
            errorMessage = 'Server error. Please try again later.';
        }

        this.setButtonState(button, canRetry ? 'error' : 'error-final');
        this.showToast(errorMessage, 'danger');

        if (canRetry) {
            button.onclick = (e) => {
                this.handleApplication(e, button);
            };
        } else {
            this.retryAttempts.delete(jobId);
        }
    }

    setButtonState(button, state) {
        const states = {
            loading: {
                html: '<span class="spinner-border spinner-border-sm me-2"></span>Applying...',
                class: 'btn-warning',
                disabled: true
            },
            success: {
                html: '<i class="bi bi-check-circle me-2"></i>Applied',
                class: 'btn-success',
                disabled: true
            },
            error: {
                html: '<i class="bi bi-exclamation-triangle me-2"></i>Try Again',
                class: 'btn-outline-danger',
                disabled: false
            },
            'error-final': {
                html: '<i class="bi bi-x-circle me-2"></i>Failed',
                class: 'btn-outline-secondary',
                disabled: true
            }
        };

        const config = states[state] || {
            html: '<i class="bi bi-send me-2"></i>Apply Now',
            class: 'btn-outline-primary',
            disabled: false
        };

        button.innerHTML = config.html;
        button.className = `btn ${config.class} rounded-pill px-4`;
        button.disabled = config.disabled;
    }

    createToastContainer() {
        if (!document.querySelector('.toast-container')) {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '1100';
            document.body.appendChild(container);
        }
    }

    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast show align-items-center text-white bg-${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${this.getToastIcon(type)} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        const container = document.querySelector('.toast-container');
        container.appendChild(toast);

        // Auto-dismiss after 5 seconds
        const autoDismiss = setTimeout(() => {
            this.removeToast(toast);
        }, 5000);

        // Manual dismiss
        toast.querySelector('[data-bs-dismiss="toast"]').addEventListener('click', () => {
            clearTimeout(autoDismiss);
            this.removeToast(toast);
        });
    }

    getToastIcon(type) {
        const icons = {
            success: 'bi-check-circle',
            danger: 'bi-exclamation-circle',
            warning: 'bi-exclamation-triangle'
        };
        return icons[type] || 'bi-info-circle';
    }

    removeToast(toast) {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new JobApplication();
});