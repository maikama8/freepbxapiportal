/**
 * Auto-logout functionality for session timeout
 */
class AutoLogout {
    constructor(timeoutMinutes = 5) {
        this.timeoutMinutes = timeoutMinutes;
        this.timeoutMs = timeoutMinutes * 60 * 1000; // Convert to milliseconds
        this.warningMs = 60 * 1000; // Show warning 1 minute before timeout
        this.timer = null;
        this.warningTimer = null;
        this.warningShown = false;
        
        this.init();
    }
    
    init() {
        // Only initialize if user is authenticated
        if (!this.isAuthenticated()) {
            return;
        }
        
        this.resetTimer();
        this.bindEvents();
    }
    
    isAuthenticated() {
        // Check if we're on a page that requires authentication
        // This is a simple check - you might want to make it more robust
        return document.querySelector('meta[name="csrf-token"]') !== null &&
               !window.location.pathname.includes('/login');
    }
    
    resetTimer() {
        this.clearTimers();
        this.warningShown = false;
        
        // Set warning timer (1 minute before logout)
        this.warningTimer = setTimeout(() => {
            this.showWarning();
        }, this.timeoutMs - this.warningMs);
        
        // Set logout timer
        this.timer = setTimeout(() => {
            this.logout();
        }, this.timeoutMs);
    }
    
    clearTimers() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        if (this.warningTimer) {
            clearTimeout(this.warningTimer);
            this.warningTimer = null;
        }
    }
    
    bindEvents() {
        // Reset timer on user activity
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                if (!this.warningShown) {
                    this.resetTimer();
                }
            }, { passive: true });
        });
    }
    
    showWarning() {
        this.warningShown = true;
        
        // Create warning modal
        const modal = this.createWarningModal();
        document.body.appendChild(modal);
        
        // Show the modal
        if (typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        } else {
            modal.style.display = 'block';
            modal.classList.add('show');
        }
    }
    
    createWarningModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'sessionWarningModal';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-labelledby', 'sessionWarningModalLabel');
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('data-bs-backdrop', 'static');
        modal.setAttribute('data-bs-keyboard', 'false');
        
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="sessionWarningModalLabel">
                            <i class="bx bx-time me-2"></i>Session Timeout Warning
                        </h5>
                    </div>
                    <div class="modal-body">
                        <div class="text-center">
                            <i class="bx bx-time-five text-warning" style="font-size: 3rem;"></i>
                            <h6 class="mt-3">Your session will expire in <span id="countdown">60</span> seconds</h6>
                            <p class="text-muted">Click "Stay Logged In" to continue your session, or you will be automatically logged out.</p>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-primary" onclick="autoLogout.stayLoggedIn()">
                            <i class="bx bx-check me-1"></i>Stay Logged In
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="autoLogout.logout()">
                            <i class="bx bx-log-out me-1"></i>Logout Now
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Start countdown
        this.startCountdown();
        
        return modal;
    }
    
    startCountdown() {
        let seconds = 60;
        const countdownElement = document.getElementById('countdown');
        
        const countdownInterval = setInterval(() => {
            seconds--;
            if (countdownElement) {
                countdownElement.textContent = seconds;
            }
            
            if (seconds <= 0) {
                clearInterval(countdownInterval);
                this.logout();
            }
        }, 1000);
        
        // Store interval so we can clear it if user stays logged in
        this.countdownInterval = countdownInterval;
    }
    
    stayLoggedIn() {
        // Clear countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        // Close modal
        const modal = document.getElementById('sessionWarningModal');
        if (modal) {
            if (typeof bootstrap !== 'undefined') {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.hide();
                }
            }
            modal.remove();
        }
        
        // Make a request to refresh the session
        fetch('/api/refresh-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        }).then(() => {
            // Reset the timer
            this.resetTimer();
        }).catch(() => {
            // If refresh fails, logout
            this.logout();
        });
    }
    
    logout() {
        // Clear all timers
        this.clearTimers();
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        // Show logout message
        this.showLogoutMessage();
        
        // Redirect to logout
        setTimeout(() => {
            window.location.href = '/logout';
        }, 2000);
    }
    
    showLogoutMessage() {
        // Remove warning modal if it exists
        const warningModal = document.getElementById('sessionWarningModal');
        if (warningModal) {
            warningModal.remove();
        }
        
        // Create logout message
        const message = document.createElement('div');
        message.className = 'position-fixed top-50 start-50 translate-middle';
        message.style.zIndex = '9999';
        message.innerHTML = `
            <div class="card shadow-lg">
                <div class="card-body text-center p-4">
                    <i class="bx bx-log-out text-danger" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Session Expired</h5>
                    <p class="text-muted">You have been logged out due to inactivity.</p>
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Redirecting...</span>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(message);
    }
}

// Initialize auto-logout when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize on authenticated pages
    if (document.querySelector('meta[name="csrf-token"]') && 
        !window.location.pathname.includes('/login')) {
        window.autoLogout = new AutoLogout(5); // 5 minutes timeout
    }
});