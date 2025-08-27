// ============================================================================
// File: mm/process/process.js
// Description: JavaScript for processing Solana token swap with looping using Jupiter Aggregator API
// Created by: Vina Network
// ============================================================================

// Hàm làm mới CSRF token khi cần thiết
async function getCsrfToken(maxRetries = 3, retryDelay = 1000) {
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            log_message(`Attempting to fetch CSRF token from /mm/refresh-csrf (attempt ${attempt + 1}/${maxRetries}), cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            const response = await fetch('/mm/refresh-csrf', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });
            const responseBody = await response.text();
            log_message(`Response from /mm/refresh-csrf: status=${response.status}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${responseBody}`);
            }
            const result = JSON.parse(responseBody);
            if (result.status !== 'success' || !result.csrf_token) {
                throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
            }
            window.CSRF_TOKEN = result.csrf_token;
            window.CSRF_TOKEN_TIMESTAMP = Date.now();
            log_message(`CSRF token retrieved: ${window.CSRF_TOKEN.substring(0, 4)}..., session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
            console.log(`CSRF token retrieved: ${window.CSRF_TOKEN.substring(0, 4)}...`);
            return window.CSRF_TOKEN;
        } catch (err) {
            attempt++;
            log_message(`Failed to fetch CSRF token (attempt ${attempt}/${maxRetries}): ${err.message}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
            console.error(`Failed to fetch CSRF token (attempt ${attempt}/${maxRetries}): ${err.message}`);
            if (attempt === maxRetries) {
                throw new Error(`Failed to fetch CSRF token after ${maxRetries} attempts: ${err.message}`);
            }
            await delay(retryDelay * attempt);
        }
    }
}

// Hàm xóa CSRF token sau khi giao dịch hoàn tất
async function clearCsrfToken() {
    try {
        const headers = addAuthHeaders({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        });
        log_message(`Clearing CSRF token, headers=${JSON.stringify(headers)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
        const response = await fetch('/mm/clear-csrf', {
            method: 'POST',
            headers,
            credentials: 'include'
        });
        const responseBody = await response.text();
        log_message(`Response from /mm/clear-csrf: status=${response.status}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${responseBody}`);
        }
        const result = JSON.parse(responseBody);
        if (result.status !== 'success') {
            throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
        }
        log_message(`CSRF token cleared successfully, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
        console.log('CSRF token cleared successfully');
    } catch (err) {
        log_message(`Failed to clear CSRF token: ${err.message}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
        console.error('Failed to clear CSRF token:', err.message);
    }
}

// Kiểm tra CSRF token, chỉ làm mới nếu cần
async function ensureAuthInitialized() {
    if (!window.CSRF_TOKEN) {
        log_message(`CSRF token missing, fetching new token`, 'process.log', 'make-market', 'WARNING');
        window.CSRF_TOKEN = await getCsrfToken();
        window.CSRF_TOKEN_TIMESTAMP = Date.now();
    }
    return window.CSRF_TOKEN;
}

// Thêm CSRF token vào headers
function addAuthHeaders(headers = {}) {
    const csrfToken = window.CSRF_TOKEN;
    if (!csrfToken) {
        console.warn('CSRF token is missing');
        log_message('CSRF token is missing in addAuthHeaders', 'process.log', 'make-market', 'WARNING');
    }
    return {
        ...headers,
        'X-CSRF-Token': csrfToken || ''
    };
}

// Thêm CSRF token vào headers cho axios
function addAxiosAuthHeaders(config = {}) {
    const csrfToken = window.CSRF_TOKEN;
    if (!csrfToken) {
        console.warn('CSRF token is missing');
        log_message('CSRF token is missing in addAxiosAuthHeaders', 'process.log', 'make-market', 'WARNING');
    }
    return {
        ...config,
        headers: {
            ...config.headers,
            'X-CSRF-Token': csrfToken || ''
        }
    };
}

// Copy functionality
document.addEventListener('DOMContentLoaded', () => {
    console.log('process.js loaded');
    const copyIcons = document.querySelectorAll('.copy-icon');
    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');
            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);
            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
                icon.classList.add('copied');
                const tooltip = document.createElement('span');
                tooltip.className = 'copy-tooltip';
                tooltip.textContent = 'Copied!';
                const parent = icon.parentNode;
                parent.style.position = 'relative';
                parent.appendChild(tooltip);
                setTimeout(() => {
                    icon.classList.remove('copied');
                    tooltip.remove();
                    console.log('Copy feedback removed');
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });

    // Handle form submission for Cancel button
    const cancelForm = document.getElementById('cancel-form');
    if (cancelForm) {
        cancelForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const transactionId = new URLSearchParams(window.location.search).get('id') || window.location.pathname.split('/').pop();
            log_message(`Cancel form submitted for transaction ID=${transactionId}, cookies=${document.cookie}`, 'process.log', 'make-market', 'INFO');
            await cancelTransaction(transactionId);
        });
    }
});

