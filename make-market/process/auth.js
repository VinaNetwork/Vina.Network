// ============================================================================
// File: make-market/process/auth.js
// Description: JavaScript utility for handling CSRF token and AJAX authentication
// Created by: Vina Network
// ============================================================================

// Log message function (reused from process.js to avoid duplication)
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
    if (log_type === 'DEBUG' && (!window.ENVIRONMENT || window.ENVIRONMENT !== 'development')) {
        return;
    }
    const logMessage = message + ', network=' + (window.SOLANA_NETWORK || 'unknown');
    fetch('/make-market/log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: logMessage,
            log_file: log_file,
            module: module,
            log_type: log_type
        })
    }).then(function(response) {
        if (!response.ok) {
            console.error('Log failed: HTTP ' + response.status + ', network=' + (window.SOLANA_NETWORK || 'unknown'));
        }
    }).catch(function(err) {
        console.error('Log error: ' + err.message + ', network=' + (window.SOLANA_NETWORK || 'unknown'));
    });
}

// Fetch CSRF token from server
async function getCsrfToken() {
    try {
        const response = await fetch('/make-market/get-csrf', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message || 'Failed to fetch CSRF token');
        }
        log_message('CSRF token fetched: ' + result.csrf_token, 'make-market.log', 'make-market', 'INFO');
        console.log('CSRF token fetched: ' + result.csrf_token + ', network=' + (window.SOLANA_NETWORK || 'unknown'));
        return result.csrf_token;
    } catch (err) {
        log_message('Failed to fetch CSRF token: ' + err.message, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to fetch CSRF token: ' + err.message + ', network=' + (window.SOLANA_NETWORK || 'unknown'));
        throw err;
    }
}

// Initialize authentication and return CSRF token
let cachedCsrfToken = null;
async function initializeAuth() {
    if (!cachedCsrfToken) {
        cachedCsrfToken = await getCsrfToken();
    }
    // Validate network
    if (!['testnet', 'mainnet'].includes(window.SOLANA_NETWORK)) {
        log_message('Invalid network: ' + (window.SOLANA_NETWORK || 'undefined'), 'make-market.log', 'make-market', 'ERROR');
        throw new Error('Invalid network: ' + (window.SOLANA_NETWORK || 'undefined'));
    }
    return cachedCsrfToken;
}

// Add CSRF token to fetch headers
function addAuthHeaders(headers = {}) {
    if (!cachedCsrfToken) {
        throw new Error('CSRF token not initialized. Call initializeAuth first.');
    }
    return {
        ...headers,
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': cachedCsrfToken
    };
}

// Add CSRF token to axios headers
function addAxiosAuthHeaders(config = {}) {
    if (!cachedCsrfToken) {
        throw new Error('CSRF token not initialized. Call initializeAuth first.');
    }
    return {
        ...config,
        headers: {
            ...config.headers,
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': cachedCsrfToken
        }
    };
}

// Export functions for use in other scripts
export { initializeAuth, addAuthHeaders, addAxiosAuthHeaders };
