<?php
// Loading Animation Component
// This component provides loading animations for page transitions and AJAX requests
?>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div class="loading-container">
        <div class="loading-spinner">
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
        </div>
        <div class="loading-text">
            <span class="loading-message">Loading...</span>
            <div class="loading-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </div>
</div>

<!-- Page Transition Loading -->
<div id="page-loading" class="page-loading" style="display: none;">
    <div class="page-loading-content">
        <div class="page-spinner">
            <div class="page-spinner-inner"></div>
        </div>
        <p class="page-loading-text">Loading page...</p>
    </div>
</div>

<!-- AJAX Loading Indicator -->
<div id="ajax-loading" class="ajax-loading" style="display: none;">
    <div class="ajax-spinner"></div>
</div>

<style>
/* MINIMALIST AESTHETIC THEME - KHAKI & EARTH TONES */
:root {
    --khaki-light: #f7f5f3;
    --khaki-medium: #d4c4b0;
    --khaki-dark: #b8a082;
    --khaki-deep: #8b7355;
    --cream: #faf8f5;
    --beige: #e8ddd4;
    --sage: #9caf88;
    --terracotta: #c17b5c;
    --charcoal: #2c2c2c;
    --text-primary: #2c2c2c;
    --text-secondary: #6b6b6b;
    --text-muted: #9a9a9a;
    --bg-primary: #faf8f5;
    --bg-secondary: #f7f5f3;
    --bg-accent: rgba(212, 196, 176, 0.1);
    --border-light: rgba(184, 160, 130, 0.2);
    --shadow-subtle: 0 2px 20px rgba(139, 115, 85, 0.08);
    --gradient-warm: linear-gradient(135deg, #f7f5f3 0%, #e8ddd4 50%, #d4c4b0 100%);
    --gradient-accent: linear-gradient(135deg, var(--sage) 0%, var(--terracotta) 100%);
}

/* Loading Overlay Styles */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(250, 248, 245, 0.95);
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.loading-overlay.show {
    opacity: 1;
    visibility: visible;
    display: flex !important;
}

.loading-container {
    text-align: center;
    color: var(--text-primary);
    background: var(--bg-primary);
    padding: 40px;
    border-radius: 20px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

.loading-spinner {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
}

.spinner-ring {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 3px solid transparent;
    border-top: 3px solid var(--sage);
    border-radius: 50%;
    animation: spin 1.2s linear infinite;
}

.spinner-ring:nth-child(2) {
    animation-delay: -0.3s;
    border-top-color: var(--terracotta);
    width: 90%;
    height: 90%;
    top: 5%;
    left: 5%;
}

.spinner-ring:nth-child(3) {
    animation-delay: -0.6s;
    border-top-color: var(--khaki-dark);
    width: 80%;
    height: 80%;
    top: 10%;
    left: 10%;
}

.spinner-ring:nth-child(4) {
    animation-delay: -0.9s;
    border-top-color: var(--khaki-deep);
    width: 70%;
    height: 70%;
    top: 15%;
    left: 15%;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-text {
    font-size: 18px;
    font-weight: 400;
    color: var(--text-secondary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    letter-spacing: -0.01em;
}

.loading-message {
    display: block;
    margin-bottom: 10px;
}

.loading-dots {
    display: flex;
    justify-content: center;
    gap: 4px;
}

.loading-dots span {
    width: 8px;
    height: 8px;
    background: var(--sage);
    border-radius: 50%;
    animation: bounce 1.4s ease-in-out infinite both;
}

.loading-dots span:nth-child(1) { animation-delay: -0.32s; }
.loading-dots span:nth-child(2) { animation-delay: -0.16s; }
.loading-dots span:nth-child(3) { animation-delay: 0s; }

@keyframes bounce {
    0%, 80%, 100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}

/* Page Transition Loading */
.page-loading {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--gradient-warm);
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.5s ease;
}

.page-loading.show {
    opacity: 1;
    visibility: visible;
    display: flex !important;
}

.page-loading-content {
    text-align: center;
    color: var(--text-primary);
    background: rgba(250, 248, 245, 0.9);
    padding: 30px;
    border-radius: 15px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

.page-spinner {
    width: 60px;
    height: 60px;
    margin: 0 auto 20px;
    position: relative;
}

.page-spinner-inner {
    width: 100%;
    height: 100%;
    border: 4px solid var(--border-light);
    border-top: 4px solid var(--sage);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.page-loading-text {
    font-size: 16px;
    font-weight: 400;
    margin: 0;
    color: var(--text-secondary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* AJAX Loading Indicator */
.ajax-loading {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    background: var(--sage);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-subtle);
    border: 2px solid white;
}

.ajax-loading.show {
    opacity: 1;
    visibility: visible;
    display: flex !important;
}

.ajax-spinner {
    width: 30px;
    height: 30px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-top: 3px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Button Loading States */
.btn.loading {
    position: relative;
    color: transparent !important;
    pointer-events: none;
}

.btn.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid transparent;
    border-top: 2px solid var(--sage);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Form Loading States */
.form-loading {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

.form-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 40px;
    height: 40px;
    margin: -20px 0 0 -20px;
    border: 3px solid var(--bg-secondary);
    border-top: 3px solid var(--sage);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    z-index: 1000;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .loading-spinner {
        width: 60px;
        height: 60px;
    }
    
    .loading-text {
        font-size: 16px;
    }
    
    .ajax-loading {
        width: 40px;
        height: 40px;
        top: 15px;
        right: 15px;
    }
    
    .ajax-spinner {
        width: 24px;
        height: 24px;
    }
}

/* Mobile Responsive Updates */
@media (max-width: 768px) {
    .loading-container {
        padding: 30px 20px;
        margin: 20px;
    }
    
    .page-loading-content {
        padding: 20px;
        margin: 20px;
    }
}
</style>

<script>
// Loading Animation Controller
class LoadingController {
    constructor() {
        this.overlay = document.getElementById('loading-overlay');
        this.pageLoading = document.getElementById('page-loading');
        this.ajaxLoading = document.getElementById('ajax-loading');
        this.setupPageTransitions();
        this.setupAjaxLoading();
    }

    // Show main loading overlay
    showOverlay(message = 'Loading...') {
        if (this.overlay) {
            const messageElement = this.overlay.querySelector('.loading-message');
            if (messageElement) {
                messageElement.textContent = message;
            }
            this.overlay.classList.add('show');
        }
    }

    // Hide main loading overlay
    hideOverlay() {
        if (this.overlay) {
            this.overlay.classList.remove('show');
        }
    }

    // Show page transition loading
    showPageLoading() {
        if (this.pageLoading) {
            this.pageLoading.classList.add('show');
        }
    }

    // Hide page transition loading
    hidePageLoading() {
        if (this.pageLoading) {
            this.pageLoading.classList.remove('show');
        }
    }

    // Show AJAX loading indicator
    showAjaxLoading() {
        if (this.ajaxLoading) {
            this.ajaxLoading.classList.add('show');
        }
    }

    // Hide AJAX loading indicator
    hideAjaxLoading() {
        if (this.ajaxLoading) {
            this.ajaxLoading.classList.remove('show');
        }
    }

    // Set button loading state
    setButtonLoading(button, loading = true) {
        if (loading) {
            button.classList.add('loading');
            button.disabled = true;
        } else {
            button.classList.remove('loading');
            button.disabled = false;
        }
    }

    // Set form loading state
    setFormLoading(form, loading = true) {
        if (loading) {
            form.classList.add('form-loading');
        } else {
            form.classList.remove('form-loading');
        }
    }

    // Setup page transition loading
    setupPageTransitions() {
        // Show loading on page unload
        window.addEventListener('beforeunload', () => {
            this.showPageLoading();
        });

        // Hide loading when page is fully loaded
        window.addEventListener('load', () => {
            this.hidePageLoading();
        });

        // Handle back/forward navigation
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                this.hidePageLoading();
            }
        });
    }

    // Setup AJAX loading
    setupAjaxLoading() {
        // Intercept fetch requests
        const originalFetch = window.fetch;
        let activeRequests = 0;

        window.fetch = async (...args) => {
            activeRequests++;
            this.showAjaxLoading();

            try {
                const response = await originalFetch(...args);
                return response;
            } finally {
                activeRequests--;
                if (activeRequests === 0) {
                    setTimeout(() => this.hideAjaxLoading(), 300);
                }
            }
        };

        // Intercept XMLHttpRequest
        const originalXHR = window.XMLHttpRequest;
        window.XMLHttpRequest = function() {
            const xhr = new originalXHR();
            const originalOpen = xhr.open;
            const originalSend = xhr.send;

            xhr.open = function(...args) {
                activeRequests++;
                this.showAjaxLoading();
                return originalOpen.apply(this, args);
            }.bind(this);

            xhr.send = function(...args) {
                const result = originalSend.apply(this, args);
                xhr.addEventListener('loadend', () => {
                    activeRequests--;
                    if (activeRequests === 0) {
                        setTimeout(() => this.hideAjaxLoading(), 300);
                    }
                });
                return result;
            };

            return xhr;
        };
    }

    // Show loading with custom message
    show(message, duration = null) {
        this.showOverlay(message);
        if (duration) {
            setTimeout(() => this.hideOverlay(), duration);
        }
    }

    // Hide all loading indicators
    hideAll() {
        this.hideOverlay();
        this.hidePageLoading();
        this.hideAjaxLoading();
    }
}

// Initialize loading controller
const loadingController = new LoadingController();

// Global functions for easy access
function showLoading(message = 'Loading...') {
    loadingController.showOverlay(message);
}

function hideLoading() {
    loadingController.hideOverlay();
}

function showPageLoading() {
    loadingController.showPageLoading();
}

function hidePageLoading() {
    loadingController.hidePageLoading();
}

function showAjaxLoading() {
    loadingController.showAjaxLoading();
}

function hideAjaxLoading() {
    loadingController.hideAjaxLoading();
}

function setButtonLoading(button, loading = true) {
    loadingController.setButtonLoading(button, loading);
}

function setFormLoading(form, loading = true) {
    loadingController.setFormLoading(form, loading);
}

// Enhanced addToCartAjax with loading
function addToCartAjaxWithLoading(productId, quantity = 1) {
    const button = event.target;
    setButtonLoading(button, true);
    
    const formData = new FormData();
    formData.append('ajax_add_to_cart', '1');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);

    fetch('ajax-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = data.cart_count;
                cartCount.classList.add('animate');
                setTimeout(() => cartCount.classList.remove('animate'), 600);
            }

            // Show notification
            if (typeof showNotification === 'function') {
                showNotification('success', data.message, data.product_name);
            }

            // Show success state briefly
            button.innerHTML = '<i class="fas fa-check"></i> Added!';
            setTimeout(() => {
                setButtonLoading(button, false);
                button.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
            }, 1500);
        } else {
            if (data.login_required) {
                if (typeof showNotification === 'function') {
                    showNotification('warning', data.message);
                }
                setTimeout(() => {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                }, 2000);
            } else {
                if (typeof showNotification === 'function') {
                    showNotification('error', data.message);
                }
            }
            setButtonLoading(button, false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof showNotification === 'function') {
            showNotification('error', 'Something went wrong. Please try again.');
        }
        setButtonLoading(button, false);
    });
}
</script>
