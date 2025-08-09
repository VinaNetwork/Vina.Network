// ============================================================================
// File: make-market/process/auth.js
// Description: JavaScript utility for handling CSRF token and AJAX authentication
// Created by: Vina Network
// ============================================================================

// Set default values for global variables
window.SOLANA_NETWORK = window.SOLANA_NETWORK || 'devnet'; // Match config.php
window.ENVIRONMENT = window.ENVIRONMENT || 'development'; // Match bootstrap.php

// Log message function (reused from process.js to avoid duplication)
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
    if (log_type === 'DEBUG' && window.ENVIRONMENT !== 'development') {
        return;
    }
    const logMessage = message + ', network=' + window.SOLANA_NETWORK;
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
            console.error('Log failed: HTTP ' + response.status + ', network=' + window.SOLANA_NETWORK);
        }
    }).catch(function(err) {
        console.error('Log error: ' + err.message + ', network=' + window.SOLANA_NETWORK);
    });
}

// Delay function for retry
function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Fetch CSRF token from server with retry
async function getCsrfToken(maxRetries = 3, retryDelay = 1000) {
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            log_message(`Attempting to fetch CSRF token (attempt ${attempt + 1}/${maxRetries})`, 'make-market.log', 'make-market', 'DEBUG');
            const response = await fetch('/make-market/get-csrf', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'include' // Ensure cookies are sent
            });
            if (response.status === 401) {
                log_message('User not authenticated, redirecting to login', 'make-market.log', 'make-market', 'ERROR');
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
                throw new Error('User not authenticated');
            }
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const result = await response.json();
            if (result.status !== 'success' || !result.csrf_token) {
                throw new Error(result.message || 'Invalid CSRF token response');
            }
            log_message('CSRF token fetched: ' + result.csrf_token, 'make-market.log', 'make-market', 'INFO');
            console.log('CSRF token fetched: ' + result.csrf_token + ', network=' + window.SOLANA_NETWORK);
            return result.csrf_token;
        } catch (err) {
            attempt++;
            log_message(`Failed to fetch CSRF token (attempt ${attempt}/${maxRetries}): ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
            console.error(`Failed to fetch CSRF token (attempt ${attempt}/${maxRetries}): ${err.message}, network=${window.SOLANA_NETWORK}`);
            if (attempt === maxRetries) {
                throw new Error(`Failed to fetch CSRF token after ${maxRetries} attempts: ${err.message}`);
            }
            await delay(retryDelay * attempt);
        }
    }
}

// Initialize authentication and return CSRF token
let cachedCsrfToken = null;
async function initializeAuth() {
    if (!cachedCsrfToken) {
        cachedCsrfToken = await getCsrfToken();
    }
    // Validate network
    if (!['testnet', 'mainnet', 'devnet'].includes(window.SOLANA_NETWORK)) {
        log_message('Invalid network: ' + window.SOLANA_NETWORK, 'make-market.log', 'make-market', 'ERROR');
        throw new Error('Invalid network: ' + window.SOLANA_NETWORK);
    }
    log_message('Authentication initialized, CSRF token: ' + cachedCsrfToken, 'make-market.log', 'make-market', 'INFO');
    return cachedCsrfToken;
}

// Add CSRF token to fetch headers
function addAuthHeaders(headers = {}) {
    if (!cachedCsrfToken) {
        log_message('CSRF token not initialized, call initializeAuth first', 'make-market.log', 'make-market', 'ERROR');
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
        log_message('CSRF token not initialized, call initializeAuth first', 'make-market.log', 'make-market', 'ERROR');
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
