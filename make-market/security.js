// ============================================================================
// File: make-market/security.js
// Description: Utility functions for secure AJAX requests with CSRF token
// Created by: Vina Network
// ============================================================================

// Log message function (tái sử dụng từ mm.js)
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
    if (log_type === 'DEBUG' && (!window.ENVIRONMENT || window.ENVIRONMENT !== 'development')) {
        return;
    }
    fetch('/make-market/log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, log_file, module, log_type })
    }).then(response => {
        if (!response.ok) {
            console.error(`Log failed: HTTP ${response.status}`);
        }
    }).catch(err => console.error('Log error:', err));
}

// Hàm chung để gửi request AJAX với CSRF token
async function sendRequest(url, data, options = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (!csrfToken) {
        log_message('CSRF token not found in meta tag', 'make-market.log', 'security', 'ERROR');
        throw new Error('CSRF token not found');
    }

    const defaultOptions = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    };

    const mergedOptions = { ...defaultOptions, ...options };

    log_message(`Sending request to ${url}: ${JSON.stringify(data)}`, 'make-market.log', 'security', 'DEBUG');
    console.log(`Sending request to ${url}:`, data);

    try {
        const response = await fetch(url, mergedOptions);
        const responseText = await response.text();
        log_message(`Response from ${url}: HTTP ${response.status}, Response: ${responseText}`, 'make-market.log', 'security', 'DEBUG');
        console.log(`Response from ${url}: HTTP ${response.status}, Response:`, responseText);

        if (!response.ok) {
            log_message(`Request failed: HTTP ${response.status}, Response: ${responseText}`, 'make-market.log', 'security', 'ERROR');
            throw new Error(`Request failed: HTTP ${response.status}`);
        }

        let result;
        try {
            result = JSON.parse(responseText);
        } catch (error) {
            log_message(`Error parsing JSON response from ${url}: ${error.message}, Response: ${responseText}`, 'make-market.log', 'security', 'ERROR');
            throw new Error(`Invalid response from server: ${error.message}`);
        }

        if (result.status !== 'success') {
            log_message(`Request failed: ${result.message}`, 'make-market.log', 'security', 'ERROR');
            throw new Error(result.message);
        }

        return result;
    } catch (error) {
        log_message(`Error sending request to ${url}: ${error.message}`, 'make-market.log', 'security', 'ERROR');
        throw error;
    }
}

// Cập nhật trạng thái giao dịch
async function updateTransactionStatus(transactionId, newStatus, errorMessage = null) {
    const validStatuses = ['pending', 'processing', 'failed', 'success', 'canceled'];
    if (!validStatuses.includes(newStatus)) {
        log_message(`Invalid status: ${newStatus} for transactionId=${transactionId}`, 'make-market.log', 'security', 'ERROR');
        throw new Error('Invalid status');
    }

    const data = {
        id: parseInt(transactionId),
        status: newStatus,
        error: errorMessage
    };

    return await sendRequest('/make-market/process/get-status.php', data);
}

// Lấy số decimals của token
async function getTokenDecimals(tokenMint, network) {
    if (!tokenMint || !/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(tokenMint)) {
        log_message(`Invalid tokenMint: ${tokenMint}`, 'make-market.log', 'security', 'ERROR');
        throw new Error('Invalid token address');
    }
    if (!['mainnet', 'testnet'].includes(network)) {
        log_message(`Invalid network: ${network}`, 'make-market.log', 'security', 'ERROR');
        throw new Error('Invalid network');
    }

    const data = {
        tokenMint: tokenMint,
        network: network
    };

    const result = await sendRequest('/make-market/process/get-decimals.php', data);
    return result.decimals;
}

// Export các hàm để sử dụng trong các file khác
export { sendRequest, updateTransactionStatus, getTokenDecimals };
