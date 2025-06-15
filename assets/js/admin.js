/**
 * PHP Git App Manager - Admin Panel JavaScript
 * Handles all interactive functionality for the admin interface
 */

// Global configuration
window.AdminConfig = window.AdminConfig || {};

// Initialize admin panel
function initializeAdminPanel() {
    // Set up CSRF token for all AJAX requests
    if (window.AdminConfig.csrfToken) {
        setupCSRFToken();
    }
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize auto-refresh for deployment status
    initializeAutoRefresh();
    
    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
    
    // Initialize form validation
    initializeFormValidation();
    
    console.log('Admin panel initialized');
}

// CSRF Token Setup
function setupCSRFToken() {
    // Add CSRF token to all AJAX requests
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        if (options.method && options.method.toUpperCase() !== 'GET') {
            if (options.body instanceof FormData) {
                options.body.append('csrf_token', window.AdminConfig.csrfToken);
            } else if (options.headers && options.headers['Content-Type'] === 'application/json') {
                try {
                    const body = JSON.parse(options.body || '{}');
                    body.csrf_token = window.AdminConfig.csrfToken;
                    options.body = JSON.stringify(body);
                } catch (e) {
                    console.warn('Failed to add CSRF token to JSON body');
                }
            }
        }
        
        return originalFetch(url, options);
    };
}

// Tooltip System
function initializeTooltips() {
    // Create tooltip element
    const tooltip = document.createElement('div');
    tooltip.id = 'admin-tooltip';
    tooltip.style.cssText = `
        position: absolute;
        background: #1e293b;
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        z-index: 9999;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
        max-width: 250px;
        word-wrap: break-word;
    `;
    document.body.appendChild(tooltip);
    
    // Handle tooltip triggers
    document.addEventListener('mouseenter', function(e) {
        const target = e.target.closest('[data-tooltip]');
        if (target) {
            showTooltip(target, target.getAttribute('data-tooltip'));
        }
    }, true);
    
    document.addEventListener('mouseleave', function(e) {
        const target = e.target.closest('[data-tooltip]');
        if (target) {
            hideTooltip();
        }
    }, true);
}

function showTooltip(element, text) {
    const tooltip = document.getElementById('admin-tooltip');
    if (!tooltip) return;
    
    tooltip.textContent = text;
    tooltip.style.opacity = '1';
    
    // Position tooltip
    const rect = element.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
    let top = rect.top - tooltipRect.height - 8;
    
    // Adjust if tooltip goes off screen
    if (left < 8) left = 8;
    if (left + tooltipRect.width > window.innerWidth - 8) {
        left = window.innerWidth - tooltipRect.width - 8;
    }
    if (top < 8) {
        top = rect.bottom + 8;
    }
    
    tooltip.style.left = left + 'px';
    tooltip.style.top = top + 'px';
}

function hideTooltip() {
    const tooltip = document.getElementById('admin-tooltip');
    if (tooltip) {
        tooltip.style.opacity = '0';
    }
}

// Auto-refresh System
function initializeAutoRefresh() {
    // Check for deployments in progress
    const deploymentStatusElements = document.querySelectorAll('[data-deployment-id]');
    if (deploymentStatusElements.length > 0) {
        deploymentStatusElements.forEach(element => {
            const deploymentId = element.getAttribute('data-deployment-id');
            if (deploymentId) {
                checkDeploymentStatus(deploymentId);
            }
        });
    }
    
    // Auto-refresh dashboard stats every 30 seconds
    if (window.location.pathname.includes('dashboard') || window.location.search.includes('action=dashboard')) {
        setInterval(refreshDashboardStats, 30000);
    }
}

function checkDeploymentStatus(deploymentId) {
    fetch(`/server-admin/ajax/deployment-status.php?id=${deploymentId}`)
        .then(response => response.json())
        .then(data => {
            updateDeploymentStatusUI(deploymentId, data);
            
            // Continue checking if deployment is still running
            if (data.status === 'running') {
                setTimeout(() => checkDeploymentStatus(deploymentId), 2000);
            }
        })
        .catch(error => {
            console.error('Failed to check deployment status:', error);
        });
}

function updateDeploymentStatusUI(deploymentId, data) {
    const statusElement = document.querySelector(`[data-deployment-id="${deploymentId}"]`);
    if (!statusElement) return;
    
    // Update status badge
    const badge = statusElement.querySelector('.status-badge');
    if (badge) {
        badge.className = 'status-badge';
        badge.textContent = getStatusText(data.status);
        badge.classList.add(getStatusClass(data.status));
    }
    
    // Update progress if available
    const progressElement = statusElement.querySelector('.deployment-progress');
    if (progressElement && data.progress) {
        progressElement.style.width = data.progress + '%';
    }
}