// Log message function
function log_message(message, log_file = 'process.log', module = 'make-market', log_type = 'INFO') {
    if (log_type === 'DEBUG' && (!window.ENVIRONMENT || window.ENVIRONMENT !== 'development')) {
        return;
    }
    const session_id = document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none';
    const csrfToken = window.CSRF_TOKEN ? window.CSRF_TOKEN.substring(0, 4) + '...' : 'none';
    const logMessage = `${message}, session_id=${session_id}, csrf_token=${csrfToken}`;
    fetch('/mm/log.php', {
        method: 'POST',
        headers: addAuthHeaders({
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }),
        credentials: 'include',
        body: JSON.stringify({ message: logMessage, log_file, module, log_type })
    }).then(response => {
        if (!response.ok) {
            console.error(`Log failed: HTTP ${response.status}, session_id=${session_id}, response=${response.statusText}`);
        }
    }).catch(err => console.error(`Log error: ${err.message}, session_id=${session_id}`));
}

// Delay function
function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Show error message
async function showError(message, detailedError = null) {
    let userFriendlyMessage = 'Đã xảy ra lỗi trong quá trình giao dịch. Vui lòng thử lại sau.';
    
    // Xử lý các trường hợp lỗi cụ thể để hiển thị thông báo thân thiện
    if (detailedError?.includes('TOKEN_NOT_TRADABLE')) {
        userFriendlyMessage = 'Token không thể giao dịch. Vui lòng kiểm tra tính thanh khoản của token hoặc chọn token khác.';
    } else if (detailedError?.includes('Invalid or expired CSRF token')) {
        userFriendlyMessage = 'Phiên của bạn đã hết hạn. Vui lòng làm mới trang để tiếp tục.';
    } else if (detailedError?.includes('Network Error')) {
        userFriendlyMessage = 'Lỗi kết nối mạng. Vui lòng kiểm tra kết nối internet và thử lại.';
    }

    const resultDiv = document.getElementById('process-result');
    resultDiv.innerHTML = `
        <div class="alert alert-danger">
            <strong>Lỗi:</strong> ${userFriendlyMessage}
            ${detailedError && window.ENVIRONMENT === 'development' ? `<br>Chi tiết kỹ thuật: ${detailedError}` : ''}
        </div>
    `;
    resultDiv.classList.add('active');
    document.getElementById('swap-status').textContent = '';
    document.getElementById('transaction-status').textContent = 'Failed';
    document.getElementById('transaction-status').classList.add('text-danger');
    
    const transactionId = new URLSearchParams(window.location.search).get('id') || window.location.pathname.split('/').pop();
    log_message(
        `Process stopped: ${userFriendlyMessage}${detailedError ? `, Details: ${detailedError}` : ''}, transactionId=${transactionId}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`,
        'process.log', 'make-market', 'ERROR'
    );
    console.error(`Process stopped: ${userFriendlyMessage}${detailedError ? `, Details: ${detailedError}` : ''}`);
    
    await updateTransactionStatus('failed', detailedError || message);
    await clearCsrfToken();
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Show success message
async function showSuccess(message, results = [], networkConfig) {
    if (!networkConfig.explorerUrl || !networkConfig.explorerQuery) {
        log_message(`Invalid network config: explorerUrl=${networkConfig.explorerUrl || 'undefined'}, explorerQuery=${networkConfig.explorerQuery || 'undefined'}, network=${networkConfig.network}`, 'process.log', 'make-market', 'ERROR');
        await showError('Invalid network configuration: missing explorerUrl or explorerQuery');
        return;
    }
    const resultDiv = document.getElementById('process-result');
    let html = `
        <div class="alert alert-${results.some(r => r.status === 'error') ? 'warning' : 'success'}">
            <strong>${results.some(r => r.status === 'error') ? 'Partial Success' : 'Success'}:</strong> ${message}
    `;
    if (results.length > 0) {
        html += '<ul>';
        results.forEach(result => {
            html += `<li>Loop ${result.loop}, Batch ${result.batch_index} (${result.direction}): ${
                result.status === 'success' 
                    ? `<a href="${networkConfig.explorerUrl}${result.txid}${networkConfig.explorerQuery}" target="_blank">Success (txid: ${result.txid})</a>`
                    : `Failed - ${result.message}`
            }</li>`;
        });
        html += '</ul>';
    }
    html += '</div>';
    resultDiv.innerHTML = html;
    resultDiv.classList.add('active');
    document.getElementById('swap-status').textContent = '';
    document.getElementById('transaction-status').textContent = results.some(r => r.status === 'error') ? 'Partial' : 'Success';
    document.getElementById('transaction-status').classList.add(results.some(r => r.status === 'error') ? 'text-warning' : 'text-success');
    log_message(`Process completed: ${message}, network=${networkConfig.network}`, 'process.log', 'make-market', 'INFO');
    console.log(`Process completed: ${message}, network=${networkConfig.network}`);
    await clearCsrfToken(); // Xóa CSRF token khi giao dịch thành công
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Update transaction status
async function updateTransactionStatus(status, error = null) {
    const transactionId = new URLSearchParams(window.location.search).get('id') || window.location.pathname.split('/').pop();
    const maxRetries = 3;
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            await ensureAuthInitialized();
            const headers = addAuthHeaders({
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            });
            log_message(`Updating transaction status: ID=${transactionId}, status=${status}, error=${error || 'none'}, headers=${JSON.stringify(headers)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            const response = await fetch(`/mm/get-status/${transactionId}`, {
                method: 'POST',
                headers,
                body: JSON.stringify({ id: transactionId, status, error }),
                credentials: 'include'
            });
            const responseBody = await response.text();
            log_message(`Response from /mm/get-status/${transactionId}: status=${response.status}, headers=${JSON.stringify([...response.headers.entries()])}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
            if (!response.ok) {
                let result;
                try {
                    result = JSON.parse(responseBody);
                } catch (e) {
                    result = {};
                }
                if (response.status === 401) {
                    window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                if (response.status === 403 && result.error === 'Invalid or expired CSRF token') {
                    log_message(`CSRF token invalid or expired, retrying with new token (attempt ${attempt + 1}/${maxRetries})`, 'process.log', 'make-market', 'WARNING');
                    attempt++;
                    if (attempt === maxRetries) {
                        throw new Error(`Failed to update transaction status after ${maxRetries} attempts: ${result.error}`);
                    }
                    await getCsrfToken();
                    continue;
                }
                throw new Error(`HTTP ${response.status}: ${JSON.stringify(result)}`);
            }
            const result = JSON.parse(responseBody);
            if (result.status !== 'success') {
                throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
            }
            log_message(`Transaction status updated: ID=${transactionId}, status=${status}, error=${error || 'none'}`, 'process.log', 'make-market', 'INFO');
            console.log(`Transaction status updated: ID=${transactionId}, status=${status}, error=${error || 'none'}`);
            return;
        } catch (err) {
            log_message(`Failed to update transaction status: ${err.message}, transactionId=${transactionId}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
            console.error('Failed to update transaction status:', err.message);
            if (attempt === maxRetries - 1) {
                showError('Failed to update transaction status: ' + err.message, err.message);
            }
        }
    }
}

// Cancel transaction
async function cancelTransaction(transactionId) {
    const maxRetries = 3;
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            await ensureAuthInitialized();
            const headers = addAuthHeaders({
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            });
            log_message(`Canceling transaction: ID=${transactionId}, headers=${JSON.stringify(headers)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            const response = await fetch(`/mm/get-status/${transactionId}`, {
                method: 'POST',
                headers,
                body: JSON.stringify({ id: transactionId, status: 'canceled', error: 'Transaction canceled by user' }),
                credentials: 'include'
            });
            const responseBody = await response.text();
            log_message(`Response from /mm/get-status/${transactionId}: status=${response.status}, headers=${JSON.stringify([...response.headers.entries()])}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
            if (!response.ok) {
                let result;
                try {
                    result = JSON.parse(responseBody);
                } catch (e) {
                    result = {};
                }
                if (response.status === 401) {
                    window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                if (response.status === 403 && result.error === 'Invalid or expired CSRF token') {
                    log_message(`CSRF token invalid or expired, retrying with new token (attempt ${attempt + 1}/${maxRetries})`, 'process.log', 'make-market', 'WARNING');
                    attempt++;
                    if (attempt === maxRetries) {
                        throw new Error(`Failed to cancel transaction after ${maxRetries} attempts: ${result.error}`);
                    }
                    await getCsrfToken();
                    continue;
                }
                throw new Error(`HTTP ${response.status}: ${JSON.stringify(result)}`);
            }
            const result = JSON.parse(responseBody);
            if (result.status !== 'success') {
                throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
            }
            log_message(`Transaction canceled: ID=${transactionId}`, 'process.log', 'make-market', 'INFO');
            console.log(`Transaction canceled: ID=${transactionId}`);
            document.getElementById('transaction-status').textContent = 'Canceled';
            document.getElementById('transaction-status').classList.add('text-danger');
            document.getElementById('swap-status').textContent = '';
            document.getElementById('process-result').innerHTML = `
                <div class="alert alert-danger">
                    <strong>Canceled:</strong> Transaction canceled by user
                </div>
            `;
            document.getElementById('process-result').classList.add('active');
            await clearCsrfToken(); // Xóa CSRF token khi giao dịch bị hủy
            const cancelBtn = document.getElementById('cancel-btn');
            if (cancelBtn) {
                cancelBtn.style.display = 'none';
            }
            return;
        } catch (err) {
            log_message(`Failed to cancel transaction: ${err.message}, transactionId=${transactionId}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
            console.error('Failed to cancel transaction:', err.message);
            if (attempt === maxRetries - 1) {
                showError('Failed to cancel transaction: ' + err.message, err.message);
            }
        }
    }
}

// Get network configuration
async function getNetworkConfig() {
    const maxRetries = 3;
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            await ensureAuthInitialized();
            const headers = addAuthHeaders({
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            });
            log_message(`Fetching network config, headers=${JSON.stringify(headers)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            const response = await fetch('/mm/get-network', {
                method: 'GET',
                headers,
                credentials: 'include'
            });
            const responseBody = await response.text();
            log_message(`Response from /mm/get-network: status=${response.status}, headers=${JSON.stringify([...response.headers.entries()])}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
            if (response.status === 401) {
                log_message(`Unauthorized response from /mm/get-network, redirecting to login`, 'process.log', 'make-market', 'ERROR');
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                return;
            }
            if (!response.ok) {
                let result;
                try {
                    result = JSON.parse(responseBody);
                } catch (e) {
                    result = {};
                }
                if (response.status === 403 && result.error === 'Invalid or expired CSRF token') {
                    log_message(`CSRF token invalid or expired, retrying with new token (attempt ${attempt + 1}/${maxRetries})`, 'process.log', 'make-market', 'WARNING');
                    attempt++;
                    if (attempt === maxRetries) {
                        throw new Error(`Failed to fetch network config after ${maxRetries} attempts: ${result.error}`);
                    }
                    await getCsrfToken();
                    continue;
                }
                throw new Error(`HTTP ${response.status}: ${JSON.stringify(result)}`);
            }
            const result = JSON.parse(responseBody);
            if (result.status !== 'success') {
                throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
            }
            if (!result.network || !['mainnet', 'devnet'].includes(result.network)) {
                log_message(`Invalid network in response: ${result.network || 'undefined'}`, 'process.log', 'make-market', 'ERROR');
                throw new Error(`Invalid network: ${result.network || 'undefined'}`);
            }
            if (!result.explorerUrl || !result.explorerQuery) {
                log_message(`Invalid network config: explorerUrl=${result.explorerUrl || 'undefined'}, explorerQuery=${result.explorerQuery || 'undefined'}`, 'process.log', 'make-market', 'ERROR');
                throw new Error(`Invalid network config: missing explorerUrl or explorerQuery`);
            }
            log_message(`Network config fetched successfully: network=${result.network}, explorerUrl=${result.explorerUrl}, explorerQuery=${result.explorerQuery}, jupiterApi=${result.jupiterApi}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
            console.log(`Network config fetched successfully:`, result);
            return result;
        } catch (err) {
            log_message(`Failed to fetch network config: ${err.message}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
            console.error('Failed to fetch network config:', err.message);
            if (attempt === maxRetries - 1) {
                throw err;
            }
            attempt++;
            await delay(1000 * attempt);
        }
    }
}

// Get token decimals from database
async function getTokenDecimals(tokenMint, solanaNetwork) {
    const maxRetries = 3;
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            log_message(`Attempting to get token decimals from database (attempt ${attempt + 1}/${maxRetries}): mint=${tokenMint}, network=${solanaNetwork}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            await ensureAuthInitialized();
            const headers = addAxiosAuthHeaders({
                timeout: 15000,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).headers;
            log_message(`Requesting /mm/get-decimals, headers=${JSON.stringify(headers)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            const response = await axios.post('/mm/get-decimals', {
                tokenMint,
                network: solanaNetwork
            }, {
                headers,
                timeout: 15000,
                withCredentials: true
            });
            log_message(`Response from /mm/get-decimals: status=${response.status}, data=${JSON.stringify(response.data)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            if (response.status !== 200 || !response.data || response.data.status !== 'success') {
                throw new Error(`Invalid response: status=${response.status}, data=${JSON.stringify(response.data)}`);
            }
            const decimals = parseInt(response.data.decimals) || 9;
            log_message(`Token decimals retrieved from database: mint=${tokenMint}, decimals=${decimals}, network=${solanaNetwork}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
            console.log(`Token decimals retrieved from database: mint=${tokenMint}, decimals=${decimals}, network=${solanaNetwork}`);
            return decimals;
        } catch (err) {
            attempt++;
            const errorMessage = err.response
                ? `HTTP ${err.response.status}: ${JSON.stringify(err.response.data)}`
                : `Network Error: ${err.message}, code=${err.code || 'N/A'}, url=${err.config?.url || '/mm/get-decimals'}`;
            log_message(`Failed to get token decimals from database (attempt ${attempt}/${maxRetries}): mint=${tokenMint}, error=${errorMessage}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
            console.error(`Failed to get token decimals from database (attempt ${attempt}/${maxRetries}):`, errorMessage);
            if (err.response?.status === 403 && err.response?.data?.error === 'Invalid or expired CSRF token') {
                log_message(`CSRF token invalid or expired, retrying with new token (attempt ${attempt + 1}/${maxRetries})`, 'process.log', 'make-market', 'WARNING');
                if (attempt === maxRetries) {
                    throw new Error(`Failed to retrieve token decimals after ${maxRetries} attempts: ${errorMessage}`);
                }
                await getCsrfToken();
                continue;
            }
            if (attempt === maxRetries) {
                throw new Error(`Failed to retrieve token decimals after ${maxRetries} attempts: ${errorMessage}`);
            }
            await delay(1000 * attempt);
        }
    }
}

// Get quote from Jupiter API
async function getQuote(inputMint, outputMint, amount, slippageBps, networkConfig) {
    const params = {
        inputMint,
        outputMint,
        amount: Math.floor(amount),
        slippageBps,
        testnet: networkConfig.network === 'devnet' ? true : undefined // Chỉ thêm testnet nếu là devnet
    };
    try {
        log_message(
            `Requesting quote from Jupiter API: url=${networkConfig.jupiterApi}/quote, params=${JSON.stringify(params)}, network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`,
            'process.log', 'make-market', 'DEBUG'
        );
        const response = await axios.get(`${networkConfig.jupiterApi}/quote`, {
            params,
            timeout: 15000,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        log_message(
            `Quote response: url=${networkConfig.jupiterApi}/quote, status=${response.status}, data=${JSON.stringify(response.data)}, inputMint=${inputMint}, outputMint=${outputMint}, amount=${amount / 1e9}, network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`,
            'process.log', 'make-market', 'INFO'
        );
        console.log('Quote retrieved:', response.data);
        return response.data;
    } catch (err) {
        const errorMessage = err.response
            ? `HTTP ${err.response.status}: ${JSON.stringify(err.response.data)}`
            : `Network Error: ${err.message}, code=${err.code || 'N/A'}, url=${err.config?.url || `${networkConfig.jupiterApi}/quote`}`;
        log_message(
            `Failed to get quote: error=${errorMessage}, inputMint=${inputMint}, outputMint=${outputMint}, amount=${amount / 1e9}, slippageBps=${slippageBps}, network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`,
            'process.log', 'make-market', 'ERROR'
        );
        console.error('Failed to get quote:', errorMessage);
        throw new Error(errorMessage);
    }
}

// Get swap transaction
async function getSwapTransaction(quote, publicKey, networkConfig) {
    try {
        const requestBody = {
            quoteResponse: quote,
            userPublicKey: publicKey,
            wrapAndUnwrapSol: true,
            dynamicComputeUnitLimit: true,
            prioritizationFeeLamports: networkConfig.prioritizationFeeLamports,
            testnet: networkConfig.network === 'devnet'
        };
        log_message(`Requesting swap transaction from Jupiter API, body=${JSON.stringify(requestBody)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
        const response = await axios.post(`${networkConfig.jupiterApi}/swap`, requestBody, {
            timeout: 15000,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        log_message(`Response from ${networkConfig.jupiterApi}/swap: status=${response.status}, data=${JSON.stringify(response.data)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
        if (response.status !== 200 || !response.data) {
            throw new Error('Failed to prepare swap transaction from Jupiter API');
        }
        const { swapTransaction } = response.data;
        log_message(`Swap transaction prepared: ${swapTransaction.substring(0, 20)}..., network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
        console.log('Swap transaction prepared:', swapTransaction);
        return swapTransaction;
    } catch (err) {
        const errorMessage = err.response
            ? `HTTP ${err.response.status}: ${JSON.stringify(err.response.data)}`
            : `Network Error: ${err.message}, code=${err.code || 'N/A'}, url=${err.config?.url || `${networkConfig.jupiterApi}/swap`}`;
        log_message(`Swap transaction failed: ${errorMessage}, network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
        console.error('Swap transaction failed:', errorMessage);
        throw new Error(errorMessage);
    }
}

// Create sub-transaction records
async function createSubTransactions(transactionId, loopCount, batchSize, tradeDirection, solanaNetwork) {
    const maxRetries = 3;
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            await ensureAuthInitialized();
            const totalTransactions = tradeDirection === 'both' ? loopCount * batchSize * 2 : loopCount * batchSize;
            const subTransactions = [];
            for (let loop = 1; loop <= loopCount; loop++) {
                for (let batchIndex = 0; batchIndex < batchSize; batchIndex++) {
                    if (tradeDirection === 'buy' || tradeDirection === 'both') {
                        subTransactions.push({ loop, batch_index: batchIndex, direction: 'buy' });
                    }
                    if (tradeDirection === 'sell' || tradeDirection === 'both') {
                        subTransactions.push({ loop, batch_index: batchIndex, direction: 'sell' });
                    }
                }
            }
            const headers = addAuthHeaders({
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            });
            log_message(`Creating sub-transactions: ID=${transactionId}, total=${totalTransactions}, headers=${JSON.stringify(headers)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            const response = await fetch(`/mm/create-tx/${transactionId}`, {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    id: transactionId, // Thêm id vào payload
                    sub_transactions: subTransactions,
                    network: solanaNetwork
                }),
                credentials: 'include'
            });
            const responseBody = await response.text();
            log_message(`Response from /mm/create-tx/${transactionId}: status=${response.status}, headers=${JSON.stringify([...response.headers.entries()])}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
            if (!response.ok) {
                let result;
                try {
                    result = JSON.parse(responseBody);
                } catch (e) {
                    result = {};
                }
                if (response.status === 401) {
                    window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                if (response.status === 403 && result.error === 'Invalid or expired CSRF token') {
                    log_message(`CSRF token invalid or expired, retrying with new token (attempt ${attempt + 1}/${maxRetries})`, 'process.log', 'make-market', 'WARNING');
                    attempt++;
                    if (attempt === maxRetries) {
                        throw new Error(`Failed to create sub-transactions after ${maxRetries} attempts: ${result.error}`);
                    }
                    await getCsrfToken();
                    continue;
                }
                throw new Error(`HTTP ${response.status}: ${JSON.stringify(result)}`);
            }
            const result = JSON.parse(responseBody);
            if (result.status !== 'success') {
                throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
            }
            log_message(`Created ${totalTransactions} sub-transactions for transaction ID=${transactionId}, IDs: ${result.sub_transaction_ids.join(',')}, network=${solanaNetwork}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
            console.log(`Created ${totalTransactions} sub-transactions:`, result.sub_transaction_ids);
            return result.sub_transaction_ids;
        } catch (err) {
            log_message(`Failed to create sub-transactions: ${err.message}, transactionId=${transactionId}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
            console.error('Failed to create sub-transactions:', err.message);
            if (attempt === maxRetries - 1) {
                throw err;
            }
            attempt++;
            await delay(1000 * attempt);
        }
    }
}

// Execute swap transactions
async function executeSwapTransactions(transactionId, swapTransactions, subTransactionIds, solanaNetwork) {
    const maxRetries = 3;
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            await ensureAuthInitialized();
            const headers = addAuthHeaders({
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            });
            log_message(`Executing swap transactions: ID=${transactionId}, headers=${JSON.stringify(headers)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            const response = await fetch('/mm/swap-jupiter', {
                method: 'POST',
                headers,
                body: JSON.stringify({ id: transactionId, swap_transactions: swapTransactions, sub_transaction_ids: subTransactionIds, network: solanaNetwork }),
                credentials: 'include'
            });
            const responseBody = await response.text();
            log_message(`Response from /mm/swap-jupiter: status=${response.status}, headers=${JSON.stringify([...response.headers.entries()])}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
            if (!response.ok) {
                let result;
                try {
                    result = JSON.parse(responseBody);
                } catch (e) {
                    result = {};
                }
                if (response.status === 401) {
                    window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                if (response.status === 403 && result.error === 'Invalid or expired CSRF token') {
                    log_message(`CSRF token invalid or expired, retrying with new token (attempt ${attempt + 1}/${maxRetries})`, 'process.log', 'make-market', 'WARNING');
                    attempt++;
                    if (attempt === maxRetries) {
                        throw new Error(`Failed to execute swap transactions after ${maxRetries} attempts: ${result.error}`);
                    }
                    await getCsrfToken();
                    continue;
                }
                throw new Error(`HTTP ${response.status}: ${JSON.stringify(result)}`);
            }
            const result = JSON.parse(responseBody);
            if (result.status !== 'success' && result.status !== 'partial') {
                throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
            }
            log_message(`Swap transactions executed: status=${result.status}, network=${solanaNetwork}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
            console.log(`Swap transactions executed:`, result);
            return result;
        } catch (err) {
            log_message(`Swap execution failed: ${err.message}, transactionId=${transactionId}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
            console.error('Swap execution failed:', err.message);
            if (attempt === maxRetries - 1) {
                throw err;
            }
            attempt++;
            await delay(1000 * attempt);
        }
    }
}

// Main process
document.addEventListener('DOMContentLoaded', async () => {
    log_message(`process.js loaded, cookies=${document.cookie}, csrf_token=${window.CSRF_TOKEN ? window.CSRF_TOKEN.substring(0, 4) + '...' : 'none'}`, 'process.log', 'make-market', 'DEBUG');
    console.log('process.js loaded');

    // Initialize authentication
    let networkConfig;
    try {
        await ensureAuthInitialized();
        networkConfig = await getNetworkConfig();
    } catch (err) {
        await showError('Failed to initialize authentication or network config: ' + err.message, err.message);
        return;
    }

    // Warn if on mainnet
    if (networkConfig.network === 'mainnet') {
        alert('Warning: You are on Solana Mainnet. Transactions will use real funds.');
    }

    // Main process
    const transactionId = new URLSearchParams(window.location.search).get('id') || window.location.pathname.split('/').pop();
    if (!transactionId || isNaN(transactionId)) {
        await showError('Invalid transaction ID');
        return;
    }

    // Fetch transaction details
    let transaction, publicKey;
    const maxRetries = 3;
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            await ensureAuthInitialized();
            const headers = addAuthHeaders({
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            });
            log_message(`Fetching transaction: ID=${transactionId}, headers=${JSON.stringify(headers)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            const response = await fetch(`/mm/get-tx/${transactionId}`, {
                headers,
                credentials: 'include'
            });
            const responseBody = await response.text();
            log_message(`Response from /mm/get-tx/${transactionId}: status=${response.status}, headers=${JSON.stringify([...response.headers.entries()])}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
            if (response.status === 401) {
                log_message(`Unauthorized response from /mm/get-tx/${transactionId}, redirecting to login`, 'process.log', 'make-market', 'ERROR');
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                return;
            }
            if (!response.ok) {
                let result;
                try {
                    result = JSON.parse(responseBody);
                } catch (e) {
                    result = {};
                }
                if (response.status === 403 && result.error === 'Invalid or expired CSRF token') {
                    log_message(`CSRF token invalid or expired, retrying with new token (attempt ${attempt + 1}/${maxRetries})`, 'process.log', 'make-market', 'WARNING');
                    attempt++;
                    if (attempt === maxRetries) {
                        throw new Error(`Failed to fetch transaction after ${maxRetries} attempts: ${result.error}`);
                    }
                    await getCsrfToken();
                    continue;
                }
                throw new Error(`HTTP ${response.status}: ${JSON.stringify(result)}`);
            }
            const result = JSON.parse(responseBody);
            if (result.status !== 'success') {
                throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
            }
            transaction = result.data;
            publicKey = transaction.public_key;
            transaction.loop_count = parseInt(transaction.loop_count) || 1;
            transaction.batch_size = parseInt(transaction.batch_size) || 1;
            transaction.slippage = parseFloat(transaction.slippage) || 0.5;
            transaction.delay_seconds = parseInt(transaction.delay_seconds) || 1;
            transaction.sol_amount = parseFloat(transaction.sol_amount) || 0;
            transaction.token_amount = parseFloat(transaction.token_amount) || 0;
            transaction.trade_direction = transaction.trade_direction || 'buy';
            log_message(`Transaction fetched: ID=${transactionId}, token_mint=${transaction.token_mint}, public_key=${publicKey}, sol_amount=${transaction.sol_amount}, token_amount=${transaction.token_amount}, trade_direction=${transaction.trade_direction}, loop_count=${transaction.loop_count}, batch_size=${transaction.batch_size}, slippage=${transaction.slippage}, delay_seconds=${transaction.delay_seconds}, status=${transaction.status}, network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}, csrf_token=${window.CSRF_TOKEN ? window.CSRF_TOKEN.substring(0, 4) + '...' : 'none'}`, 'process.log', 'make-market', 'INFO');
            console.log('Transaction fetched:', transaction);
            break;
        } catch (err) {
            log_message(`Failed to fetch transaction: ${err.message}, transactionId=${transactionId}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
            console.error('Failed to fetch transaction:', err.message);
            if (attempt === maxRetries - 1) {
                await showError('Failed to retrieve transaction info: ' + err.message, err.message);
                return;
            }
            attempt++;
            await delay(1000 * attempt);
        }
    }

    // Validate transaction parameters
    const loopCount = transaction.loop_count;
    const batchSize = transaction.batch_size;
    const tradeDirection = transaction.trade_direction;
    if (isNaN(loopCount) || isNaN(batchSize) || loopCount <= 0 || batchSize <= 0) {
        await showError('Invalid transaction parameters: loop_count or batch_size is invalid');
        return;
    }
    if (!['buy', 'sell', 'both'].includes(tradeDirection)) {
        await showError('Invalid trade direction: ' + tradeDirection);
        return;
    }
    if (transaction.sol_amount <= 0 && (tradeDirection === 'buy' || tradeDirection === 'both')) {
        await showError('SOL amount must be greater than 0 for buy or both transactions');
        return;
    }
    if (transaction.token_amount <= 0 && (tradeDirection === 'sell' || tradeDirection === 'both')) {
        await showError('Token amount must be greater than 0 for sell or both transactions');
        return;
    }

    // Check initial status
    if (!['new', 'pending', 'processing'].includes(transaction.status)) {
        const cancelBtn = document.getElementById('cancel-btn');
        if (cancelBtn) {
            cancelBtn.style.display = 'none';
        }
    }

    // Update status to pending
    await updateTransactionStatus('pending');

    // Fetch token decimals
    let tokenDecimals;
    try {
        tokenDecimals = await getTokenDecimals(transaction.token_mint, networkConfig.network);
    } catch (err) {
        await showError('Failed to retrieve token decimals: ' + err.message, err.message);
        return;
    }

    // Create sub-transaction records
    let subTransactionIds;
    let subTransactionIndex = 0;
    try {
        subTransactionIds = await createSubTransactions(transactionId, loopCount, batchSize, tradeDirection, networkConfig.network);
    } catch (err) {
        await showError('Failed to create sub-transactions: ' + err.message, err.message);
        return;
    }

    // Process swaps
    try {
        const solMint = networkConfig.solMint;
        const solAmount = transaction.sol_amount * 1e9;
        const tokenAmount = transaction.token_amount * Math.pow(10, tokenDecimals);
        const slippageBps = Math.floor(transaction.slippage * 100);
        const delaySeconds = transaction.delay_seconds * 1000;
        const swapTransactions = [];

        for (let loop = 1; loop <= loopCount; loop++) {
            document.getElementById('swap-status').textContent = `Preparing loop ${loop} of ${loopCount} on ${networkConfig.network}...`;
            for (let i = 0; i < batchSize; i++) {
                if (tradeDirection === 'buy' || tradeDirection === 'both') {
                    document.getElementById('swap-status').textContent = `Retrieving buy quote for loop ${loop}, batch ${i + 1} on ${networkConfig.network}...`;
                    const buyQuote = await getQuote(solMint, transaction.token_mint, solAmount, slippageBps, networkConfig);
                    const buyTx = await getSwapTransaction(buyQuote, publicKey, networkConfig);
                    swapTransactions.push({ direction: 'buy', tx: buyTx, sub_transaction_id: subTransactionIds[subTransactionIndex++], loop, batch_index: i });
                    if (i < batchSize - 1 || tradeDirection === 'both') {
                        document.getElementById('swap-status').textContent = `Waiting ${transaction.delay_seconds} seconds before next ${tradeDirection === 'both' ? 'buy/sell' : 'batch'} in loop ${loop}...`;
                        await delay(delaySeconds);
                    }
                }
                if (tradeDirection === 'sell' || tradeDirection === 'both') {
                    document.getElementById('swap-status').textContent = `Retrieving sell quote for loop ${loop}, batch ${i + 1} on ${networkConfig.network}...`;
                    const sellQuote = await getQuote(transaction.token_mint, solMint, tokenAmount, slippageBps, networkConfig);
                    const sellTx = await getSwapTransaction(sellQuote, publicKey, networkConfig);
                    swapTransactions.push({ direction: 'sell', tx: sellTx, sub_transaction_id: subTransactionIds[subTransactionIndex++], loop, batch_index: i });
                    if (i < batchSize - 1) {
                        document.getElementById('swap-status').textContent = `Waiting ${transaction.delay_seconds} seconds before next batch in loop ${loop}...`;
                        await delay(delaySeconds);
                    }
                }
            }
            if (loop < loopCount) {
                document.getElementById('swap-status').textContent = `Waiting ${transaction.delay_seconds} seconds before next loop on ${networkConfig.network}...`;
                await delay(delaySeconds);
            }
        }

        document.getElementById('swap-status').textContent = `Executing swap transactions on ${networkConfig.network}...`;
        const swapResult = await executeSwapTransactions(transactionId, swapTransactions, subTransactionIds, networkConfig.network);
        const successCount = swapResult.results.filter(r => r.status === 'success').length;
        const totalTransactions = swapTransactions.length;
        await updateTransactionStatus(successCount === totalTransactions ? 'success' : 'partial', `Completed ${successCount} of ${totalTransactions} transactions on ${networkConfig.network}`);
        await showSuccess(`Completed ${successCount} of ${totalTransactions} transactions on ${networkConfig.network}`, swapResult.results, networkConfig);
    } catch (err) {
        await showError('Error during swap process: ' + err.message, err.message);
    }
});
