// ============================================================================
// File: mm/process/process.js
// Description: JavaScript for processing Solana token swap with looping using Jupiter Aggregator API
// Created by: Vina Network
// ============================================================================

// Hàm lấy CSRF token từ endpoint /mm/get-csrf
async function getCsrfToken(maxRetries = 3, retryDelay = 1000) {
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            log_message(`Attempting to fetch CSRF token from /mm/get-csrf (attempt ${attempt + 1}/${maxRetries}), cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
            const response = await fetch('/mm/get-csrf', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });
            const responseBody = await response.text();
            log_message(`Response from /mm/get-csrf: status=${response.status}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${responseBody}`);
            }
            const result = JSON.parse(responseBody);
            if (result.status !== 'success' || !result.csrfToken) {
                throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
            }
            window.CSRF_TOKEN = result.csrfToken;
            log_message(`CSRF token retrieved: ${window.CSRF_TOKEN}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
            console.log(`CSRF token retrieved: ${window.CSRF_TOKEN}`);
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

// Initialize authentication and CSRF token
async function ensureAuthInitialized(maxRetries = 3, retryDelay = 1000) {
    if (window.CSRF_TOKEN) {
        log_message(`Using existing CSRF token: ${window.CSRF_TOKEN}`, 'process.log', 'make-market', 'INFO');
        return window.CSRF_TOKEN;
    }
    return await getCsrfToken(maxRetries, retryDelay);
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
    const csrfToken = window.CSRF_TOKEN || 'none';
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
function showError(message, detailedError = null) {
    const resultDiv = document.getElementById('process-result');
    resultDiv.innerHTML = `
        <div class="alert alert-danger">
            <strong>Error:</strong> ${message}
            ${detailedError ? `<br>Details: ${detailedError}` : ''}
        </div>
    `;
    resultDiv.classList.add('active');
    document.getElementById('swap-status').textContent = '';
    document.getElementById('transaction-status').textContent = 'Failed';
    document.getElementById('transaction-status').classList.add('text-danger');
    log_message(`Process stopped: ${message}${detailedError ? `, Details: ${detailedError}` : ''}`, 'process.log', 'make-market', 'ERROR');
    console.error(`Process stopped: ${message}${detailedError ? `, Details: ${detailedError}` : ''}`);
    updateTransactionStatus('failed', detailedError || message);
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Show success message
function showSuccess(message, results = [], networkConfig) {
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
                    ? `<a href="${networkConfig.config.explorerUrl}${result.txid}${networkConfig.config.explorerQuery}" target="_blank">Success (txid: ${result.txid})</a>`
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
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Update transaction status
async function updateTransactionStatus(status, error = null) {
    const transactionId = new URLSearchParams(window.location.search).get('id') || window.location.pathname.split('/').pop();
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
            throw new Error(`HTTP ${response.status}: ${JSON.stringify(result)}`);
        }
        const result = JSON.parse(responseBody);
        if (result.status !== 'success') {
            throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
        }
        log_message(`Transaction status updated: ID=${transactionId}, status=${status}, error=${error || 'none'}`, 'process.log', 'make-market', 'INFO');
        console.log(`Transaction status updated: ID=${transactionId}, status=${status}, error=${error || 'none'}`);
    } catch (err) {
        log_message(`Failed to update transaction status: ${err.message}, transactionId=${transactionId}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
        console.error('Failed to update transaction status:', err.message);
    }
}

// Cancel transaction
async function cancelTransaction(transactionId) {
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
        const cancelBtn = document.getElementById('cancel-btn');
        if (cancelBtn) {
            cancelBtn.style.display = 'none';
        }
    } catch (err) {
        log_message(`Failed to cancel transaction: ${err.message}, transactionId=${transactionId}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
        showError('Failed to cancel transaction: ' + err.message, err.message);
    }
}

// Get network configuration
async function getNetworkConfig() {
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
        let result;
        try {
            result = JSON.parse(responseBody);
        } catch (e) {
            log_message(`Failed to parse JSON from /mm/get-network: ${e.message}, status=${response.status}, response_body=${responseBody}`, 'process.log', 'make-market', 'ERROR');
            throw new Error(`Invalid JSON response: ${e.message}`);
        }
        if (!response.ok) {
            log_message(`HTTP error from /mm/get-network: status=${response.status}, response=${JSON.stringify(result)}`, 'process.log', 'make-market', 'ERROR');
            throw new Error(`HTTP ${response.status}: ${JSON.stringify(result)}`);
        }
        if (result.status !== 'success') {
            log_message(`Invalid response from /mm/get-network: ${JSON.stringify(result)}`, 'process.log', 'make-market', 'ERROR');
            throw new Error(result.message || `Invalid response: ${JSON.stringify(result)}`);
        }
        if (!result.network || !['testnet', 'mainnet', 'devnet'].includes(result.network)) {
            log_message(`Invalid network in response: ${result.network || 'undefined'}`, 'process.log', 'make-market', 'ERROR');
            throw new Error(`Invalid network: ${result.network || 'undefined'}`);
        }
        log_message(`Network config fetched successfully: network=${result.network}, explorerUrl=${result.config.explorerUrl}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
        console.log(`Network config fetched successfully:`, result);
        return result;
    } catch (err) {
        log_message(`Failed to fetch network config: ${err.message}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
        console.error('Failed to fetch network config:', err.message);
        throw err;
    }
}

// Get token decimals
async function getTokenDecimals(tokenMint, heliusApiKey, solanaNetwork) {
    const maxRetries = 3;
    let attempt = 0;
    while (attempt < maxRetries) {
        try {
            log_message(`Attempting to get token decimals from server (attempt ${attempt + 1}/${maxRetries}): mint=${tokenMint}, network=${solanaNetwork}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
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
            const decimals = response.data.decimals || 9;
            log_message(`Token decimals retrieved from server: mint=${tokenMint}, decimals=${decimals}, network=${solanaNetwork}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
            console.log(`Token decimals retrieved from server: mint=${tokenMint}, decimals=${decimals}, network=${solanaNetwork}`);
            return decimals;
        } catch (err) {
            attempt++;
            const errorMessage = err.response
                ? `HTTP ${err.response.status}: ${JSON.stringify(err.response.data)}`
                : `Network Error: ${err.message}, code=${err.code || 'N/A'}, url=${err.config?.url || '/mm/get-decimals'}`;
            log_message(`Failed to get token decimals from server (attempt ${attempt}/${maxRetries}): mint=${tokenMint}, error=${errorMessage}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
            console.error(`Failed to get token decimals from server (attempt ${attempt}/${maxRetries}):`, errorMessage);
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
        slippageBps
    };
    if (networkConfig.network === 'testnet' || networkConfig.network === 'devnet') {
        params.testnet = true;
    }
    try {
        log_message(`Requesting quote from Jupiter API: input=${inputMint}, output=${outputMint}, amount=${amount / 1e9}, params=${JSON.stringify(params)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
        const response = await axios.get(`${networkConfig.config.jupiterApi}/quote`, {
            params,
            timeout: 15000,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        log_message(`Response from ${networkConfig.config.jupiterApi}/quote: status=${response.status}, data=${JSON.stringify(response.data)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
        if (response.status !== 200 || !response.data) {
            throw new Error('Failed to retrieve quote from Jupiter API');
        }
        log_message(`Quote retrieved: input=${inputMint}, output=${outputMint}, amount=${amount / 1e9}, network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
        console.log('Quote retrieved:', response.data);
        return response.data;
    } catch (err) {
        const errorMessage = err.response
            ? `HTTP ${err.response.status}: ${JSON.stringify(err.response.data)}`
            : `Network Error: ${err.message}, code=${err.code || 'N/A'}, url=${err.config?.url || `${networkConfig.config.jupiterApi}/quote`}`;
        log_message(`Failed to get quote: ${errorMessage}, input=${inputMint}, output=${outputMint}, amount=${amount / 1e9}, network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
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
            prioritizationFeeLamports: networkConfig.config.prioritizationFeeLamports,
            testnet: networkConfig.network === 'testnet' || networkConfig.network === 'devnet'
        };
        log_message(`Requesting swap transaction from Jupiter API, body=${JSON.stringify(requestBody)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
        const response = await axios.post(`${networkConfig.config.jupiterApi}/swap`, requestBody, {
            timeout: 15000,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        log_message(`Response from ${networkConfig.config.jupiterApi}/swap: status=${response.status}, data=${JSON.stringify(response.data)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
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
            : `Network Error: ${err.message}, code=${err.code || 'N/A'}, url=${err.config?.url || `${networkConfig.config.jupiterApi}/swap`}`;
        log_message(`Swap transaction failed: ${errorMessage}, network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'ERROR');
        console.error('Swap transaction failed:', errorMessage);
        throw new Error(errorMessage);
    }
}

// Create sub-transaction records
async function createSubTransactions(transactionId, loopCount, batchSize, tradeDirection, solanaNetwork) {
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
            body: JSON.stringify({ sub_transactions: subTransactions, network: solanaNetwork }),
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
        throw err;
    }
}

// Execute swap transactions
async function executeSwapTransactions(transactionId, swapTransactions, subTransactionIds, solanaNetwork) {
    try {
        await ensureAuthInitialized();
        const headers = addAuthHeaders({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        });
        log_message(`Executing swap transactions: ID=${transactionId}, headers=${JSON.stringify(headers)}, cookies=${document.cookie}`, 'process.log', 'make-market', 'DEBUG');
        const response = await fetch('/mm/swap', {
            method: 'POST',
            headers,
            body: JSON.stringify({ id: transactionId, swap_transactions: swapTransactions, sub_transaction_ids: subTransactionIds, network: solanaNetwork }),
            credentials: 'include'
        });
        const responseBody = await response.text();
        log_message(`Response from /mm/swap: status=${response.status}, headers=${JSON.stringify([...response.headers.entries()])}, response_body=${responseBody}`, 'process.log', 'make-market', 'DEBUG');
        if (!response.ok) {
            let result;
            try {
                result = JSON.parse(responseBody);
            } catch (e) {
                result = {};
            }
            if (result.error === 'Invalid CSRF token') {
                log_message(`CSRF validation failed for /mm/swap, attempting to refresh token`, 'process.log', 'make-market', 'WARNING');
                await ensureAuthInitialized();
                headers['X-CSRF-Token'] = window.CSRF_TOKEN || '';
                const retryResponse = await fetch('/mm/swap', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ id: transactionId, swap_transactions: swapTransactions, sub_transaction_ids: subTransactionIds, network: solanaNetwork }),
                    credentials: 'include'
                });
                const retryResponseBody = await retryResponse.text();
                log_message(`Response from /mm/swap (retry): status=${retryResponse.status}, headers=${JSON.stringify([...retryResponse.headers.entries()])}, response_body=${retryResponseBody}`, 'process.log', 'make-market', 'DEBUG');
                if (!retryResponse.ok) {
                    let retryResult;
                    try {
                        retryResult = JSON.parse(retryResponseBody);
                    } catch (e) {
                        retryResult = {};
                    }
                    if (retryResponse.status === 401) {
                        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                        return;
                    }
                    throw new Error(`Retry HTTP ${retryResponse.status}: ${JSON.stringify(retryResult)}`);
                }
                const retryResult = JSON.parse(retryResponseBody);
                log_message(`Retry swap transactions executed: status=${retryResult.status}, network=${solanaNetwork}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}`, 'process.log', 'make-market', 'INFO');
                return retryResult;
            }
            if (response.status === 401) {
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                return;
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
        throw err;
    }
}

// Main process
document.addEventListener('DOMContentLoaded', async () => {
    log_message(`process.js loaded, cookies=${document.cookie}, csrf_token=${window.CSRF_TOKEN || 'none'}`, 'process.log', 'make-market', 'DEBUG');
    console.log('process.js loaded');

    // Initialize authentication
    let networkConfig;
    try {
        await ensureAuthInitialized();
        networkConfig = await getNetworkConfig();
    } catch (err) {
        showError('Failed to initialize authentication or network config: ' + err.message, err.message);
        return;
    }

    // Warn if on mainnet
    if (networkConfig.network === 'mainnet') {
        alert('Warning: You are on Solana Mainnet. Transactions will use real funds.');
    }

    // Main process
    const transactionId = new URLSearchParams(window.location.search).get('id') || window.location.pathname.split('/').pop();
    if (!transactionId || isNaN(transactionId)) {
        showError('Invalid transaction ID');
        return;
    }

    // Fetch transaction details
    let transaction, publicKey;
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
        log_message(`Transaction fetched: ID=${transactionId}, token_mint=${transaction.token_mint}, public_key=${publicKey}, sol_amount=${transaction.sol_amount}, token_amount=${transaction.token_amount}, trade_direction=${transaction.trade_direction}, loop_count=${transaction.loop_count}, batch_size=${transaction.batch_size}, slippage=${transaction.slippage}, delay_seconds=${transaction.delay_seconds}, status=${transaction.status}, network=${networkConfig.network}, session_id=${document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || 'none'}, csrf_token=${window.CSRF_TOKEN || 'none'}`, 'process.log', 'make-market', 'INFO');
        console.log('Transaction fetched:', transaction);
    } catch (err) {
        showError('Failed to retrieve transaction info: ' + err.message, err.message);
        return;
    }

    // Validate transaction parameters
    const loopCount = transaction.loop_count;
    const batchSize = transaction.batch_size;
    const tradeDirection = transaction.trade_direction;
    if (isNaN(loopCount) || isNaN(batchSize) || loopCount <= 0 || batchSize <= 0) {
        showError('Invalid transaction parameters: loop_count or batch_size is invalid');
        return;
    }
    if (!['buy', 'sell', 'both'].includes(tradeDirection)) {
        showError('Invalid trade direction: ' + tradeDirection);
        return;
    }
    if (transaction.sol_amount <= 0 && (tradeDirection === 'buy' || tradeDirection === 'both')) {
        showError('SOL amount must be greater than 0 for buy or both transactions');
        return;
    }
    if (transaction.token_amount <= 0 && (tradeDirection === 'sell' || tradeDirection === 'both')) {
        showError('Token amount must be greater than 0 for sell or both transactions');
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
        tokenDecimals = await getTokenDecimals(transaction.token_mint, null, networkConfig.network);
    } catch (err) {
        showError('Failed to retrieve token decimals: ' + err.message, err.message);
        return;
    }

    // Create sub-transaction records
    let subTransactionIds;
    let subTransactionIndex = 0;
    try {
        subTransactionIds = await createSubTransactions(transactionId, loopCount, batchSize, tradeDirection, networkConfig.network);
    } catch (err) {
        showError('Failed to create sub-transactions: ' + err.message, err.message);
        return;
    }

    // Process swaps
    try {
        const solMint = networkConfig.config.solMint;
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
        showSuccess(`Completed ${successCount} of ${totalTransactions} transactions on ${networkConfig.network}`, swapResult.results, networkConfig);
    } catch (err) {
        showError('Error during swap process: ' + err.message, err.message);
    }
});