function getStatusText(status) {
    const statusTexts = {
        'pending': '⏳ Pending',
        'running': '🔄 Running',
        'completed': '✅ Completed',
        'failed': '❌ Failed',
        'cancelled': '🚫 Cancelled'
    };
    return statusTexts[status] || status;
}

function getStatusClass(status) {
    const statusClasses = {
        'pending': 'status-warning',
        'running': 'status-info',
        'completed': 'status-success',
        'failed': 'status-error',
        'cancelled': 'status-secondary'
    };
    return statusClasses[status] || 'status-info';
}

function refreshDashboardStats() {
    fetch('/server-admin/ajax/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboardStats(data.stats);
            }
        })
        .catch(error => {
            console.error('Failed to refresh dashboard stats:', error);
        });
}

function updateDashboardStats(stats) {
    // Update stat cards
    Object.entries(stats).forEach(([key, value]) => {
        const element = document.querySelector(`[data-stat="${key}"]`);
        if (element) {
            element.textContent = value;
        }
    });
}

// Keyboard Shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            showGlobalSearch();
        }
        
        // Ctrl/Cmd + N for new application (if on applications page)
        if ((e.ctrlKey || e.metaKey) && e.key === 'n' && window.location.search.includes('applications')) {
            e.preventDefault();
            if (typeof showCreateAppModal === 'function') {
                showCreateAppModal();
            }
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

function showGlobalSearch() {
    // Implement global search functionality
    console.log('Global search not yet implemented');
}

function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (modal.style.display !== 'none') {
            modal.style.display = 'none';
        }
    });
}

// Form Validation
function initializeFormValidation() {
    // Add real-time validation to forms
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(input);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(input);
            });
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');
    let isValid = true;
    let errorMessage = '';
    
    // Required field validation
    if (required && !value) {
        isValid = false;
        errorMessage = 'This field is required';
    }
    
    // Type-specific validation
    if (value && type === 'email') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        }
    }
    
    if (value && type === 'url') {
        try {
            new URL(value);
        } catch {
            isValid = false;
            errorMessage = 'Please enter a valid URL';
        }
    }
    
    // Custom validation
    if (field.hasAttribute('data-validate-git-url')) {
        if (value && !isValidGitUrl(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid Git repository URL';
        }
    }
    
    // Show/hide error
    if (isValid) {
        clearFieldError(field);
    } else {
        showFieldError(field, errorMessage);
    }
    
    return isValid;
}

function isValidGitUrl(url) {
    const gitUrlPatterns = [
        /^https:\/\/github\.com\/[\w\-\.]+\/[\w\-\.]+(?:\.git)?$/,
        /^https:\/\/gitlab\.com\/[\w\-\.]+\/[\w\-\.]+(?:\.git)?$/,
        /^https:\/\/bitbucket\.org\/[\w\-\.]+\/[\w\-\.]+(?:\.git)?$/,
        /^https:\/\/[a-zA-Z0-9\-\.]+\/[\w\-\.\/]+(?:\.git)?$/
    ];
    
    return gitUrlPatterns.some(pattern => pattern.test(url));
}

function showFieldError(field, message) {
    clearFieldError(field);
    
    field.classList.add('error');
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.textContent = message;
    errorElement.style.cssText = `
        color: #dc2626;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    `;
    
    field.parentNode.appendChild(errorElement);
}

function clearFieldError(field) {
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Notification System
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 1000;
        max-width: 400px;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after duration
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
    
    return notification;
}

// API Helper Functions
function apiRequest(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        },
    };
    
    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers,
        },
    };
    
    return fetch(url, mergedOptions)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('API request failed:', error);
            showNotification('Request failed. Please try again.', 'error');
            throw error;
        });
}

function showLoadingOverlay(message = 'Processing...') {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        const messageElement = overlay.querySelector('p');
        if (messageElement) {
            messageElement.textContent = message;
        }
        overlay.style.display = 'flex';
    }
}

function hideLoadingOverlay() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

// Utility Functions
function formatFileSize(bytes) {
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    if (bytes === 0) return '0 B';
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + ' days ago';
    
    return date.toLocaleDateString();
}

function sanitizeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Export functions for use in other scripts
window.AdminUtils = {
    showNotification,
    apiRequest,
    showLoadingOverlay,
    hideLoadingOverlay,
    formatFileSize,
    formatTimeAgo,
    sanitizeHTML,
    debounce,
    throttle,
    validateField,
    isValidGitUrl
};

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAdminPanel);
} else {
    initializeAdminPanel();
} 