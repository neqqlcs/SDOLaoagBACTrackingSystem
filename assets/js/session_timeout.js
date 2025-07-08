// session_timeout.js - Client-side session timeout management

class SessionTimeoutManager {
    constructor() {
        this.warningShown = false;
        this.checkInterval = null;
        this.activityTimer = null;
        this.modalShown = false;
        
        // Check session every 30 seconds
        this.checkFrequency = 30000; 
        
        // Activity tracking delay (refresh session after 5 seconds of activity)
        this.activityDelay = 5000;
        
        // Initialize
        this.init();
    }
    
    init() {
        // Start periodic session checks
        this.startSessionChecks();
        
        // Track user activity
        this.trackUserActivity();
        
        // Create timeout warning modal
        this.createTimeoutModal();
    }
    
    startSessionChecks() {
        this.checkInterval = setInterval(() => {
            this.checkSessionStatus();
        }, this.checkFrequency);
        
        // Initial check
        this.checkSessionStatus();
    }
    
    async checkSessionStatus() {
        try {
            const response = await fetch('session_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=check'
            });
            
            const data = await response.json();
            
            if (data.status === 'expired') {
                this.handleSessionExpired();
            } else if (data.status === 'active') {
                if (data.show_warning && !this.modalShown) {
                    this.showTimeoutWarning(data.remaining_time);
                } else if (!data.show_warning && this.modalShown) {
                    this.hideTimeoutWarning();
                }
            }
        } catch (error) {
            console.error('Session check failed:', error);
        }
    }
    
    trackUserActivity() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        const activityHandler = () => {
            // Clear existing timer
            if (this.activityTimer) {
                clearTimeout(this.activityTimer);
            }
            
            // Set new timer to refresh session after activity delay
            this.activityTimer = setTimeout(() => {
                this.refreshSession();
            }, this.activityDelay);
        };
        
        // Add event listeners for user activity
        events.forEach(event => {
            document.addEventListener(event, activityHandler, true);
        });
    }
    
    async refreshSession() {
        try {
            const response = await fetch('session_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=refresh'
            });
            
            const data = await response.json();
            
            if (data.status === 'expired') {
                this.handleSessionExpired();
            }
        } catch (error) {
            console.error('Session refresh failed:', error);
        }
    }
    
    createTimeoutModal() {
        // Create modal HTML
        const modalHTML = `
            <div id="sessionTimeoutModal" class="session-modal" style="display: none;">
                <div class="session-modal-content">
                    <div class="session-modal-header">
                        <h3><i class="fas fa-clock"></i> Session Timeout Warning</h3>
                    </div>
                    <div class="session-modal-body">
                        <p>Your session will expire in <span id="timeoutCountdown">5:00</span> minutes due to inactivity.</p>
                        <p>Would you like to stay logged in?</p>
                    </div>
                    <div class="session-modal-footer">
                        <button id="stayLoggedInBtn" class="btn btn-primary">Stay Logged In</button>
                        <button id="logoutNowBtn" class="btn btn-secondary">Logout Now</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add event listeners
        document.getElementById('stayLoggedInBtn').addEventListener('click', () => {
            this.extendSession();
        });
        
        document.getElementById('logoutNowBtn').addEventListener('click', () => {
            this.logout();
        });
        
        // Add CSS styles
        this.addModalStyles();
    }
    
    addModalStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .session-modal {
                display: none;
                position: fixed;
                z-index: 10000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0, 0, 0, 0.6);
                animation: fadeIn 0.3s ease-out;
            }
            
            .session-modal-content {
                background-color: #fff;
                margin: 10% auto;
                padding: 0;
                border: none;
                border-radius: 12px;
                width: 90%;
                max-width: 450px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                animation: slideIn 0.3s ease-out;
            }
            
            .session-modal-header {
                background-color: #c62828;
                color: white;
                padding: 20px 25px;
                border-radius: 12px 12px 0 0;
                text-align: center;
            }
            
            .session-modal-header h3 {
                margin: 0;
                font-size: 1.2em;
                font-weight: 600;
            }
            
            .session-modal-header i {
                margin-right: 8px;
            }
            
            .session-modal-body {
                padding: 25px;
                text-align: center;
                line-height: 1.6;
            }
            
            .session-modal-body p {
                margin: 0 0 15px 0;
                color: #333;
            }
            
            #timeoutCountdown {
                font-weight: bold;
                color: #c62828;
                font-size: 1.1em;
            }
            
            .session-modal-footer {
                padding: 20px 25px;
                text-align: center;
                border-top: 1px solid #eee;
                border-radius: 0 0 12px 12px;
            }
            
            .session-modal-footer .btn {
                margin: 0 5px;
                padding: 10px 20px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            
            .session-modal-footer .btn-primary {
                background-color: #c62828;
                color: white;
            }
            
            .session-modal-footer .btn-primary:hover {
                background-color: #a91e1e;
                transform: translateY(-1px);
            }
            
            .session-modal-footer .btn-secondary {
                background-color: #6c757d;
                color: white;
            }
            
            .session-modal-footer .btn-secondary:hover {
                background-color: #545b62;
                transform: translateY(-1px);
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes slideIn {
                from { transform: translateY(-30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            
            @media (max-width: 480px) {
                .session-modal-content {
                    margin: 20% auto;
                    width: 95%;
                }
                
                .session-modal-footer .btn {
                    display: block;
                    width: 100%;
                    margin: 5px 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    showTimeoutWarning(remainingTime) {
        this.modalShown = true;
        const modal = document.getElementById('sessionTimeoutModal');
        modal.style.display = 'block';
        
        // Start countdown
        this.startCountdown(remainingTime);
    }
    
    hideTimeoutWarning() {
        this.modalShown = false;
        const modal = document.getElementById('sessionTimeoutModal');
        modal.style.display = 'none';
        
        // Clear countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
    }
    
    startCountdown(seconds) {
        const countdownElement = document.getElementById('timeoutCountdown');
        
        const updateCountdown = () => {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            const display = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
            countdownElement.textContent = display;
            
            if (seconds <= 0) {
                this.handleSessionExpired();
                return;
            }
            
            seconds--;
        };
        
        // Update immediately
        updateCountdown();
        
        // Update every second
        this.countdownInterval = setInterval(updateCountdown, 1000);
    }
    
    async extendSession() {
        try {
            const response = await fetch('session_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=extend'
            });
            
            const data = await response.json();
            
            if (data.status === 'extended') {
                this.hideTimeoutWarning();
            } else {
                this.handleSessionExpired();
            }
        } catch (error) {
            console.error('Session extension failed:', error);
            this.handleSessionExpired();
        }
    }
    
    logout() {
        window.location.href = window.logoutUrl || 'logout.php';
    }
    
    handleSessionExpired() {
        // Clear intervals
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        // Show expired message and redirect
        alert('Your session has expired. You will be redirected to the login page.');
        this.logout();
    }
    
    destroy() {
        // Clean up intervals and event listeners
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        if (this.activityTimer) {
            clearTimeout(this.activityTimer);
        }
    }
}

// Initialize session timeout manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize on pages where user is logged in (not login page)
    if (!document.body.classList.contains('login-page')) {
        window.sessionManager = new SessionTimeoutManager();
    }
});

// Clean up when page unloads
window.addEventListener('beforeunload', function() {
    if (window.sessionManager) {
        window.sessionManager.destroy();
    }
});
