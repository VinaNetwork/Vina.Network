// ============================================================================
// File: make-market/process.js
// Description: Process page for displaying transaction progress and validation results
// Created by: Vina Network
// ============================================================================

const transactionId = TRANSACTION_ID;
const loopCount = LOOP_COUNT;
let currentLoop = 0;

// Log message function
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
    fetch('/make-market/log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, log_file, module, log_type })
    }).catch(err => console.error('Log error:', err));
}

// Perform pre-transaction checks
async function performChecks() {
    const checkBalance = document.getElementById('check-balance').querySelector('span');
    const checkToken = document.getElementById('check-token').querySelector('span');
    const checkLiquidity = document.getElementById('check-liquidity').querySelector('span');
    const checkPrivateKey = document.getElementById('check-private-key').querySelector('span');
    const checkError = document.getElementById('check-error');
    let allChecksPassed = true;
    let errorMessages = [];

    // 1. Check private key
    try {
        log_message(`Checking private key for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        const privateKeyResponse = await fetch('/make-market/check-private-key.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ transaction_id: transactionId })
        });
        if (!privateKeyResponse.ok) {
            const errorText = await privateKeyResponse.text();
            log_message(`Private key check failed: HTTP ${privateKeyResponse.status}, Response: ${errorText} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
            checkPrivateKey.textContent = `Failed (HTTP ${privateKeyResponse.status})`;
            checkPrivateKey.classList.add('error');
            errorMessages.push(`Private key check failed: HTTP ${privateKeyResponse.status}, Response: ${errorText}`);
            allChecksPassed = false;
        } else {
            const privateKeyData = await privateKeyResponse.json();
            if (privateKeyData.status === 'success') {
                checkPrivateKey.textContent = 'Done';
                checkPrivateKey.classList.add('done');
                log_message(`Private key check passed for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
            } else {
                checkPrivateKey.textContent = 'Invalid';
                checkPrivateKey.classList.add('error');
                errorMessages.push(`Invalid private key: ${privateKeyData.message}`);
                log_message(`Private key check failed: ${privateKeyData.message} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
                allChecksPassed = false;
            }
        }
    } catch (error) {
        log_message(`Error checking private key for transaction ID ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        checkPrivateKey.textContent = 'Failed';
        checkPrivateKey.classList.add('error');
        errorMessages.push(`Private key check error: ${error.message}`);
        allChecksPassed = false;
    }

    // 2. Check wallet balance
    try {
        log_message(`Sending balance check request to /make-market/get-balance.php for public_key ${PUBLIC_KEY} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        const balanceResponse = await fetch('/make-market/get-balance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ public_key: PUBLIC_KEY })
        });
        const responseText = await balanceResponse.text();
        log_message(`Balance check response: HTTP ${balanceResponse.status}, Response: ${responseText} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        if (!balanceResponse.ok) {
            log_message(`Balance check failed: HTTP ${balanceResponse.status}, Response: ${responseText} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
            checkBalance.textContent = `Failed (HTTP ${balanceResponse.status})`;
            checkBalance.classList.add('error');
            errorMessages.push(`Balance check failed: HTTP ${balanceResponse.status}, Response: ${responseText}`);
            allChecksPassed = false;
        } else {
            const balanceData = JSON.parse(responseText);
            if (balanceData.status === 'success' && typeof balanceData.balance === 'number') {
                const requiredSol = SOL_AMOUNT;
                if (balanceData.balance >= requiredSol) {
                    checkBalance.textContent = `Done (${balanceData.balance} SOL)`;
                    checkBalance.classList.add('done');
                    log_message(`Balance check passed: ${balanceData.balance} SOL available, required: ${requiredSol} SOL for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
                } else {
                    checkBalance.textContent = `Insufficient (${balanceData.balance} SOL)`;
                    checkBalance.classList.add('error');
                    errorMessages.push(`Insufficient balance: ${balanceData.balance} SOL, required: ${requiredSol} SOL`);
                    log_message(`Balance check failed: Insufficient balance: ${balanceData.balance} SOL, required: ${requiredSol} SOL for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
                    allChecksPassed = false;
                }
            } else {
                const errorMsg = balanceData.status === 'error' ? balanceData.message : 'Invalid balance response';
                checkBalance.textContent = 'Failed';
                checkBalance.classList.add('error');
                errorMessages.push(`Balance check failed: ${errorMsg}`);
                log_message(`Balance check failed: ${errorMsg} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
                allChecksPassed = false;
            }
        }
    } catch (error) {
        log_message(`Error checking balance for transaction ID ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        checkBalance.textContent = 'Failed';
        checkBalance.classList.add('error');
        errorMessages.push(`Balance check error: ${error.message}`);
        allChecksPassed = false;
    }

    // 3. Check token mint using mm-api.php
    try {
        log_message(`Checking token mint ${TOKEN_MINT} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        const tokenResponse = await fetch('/make-market/mm-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                endpoint: 'getAccountInfo',
                params: [TOKEN_MINT, { encoding: 'jsonParsed' }]
            })
        });
        const responseText = await tokenResponse.text();
        log_message(`Token mint check response: HTTP ${tokenResponse.status}, Response: ${responseText} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        if (!tokenResponse.ok) {
            const errorMsg = tokenResponse.status === 401 
                ? 'Unauthorized: Invalid or expired Helius API key'
                : `HTTP ${tokenResponse.status}, Response: ${responseText}`;
            log_message(`Token mint check failed: ${errorMsg} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
            checkToken.textContent = `Failed (HTTP ${tokenResponse.status})`;
            checkToken.classList.add('error');
            errorMessages.push(`Token mint check failed: ${errorMsg}`);
            allChecksPassed = false;
        } else {
            const tokenData = JSON.parse(responseText);
            if (tokenData.status === 'error') {
                log_message(`Token mint check failed: ${tokenData.message} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
                checkToken.textContent = 'Failed';
                checkToken.classList.add('error');
                errorMessages.push(`Token mint check failed: ${tokenData.message}`);
                allChecksPassed = false;
            } else if (tokenData.result && tokenData.result.result && tokenData.result.result.value && tokenData.result.result.value.data && tokenData.result.result.value.data.program === 'spl-token' && tokenData.result.result.value.data.parsed.type === 'mint') {
                checkToken.textContent = 'Done';
                checkToken.classList.add('done');
                log_message(`Token mint check passed: ${TOKEN_MINT} is a valid SPL token mint for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
            } else {
                checkToken.textContent = 'Does not exist';
                checkToken.classList.add('error');
                errorMessages.push(`Invalid token mint: Not a valid SPL token mint, Response: ${responseText}`);
                log_message(`Token mint check failed: Invalid token mint ${TOKEN_MINT} (not an SPL token mint) for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
                allChecksPassed = false;
            }
        }
    } catch (error) {
        log_message(`Error checking token mint for transaction ID ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        checkToken.textContent = 'Failed';
        checkToken.classList.add('error');
        errorMessages.push(`Token mint check error: ${error.message}`);
        allChecksPassed = false;
    }

    // 4. Check liquidity
    try {
        log_message(`Checking liquidity for token ${TOKEN_MINT} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        const liquidityResponse = await fetch(`https://quote-api.jup.ag/v6/quote?inputMint=So11111111111111111111111111111111111111112&outputMint=${encodeURIComponent(TOKEN_MINT)}&amount=${SOL_AMOUNT * 1e9}&slippageBps=${SLIPPAGE * 100}`, {
            headers: { 'Accept': 'application/json' }
        });
        const responseText = await liquidityResponse.text();
        log_message(`Liquidity check response: HTTP ${liquidityResponse.status}, Response: ${responseText} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        if (!liquidityResponse.ok) {
            const errorMsg = liquidityResponse.status === 400 
                ? 'Invalid request or insufficient liquidity'
                : `HTTP ${liquidityResponse.status}, Response: ${responseText}`;
            log_message(`Liquidity check failed: ${errorMsg} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
            checkLiquidity.textContent = `Failed (HTTP ${liquidityResponse.status})`;
            checkLiquidity.classList.add('error');
            errorMessages.push(`Liquidity check failed: ${errorMsg}`);
            allChecksPassed = false;
        } else {
            const liquidityData = JSON.parse(responseText);
            if (liquidityData.outAmount && parseInt(liquidityData.outAmount) > 0) {
                checkLiquidity.textContent = 'Done';
                checkLiquidity.classList.add('done');
                log_message(`Liquidity check passed for token: ${TOKEN_MINT}, outAmount: ${liquidityData.outAmount} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
            } else {
                checkLiquidity.textContent = 'Insufficient';
                checkLiquidity.classList.add('error');
                errorMessages.push(`Insufficient liquidity for token: ${TOKEN_MINT}, Response: ${responseText}`);
                log_message(`Liquidity check failed: Insufficient liquidity for token: ${TOKEN_MINT} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'ERROR');
                allChecksPassed = false;
            }
        }
    } catch (error) {
        log_message(`Error checking liquidity for transaction ID ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        checkLiquidity.textContent = 'Failed';
        checkLiquidity.classList.add('error');
        errorMessages.push(`Liquidity check error: ${error.message}`);
        allChecksPassed = false;
    }

    // Display all error messages
    if (errorMessages.length > 0) {
        checkError.innerHTML = errorMessages.map(msg => `<p style="color: red;">${msg}</p>`).join('');
        checkError.style.display = 'block';
        log_message(`One or more pre-transaction checks failed for transaction ID ${transactionId}: ${errorMessages.join('; ')}`, 'make-market.log', 'make-market', 'ERROR');
    }

    // If all checks pass, start transaction
    if (allChecksPassed) {
        document.getElementById('progress-section').style.display = 'block';
        log_message(`All pre-transaction checks passed for transaction ID ${transactionId}, starting transaction`, 'make-market.log', 'make-market', 'INFO');
        startTransaction();
    }
}

// Start transaction
async function startTransaction() {
    const resultDiv = document.getElementById('check-error');
    try {
        log_message(`Starting transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
        const response = await fetch('/make-market/start-transaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ transaction_id: transactionId })
        });
        const responseText = await response.text();
        log_message(`Start transaction response: HTTP ${response.status}, Response: ${responseText} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}, Response: ${responseText}`);
        }
        const result = JSON.parse(responseText);
        if (result.status !== 'success') {
            throw new Error(result.message || 'Failed to start transaction');
        }
        log_message(`Transaction ID ${transactionId} started`, 'make-market.log', 'make-market', 'INFO');
        pollTransactionStatus();
    } catch (error) {
        log_message(`Error starting transaction ID ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        resultDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
        resultDiv.style.display = 'block';
    }
}

// Poll transaction status
function pollTransactionStatus() {
    const transactionLog = document.getElementById('transaction-log');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const currentLoopSpan = document.getElementById('current-loop');
    const statusSpan = document.getElementById('transaction-status');
    const cancelBtn = document.getElementById('cancel-btn');

    const interval = setInterval(async () => {
        try {
            const response = await fetch(`/make-market/status.php?id=${transactionId}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const responseText = await response.text();
            log_message(`Status check response: HTTP ${response.status}, Response: ${responseText} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}, Response: ${responseText}`);
            }
            const data = JSON.parse(responseText);
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to fetch status');
            }

            // Update status
            statusSpan.textContent = data.transaction.status;
            currentLoop = data.transaction.current_loop || 0;
            currentLoopSpan.textContent = currentLoop;

            // Update progress bar
            const progressPercent = (currentLoop / loopCount) * 100;
            progressBar.style.width = `${progressPercent}%`;
            progressText.textContent = `${Math.round(progressPercent)}%`;

            // Update transaction log
            if (data.transaction.logs && data.transaction.logs.length > 0) {
                transactionLog.innerHTML = data.transaction.logs.map(log => 
                    `<p>${log.timestamp} ${log.message} ${log.tx_id ? `<a href="https://solscan.io/tx/${log.tx_id}" target="_blank">${log.tx_id.substring(0, 4)}...</a>` : ''}</p>`
                ).join('');
            }

            // Show/hide cancel button
            cancelBtn.style.display = ['pending', 'processing'].includes(data.transaction.status.toLowerCase()) ? 'inline-block' : 'none';

            // Stop polling if transaction is complete or canceled
            if (['success', 'failed', 'canceled'].includes(data.transaction.status.toLowerCase())) {
                clearInterval(interval);
                const resultDiv = document.getElementById('check-error');
                resultDiv.innerHTML = `<p style="color: ${data.transaction.status.toLowerCase() === 'success' ? 'green' : 'red'};">Process ${data.transaction.status.toLowerCase() === 'success' ? 'completed successfully!' : data.transaction.status.toLowerCase() === 'failed' ? 'failed: ' + (data.transaction.error || 'Unknown error') : 'canceled.'}</p>`;
                resultDiv.style.display = 'block';
            }
        } catch (error) {
            log_message(`Error polling status for transaction ID ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
            transactionLog.innerHTML += `<p style="color: red;">Error polling status: ${error.message}</p>`;
        }
    }, 5000); // Poll every 5 seconds
}

// Show cancel confirmation popup
function showCancelConfirmation(transactionId) {
    const popup = document.getElementById('cancel-confirmation');
    popup.style.display = 'flex';
    log_message(`Displayed cancel confirmation popup for transaction ID: ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
}

// Close cancel confirmation popup
function closeCancelConfirmation() {
    const popup = document.getElementById('cancel-confirmation');
    popup.style.display = 'none';
    log_message('Cancel confirmation popup closed', 'make-market.log', 'make-market', 'DEBUG');
}

// Confirm cancel action
async function confirmCancel(transactionId) {
    const resultDiv = document.getElementById('check-error');
    try {
        log_message(`Sending cancel request for transaction ID: ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
        const response = await fetch('/make-market/cancel-transaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ id: transactionId })
        });
        const responseText = await response.text();
        log_message(`Cancel transaction response: HTTP ${response.status}, Response: ${responseText} for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}, Response: ${responseText}`);
        }
        const data = JSON.parse(responseText);
        if (data.status !== 'success') {
            throw new Error(data.message || 'Failed to cancel transaction');
        }
        closeCancelConfirmation();
        resultDiv.innerHTML = `<p style="color: green;">Transaction ${transactionId} canceled successfully!</p>`;
        resultDiv.style.display = 'block';
        log_message(`Transaction ${transactionId} canceled successfully`, 'make-market.log', 'make-market', 'INFO');
    } catch (error) {
        log_message(`Error canceling transaction ${transactionId}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        resultDiv.innerHTML = `<p style="color: red;">Error canceling transaction: ${error.message}</p>`;
        resultDiv.style.display = 'block';
    }
}

// Initialize checks on page load
document.addEventListener('DOMContentLoaded', () => {
    log_message(`process.js loaded for transaction ID ${transactionId}`, 'make-market.log', 'make-market', 'DEBUG');
    performChecks();
});
