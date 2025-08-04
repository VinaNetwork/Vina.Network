// ============================================================================
// File: make-market/process/process.js
// Description: JavaScript for processing Solana token swap with looping using Jupiter Aggregator API
// Created by: Vina Network
// ============================================================================

// Log message function
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

// Show error message with styled alert
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
    log_message(`Process stopped: ${message}${detailedError ? `, Details: ${detailedError}` : ''}`, 'make-market.log', 'make-market', 'ERROR');
    console.error(`Process stopped: ${message}${detailedError ? `, Details: ${detailedError}` : ''}`);
    updateTransactionStatus('failed', detailedError || message);
    // Hide Cancel button
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Show success message with styled alert
function showSuccess(message, results = []) {
    const resultDiv = document.getElementById('process-result');
    let html = `
        <div class="alert alert-${results.some(r => r.status === 'error') ? 'warning' : 'success'}">
            <strong>${results.some(r => r.status === 'error') ? 'Partial Success' : 'Success'}:</strong> ${message}
    `;
    if (results.length > 0) {
        html += '<ul>';
        results.forEach(result => {
            html += `<li>Loop ${result.loop}, Batch ${result.batch_index}: ${
                result.status === 'success' 
                    ? `<a href="https://solscan.io/tx/${result.txid}" target="_blank">Success (txid: ${result.txid})</a>`
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
    log_message(`Process completed: ${message}`, 'make-market.log', 'make-market', 'INFO');
    console.log(`Process completed: ${message}`);
    // Hide Cancel button
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Update transaction status and error in database
async function updateTransactionStatus(status, error = null) {
    const transactionId = new URLSearchParams(window.location.search).get('id');
    try {
        const response = await fetch('/make-market/process/get-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: transactionId, status, error })
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message);
        }
        log_message(`Transaction status updated: ID=${transactionId}, status=${status}, error=${error || 'none'}`, 'make-market.log', 'make-market', 'INFO');
        console.log(`Transaction status updated: ID=${transactionId}, status=${status}, error=${error || 'none'}`);
    } catch (err) {
        log_message(`Failed to update transaction status: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to update transaction status:', err.message);
    }
}

// Cancel transaction
async function cancelTransaction(transactionId) {
    try {
        const response = await fetch('/make-market/process/get-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: transactionId, status: 'canceled', error: 'Transaction canceled by user' })
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message);
        }
        log_message(`Transaction canceled: ID=${transactionId}`, 'make-market.log', 'make-market', 'INFO');
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
        // Hide Cancel button
        const cancelBtn = document.getElementById('cancel-btn');
        if (cancelBtn) {
            cancelBtn.style.display = 'none';
        }
    } catch (err) {
        log_message(`Failed to cancel transaction: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Failed to cancel transaction: ' + err.message);
    }
}

// Check SOL balance
async function checkSolBalance(transactionId, requiredSol) {
    try {
        const response = await fetch(`/make-market/process/get-balance.php?id=${transactionId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message);
        }
        const balanceSol = result.balance;
        log_message(`Balance checked: transaction_id=${transactionId}, balance=${balanceSol} SOL, required=${requiredSol} SOL`, 'make-market.log', 'make-market', 'INFO');
        console.log(`Balance checked: ${balanceSol} SOL`);
        return { sufficient: balanceSol >= requiredSol, balance: balanceSol };
    } catch (err) {
        log_message(`Failed to check balance: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to check balance:', err.message);
        throw err;
    }
}

// Get quote from Jupiter API
async function getQuote(inputMint, outputMint, amount, slippageBps) {
    try {
        const response = await axios.get('https://quote-api.jup.ag/v6/quote', {
            params: {
                inputMint,
                outputMint,
                amount: Math.floor(amount),
                slippageBps
            }
        });
        if (response.status !== 200 || !response.data) {
            throw new Error('Failed to retrieve quote from Jupiter API');
        }
        log_message(`Quote retrieved: input=${inputMint}, output=${outputMint}, amount=${amount / 1e9} tokens`, 'make-market.log', 'make-market', 'INFO');
        console.log('Quote retrieved:', response.data);
        return response.data;
    } catch (err) {
        log_message(`Failed to get quote: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to get quote:', err.message);
        throw err;
    }
}

// Get swap transaction from Jupiter API
async function getSwapTransaction(quote, publicKey) {
    try {
        const response = await axios.post('https://quote-api.jup.ag/v6/swap', {
            quoteResponse: quote,
            userPublicKey: publicKey,
            wrapAndUnwrapSol: true,
            dynamicComputeUnitLimit: true,
            prioritizationFeeLamports: 10000
        });
        if (response.status !== 200 || !response.data) {
            throw new Error('Failed to prepare swap transaction from Jupiter API');
        }
        const { swapTransaction } = response.data;
        log_message(`Swap transaction prepared: ${swapTransaction.substring(0, 20)}...`, 'make-market.log', 'make-market', 'INFO');
        console.log('Swap transaction prepared:', swapTransaction);
        return swapTransaction;
    } catch (err) {
        log_message(`Swap transaction failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Swap transaction failed:', err.message);
        throw err;
    }
}

// Execute swap transactions
async function executeSwapTransactions(transactionId, swapTransactions) {
    try {
        const response = await fetch('/make-market/process/swap.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: transactionId, swap_transactions: swapTransactions })
        });
        if (!response.ok) {
            const result = await response.json();
            if (result.message.includes('Insufficient wallet balance')) {
                throw new Error(`Insufficient SOL balance. Required: ${result.required} SOL, Available: ${result.balance} SOL`);
            }
            throw new Error(result.message || `Server error: HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success' && result.status !== 'partial') {
            throw new Error(result.message);
        }
        return result;
    } catch (err) {
        log_message(`Swap execution failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Swap execution failed:', err.message);
        throw err;
    }
}

// Delay function
function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Main process
document.addEventListener('DOMContentLoaded', async () => {
    log_message('process.js loaded', 'make-market.log', 'make-market', 'DEBUG');
    console.log('process.js loaded');

    const transactionId = new URLSearchParams(window.location.search).get('id');
    if (!transactionId) {
        showError('Invalid transaction ID');
        return;
    }

    // Fetch transaction details
    let transaction;
    try {
        const response = await fetch(`/make-market/process/get-tx.php?id=${transactionId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message);
        }
        transaction = result.data;
        // Ensure defaults
        transaction.loop_count = parseInt(transaction.loop_count) || 1;
        transaction.batch_size = parseInt(transaction.batch_size) || 1;
        transaction.slippage = parseFloat(transaction.slippage) || 0.5;
        transaction.delay_seconds = parseInt(transaction.delay_seconds) || 1;
        log_message(`Transaction fetched: ID=${transactionId}, token_mint=${transaction.token_mint}, public_key=${transaction.public_key}, sol_amount=${transaction.sol_amount}, loop_count=${transaction.loop_count}, batch_size=${transaction.batch_size}, slippage=${transaction.slippage}, delay_seconds=${transaction.delay_seconds}, status=${transaction.status}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Transaction fetched:', transaction);
    } catch (err) {
        showError('Failed to retrieve transaction info: ' + err.message);
        return;
    }

    // Validate loop_count and batch_size
    const loopCount = transaction.loop_count;
    const batchSize = transaction.batch_size;
    if (isNaN(loopCount) || isNaN(batchSize) || loopCount <= 0 || batchSize <= 0) {
        showError('Invalid transaction parameters: loop_count or batch_size is invalid');
        return;
    }

    // Fetch public key
    let publicKey;
    try {
        const response = await fetch(`/make-market/process/get-public-key.php?id=${transactionId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message);
        }
        publicKey = result.public_key;
        log_message(`Public key fetched: ${publicKey}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Public key fetched:', publicKey);
    } catch (err) {
        showError('Failed to retrieve wallet address: ' + err.message);
        return;
    }

    // Check initial status and hide Cancel button if not new/pending/processing
    if (!['new', 'pending', 'processing'].includes(transaction.status)) {
        const cancelBtn = document.getElementById('cancel-btn');
        if (cancelBtn) {
            cancelBtn.style.display = 'none';
        }
    }

    // Handle Cancel button click
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', async () => {
            log_message(`Cancel button clicked for transaction ID=${transactionId}`, 'make-market.log', 'make-market', 'INFO');
            console.log(`Cancel button clicked for transaction ID=${transactionId}`);
            await cancelTransaction(transactionId);
        });
    }

    // Check SOL balance
    try {
        const requiredSol = transaction.sol_amount * loopCount * batchSize + 0.005 * loopCount * batchSize; // SOL + phí
        const balanceCheck = await checkSolBalance(transactionId, requiredSol);
        if (!balanceCheck.sufficient) {
            showError(`Insufficient SOL balance.`, `Required: ${requiredSol} SOL, Available: ${balanceCheck.balance} SOL`);
            return;
        }
    } catch (err) {
        showError('Failed to check SOL balance: ' + err.message, err.message.includes('Insufficient SOL balance') ? err.message : null);
        return;
    }

    // Update status to pending
    await updateTransactionStatus('pending');

    // Process swaps with loop_count and batch_size
    try {
        const solMint = 'So11111111111111111111111111111111111111112';
        const tokenMint = transaction.token_mint;
        const solAmount = transaction.sol_amount * 1e9; // Convert to lamports
        const slippageBps = Math.floor(transaction.slippage * 100); // Convert to basis points
        const delaySeconds = transaction.delay_seconds * 1000; // Convert to milliseconds
        const swapTransactions = [];

        // Generate swap transactions (buy only)
        for (let loop = 1; loop <= loopCount; loop++) {
            document.getElementById('swap-status').textContent = `Preparing loop ${loop} of ${loopCount}...`;
            for (let i = 0; i < batchSize; i++) {
                document.getElementById('swap-status').textContent = `Retrieving buy quote for loop ${loop}, batch ${i + 1}...`;
                const buyQuote = await getQuote(solMint, tokenMint, solAmount, slippageBps);
                const buyTx = await getSwapTransaction(buyQuote, publicKey);
                swapTransactions.push(buyTx);
                if (i < batchSize - 1) {
                    document.getElementById('swap-status').textContent = `Waiting ${transaction.delay_seconds} seconds before next batch in loop ${loop}...`;
                    await delay(delaySeconds);
                }
            }
            if (loop < loopCount) {
                document.getElementById('swap-status').textContent = `Waiting ${transaction.delay_seconds} seconds before next loop...`;
                await delay(delaySeconds);
            }
        }

        // Execute swaps
        document.getElementById('swap-status').textContent = 'Executing swap transactions...';
        const swapResult = await executeSwapTransactions(transactionId, swapTransactions);
        const successCount = swapResult.results.filter(r => r.status === 'success').length;
        const totalTransactions = loopCount * batchSize;
        await updateTransactionStatus(successCount === totalTransactions ? 'success' : 'partial', `Completed ${successCount} of ${totalTransactions} transactions`);
        showSuccess(`Completed ${successCount} of ${totalTransactions} transactions`, swapResult.results);
    } catch (err) {
        showError('Error during swap process: ' + err.message, err.message.includes('Insufficient SOL balance') ? err.message : null);
    }
});

// Copy functionality
document.addEventListener('DOMContentLoaded', () => {
    const copyIcons = document.querySelectorAll('.copy-icon');
    log_message(`Found ${copyIcons.length} copy icons`, 'make-market.log', 'make-market', 'DEBUG');
    if (copyIcons.length === 0) {
        log_message('No .copy-icon elements found in DOM', 'make-market.log', 'make-market', 'ERROR');
    }

    copyIcons.forEach(icon => {
        log_message('Attaching click event to copy icon', 'make-market.log', 'make-market', 'DEBUG');
        icon.addEventListener('click', (e) => {
            log_message('Copy icon clicked', 'make-market.log', 'make-market', 'INFO');
            console.log('Copy icon clicked');

            if (!window.isSecureContext) {
                log_message('Copy blocked: Not in secure context', 'make-market.log', 'make-market', 'ERROR');
                showError('Cannot copy: This feature requires HTTPS');
                return;
            }

            const fullAddress = icon.getAttribute('data-full');
            if (!fullAddress) {
                log_message('Copy failed: data-full attribute not found or empty', 'make-market.log', 'make-market', 'ERROR');
                showError('Cannot copy: Invalid address');
                return;
            }

            const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
            if (!base58Regex.test(fullAddress)) {
                log_message(`Invalid address format: ${fullAddress}`, 'make-market.log', 'make-market', 'ERROR');
                showError('Cannot copy: Invalid address format');
                return;
            }

            navigator.clipboard.writeText(fullAddress).then(() => {
                log_message('Copy successful', 'make-market.log', 'make-market', 'INFO');
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
                    log_message('Copy feedback removed', 'make-market.log', 'make-market', 'DEBUG');
                    console.log('Copy feedback removed');
                }, 2000);
            }).catch(err => {
                log_message(`Clipboard API failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
                showError(`Cannot copy: ${err.message}`);
            });
        });
    });
});
