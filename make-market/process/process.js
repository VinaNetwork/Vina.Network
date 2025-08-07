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
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Show success message with styled alert
function showSuccess(message, results = [], solanaNetwork = 'mainnet') {
    const resultDiv = document.getElementById('process-result');
    const explorerUrl = solanaNetwork === 'testnet' ? 'https://solana.fm/tx/' : 'https://solscan.io/tx/';
    let html = `
        <div class="alert alert-${results.some(r => r.status === 'error') ? 'warning' : 'success'}">
            <strong>${results.some(r => r.status === 'error') ? 'Partial Success' : 'Success'}:</strong> ${message}
    `;
    if (results.length > 0) {
        html += '<ul>';
        results.forEach(result => {
            html += `<li>Loop ${result.loop}, Batch ${result.batch_index} (${result.direction}): ${
                result.status === 'success' 
                    ? `<a href="${explorerUrl}${result.txid}${solanaNetwork === 'testnet' ? '?cluster=testnet' : ''}" target="_blank">Success (txid: ${result.txid})</a>`
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
    log_message(`Process completed: ${message}, network=${solanaNetwork}`, 'make-market.log', 'make-market', 'INFO');
    console.log(`Process completed: ${message}, network=${solanaNetwork}`);
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Update transaction status and error in database
async function updateTransactionStatus(status, error = null) {
    const transactionId = window.location.pathname.split('/').pop();
    try {
        const response = await fetch(`/make-market/get-status/${transactionId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: transactionId, status, error })
        });
        console.log(`Updating status for ID: ${transactionId}, URL: /make-market/get-status/${transactionId}`);
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
        const response = await fetch(`/make-market/get-status/${transactionId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: transactionId, status: 'canceled', error: 'Transaction canceled by user' })
        });
        console.log(`Canceling transaction for ID: ${transactionId}, URL: /make-market/get-status/${transactionId}`);
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
        const cancelBtn = document.getElementById('cancel-btn');
        if (cancelBtn) {
            cancelBtn.style.display = 'none';
        }
    } catch (err) {
        log_message(`Failed to cancel transaction: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Failed to cancel transaction: ' + err.message);
    }
}

// Get Helius API key and Solana network from server
async function getApiConfig() {
    try {
        const response = await fetch('/make-market/get-api', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        console.log(`Fetching API config, URL: /make-market/get-api`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message);
        }
        log_message(`API config fetched successfully: network=${result.solana_network}`, 'make-market.log', 'make-market', 'INFO');
        console.log(`API config fetched successfully: network=${result.solana_network}`);
        return {
            heliusApiKey: result.helius_api_key,
            solanaNetwork: result.solana_network
        };
    } catch (err) {
        log_message(`Failed to fetch API config: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to fetch API config:', err.message);
        throw err;
    }
}

// Get token decimals from Solana or Helius API
async function getTokenDecimals(tokenMint, heliusApiKey, solanaNetwork) {
    const maxRetries = 3;
    const endpoints = solanaNetwork === 'testnet' 
        ? ['https://api.testnet.solana.com', 'https://api.devnet.solana.com'] // Fallback endpoint
        : [`https://mainnet.helius-rpc.com/?api-key=${heliusApiKey}`];
    let attempt = 0;
    let endpointIndex = 0;

    while (attempt < maxRetries) {
        const rpcUrl = endpoints[endpointIndex];
        try {
            const response = await axios.post(rpcUrl, {
                jsonrpc: '2.0',
                id: '1',
                method: 'getAccountInfo',
                params: [tokenMint, { encoding: 'jsonParsed' }]
            }, {
                timeout: 10000, // 10 seconds timeout
                headers: { 'Content-Type': 'application/json' }
            });
            if (response.status !== 200 || !response.data.result || !response.data.result.value) {
                throw new Error(`Invalid response: status=${response.status}, data=${JSON.stringify(response.data)}`);
            }
            const decimals = response.data.result.value.data.parsed.info.decimals || 9;
            log_message(`Token decimals retrieved via getAccountInfo: mint=${tokenMint}, decimals=${decimals}, network=${solanaNetwork}, endpoint=${rpcUrl}`, 'make-market.log', 'make-market', 'INFO');
            console.log(`Token decimals retrieved via getAccountInfo: mint=${tokenMint}, decimals=${decimals}, network=${solanaNetwork}, endpoint=${rpcUrl}`);
            return decimals;
        } catch (err) {
            attempt++;
            const errorMessage = err.response ? `HTTP ${err.response.status}: ${JSON.stringify(err.response.data)}` : err.message;
            log_message(`Failed to get token decimals (attempt ${attempt}/${maxRetries}): mint=${tokenMint}, error=${errorMessage}, network=${solanaNetwork}, endpoint=${rpcUrl}`, 'make-market.log', 'make-market', 'ERROR');
            console.error(`Failed to get token decimals (attempt ${attempt}/${maxRetries}):`, errorMessage);
            if (attempt === maxRetries && endpointIndex < endpoints.length - 1) {
                endpointIndex++; // Switch to fallback endpoint
                attempt = 0; // Reset attempts for new endpoint
                log_message(`Switching to fallback endpoint: ${endpoints[endpointIndex]}`, 'make-market.log', 'make-market', 'INFO');
                console.log(`Switching to fallback endpoint: ${endpoints[endpointIndex]}`);
            } else if (attempt === maxRetries) {
                throw new Error(`Failed to retrieve token decimals after ${maxRetries} attempts: ${errorMessage}`);
            }
            await delay(1000 * attempt); // Wait 1s, 2s, 3s before retry
        }
    }
}

// Get quote from Jupiter API
async function getQuote(inputMint, outputMint, amount, slippageBps, solanaNetwork) {
    try {
        const quoteApiUrl = solanaNetwork === 'testnet' 
            ? 'https://quote-api.jup.ag/v6/quote?testnet=true'
            : 'https://quote-api.jup.ag/v6/quote';
        const response = await axios.get(quoteApiUrl, {
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
        log_message(`Quote retrieved: input=${inputMint}, output=${outputMint}, amount=${amount / 1e9}, network=${solanaNetwork}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Quote retrieved:', response.data);
        return response.data;
    } catch (err) {
        log_message(`Failed to get quote: ${err.message}, network=${solanaNetwork}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to get quote:', err.message);
        throw err;
    }
}

// Get swap transaction from Jupiter API
async function getSwapTransaction(quote, publicKey, solanaNetwork) {
    try {
        const swapApiUrl = solanaNetwork === 'testnet' 
            ? 'https://quote-api.jup.ag/v6/swap?testnet=true'
            : 'https://quote-api.jup.ag/v6/swap';
        const response = await axios.post(swapApiUrl, {
            quoteResponse: quote,
            userPublicKey: publicKey,
            wrapAndUnwrapSol: true,
            dynamicComputeUnitLimit: true,
            prioritizationFeeLamports: solanaNetwork === 'testnet' ? 0 : 10000
        });
        if (response.status !== 200 || !response.data) {
            throw new Error('Failed to prepare swap transaction from Jupiter API');
        }
        const { swapTransaction } = response.data;
        log_message(`Swap transaction prepared: ${swapTransaction.substring(0, 20)}..., network=${solanaNetwork}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Swap transaction prepared:', swapTransaction);
        return swapTransaction;
    } catch (err) {
        log_message(`Swap transaction failed: ${err.message}, network=${solanaNetwork}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Swap transaction failed:', err.message);
        throw err;
    }
}

// Execute swap transactions
async function executeSwapTransactions(transactionId, swapTransactions, solanaNetwork) {
    try {
        const response = await fetch('/make-market/swap-transactions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: transactionId, swap_transactions: swapTransactions })
        });
        console.log(`Executing swap transactions for ID: ${transactionId}, URL: /make-market/swap-transactions`);
        if (!response.ok) {
            const result = await response.json();
            throw new Error(result.message || `Server error: HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success' && result.status !== 'partial') {
            throw new Error(result.message || 'Unknown server error');
        }
        log_message(`Swap transactions executed: status=${result.status}, network=${solanaNetwork}`, 'make-market.log', 'make-market', 'INFO');
        return result;
    } catch (err) {
        log_message(`Swap execution failed: ${err.message}, network=${solanaNetwork}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Swap execution failed:', err.message);
        throw err;
    }
}

// Delay function
function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Main process and copy functionality
document.addEventListener('DOMContentLoaded', async () => {
    log_message('process.js loaded', 'make-market.log', 'make-market', 'DEBUG');
    console.log('process.js loaded');

    // Copy functionality
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

    // Main process
    const transactionId = window.location.pathname.split('/').pop();
    if (!transactionId || isNaN(transactionId)) {
        showError('Invalid transaction ID');
        return;
    }

    // Fetch API config
    let heliusApiKey, solanaNetwork;
    try {
        const apiConfig = await getApiConfig();
        heliusApiKey = apiConfig.heliusApiKey;
        solanaNetwork = apiConfig.solanaNetwork;
    } catch (err) {
        showError('Failed to retrieve API config: ' + err.message);
        return;
    }

    // Fetch transaction details
    let transaction;
    try {
        const response = await fetch(`/make-market/get-tx/${transactionId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        console.log(`Fetching transaction for ID: ${transactionId}, URL: /make-market/get-tx/${transactionId}`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message);
        }
        transaction = result.data;
        transaction.loop_count = parseInt(transaction.loop_count) || 1;
        transaction.batch_size = parseInt(transaction.batch_size) || 1;
        transaction.slippage = parseFloat(transaction.slippage) || 0.5;
        transaction.delay_seconds = parseInt(transaction.delay_seconds) || 1;
        transaction.sol_amount = parseFloat(transaction.sol_amount) || 0;
        transaction.token_amount = parseFloat(transaction.token_amount) || 0;
        transaction.trade_direction = transaction.trade_direction || 'buy';
        log_message(`Transaction fetched: ID=${transactionId}, token_mint=${transaction.token_mint}, public_key=${transaction.public_key}, sol_amount=${transaction.sol_amount}, token_amount=${transaction.token_amount}, trade_direction=${transaction.trade_direction}, loop_count=${transaction.loop_count}, batch_size=${transaction.batch_size}, slippage=${transaction.slippage}, delay_seconds=${transaction.delay_seconds}, status=${transaction.status}, network=${solanaNetwork}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Transaction fetched:', transaction);
    } catch (err) {
        showError('Failed to retrieve transaction info: ' + err.message);
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

    // Fetch public key
    let publicKey;
    try {
        const response = await fetch(`/make-market/get-public-key/${transactionId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        console.log(`Fetching public key for ID: ${transactionId}, URL: /make-market/get-public-key/${transactionId}`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message);
        }
        publicKey = result.public_key;
        log_message(`Public key fetched: ${publicKey}, network=${solanaNetwork}`, 'make-market.log', 'make-market', 'INFO');
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
            log_message(`Cancel button clicked for transaction ID=${transactionId}, network=${solanaNetwork}`, 'make-market.log', 'make-market', 'INFO');
            console.log(`Cancel button clicked for transaction ID=${transactionId}`);
            await cancelTransaction(transactionId);
        });
    }

    // Update status to pending
    await updateTransactionStatus('pending');

    // Fetch token decimals
    let tokenDecimals;
    try {
        tokenDecimals = await getTokenDecimals(transaction.token_mint, heliusApiKey, solanaNetwork);
    } catch (err) {
        showError('Failed to retrieve token decimals: ' + err.message);
        return;
    }

    // Process swaps with loop_count and batch_size
    try {
        const solMint = solanaNetwork === 'testnet' 
            ? 'So11111111111111111111111111111111111111112' // WSOL on testnet
            : 'So11111111111111111111111111111111111111112';
        const tokenMint = transaction.token_mint;
        const solAmount = transaction.sol_amount * 1e9; // Convert SOL to lamports
        const tokenAmount = transaction.token_amount * Math.pow(10, tokenDecimals); // Convert token amount to correct decimals
        const slippageBps = Math.floor(transaction.slippage * 100); // Convert to basis points
        const delaySeconds = transaction.delay_seconds * 1000; // Convert to milliseconds
        const swapTransactions = [];

        // Generate swap transactions based on trade_direction
        for (let loop = 1; loop <= loopCount; loop++) {
            document.getElementById('swap-status').textContent = `Preparing loop ${loop} of ${loopCount} on ${solanaNetwork}...`;
            for (let i = 0; i < batchSize; i++) {
                if (tradeDirection === 'buy' || tradeDirection === 'both') {
                    document.getElementById('swap-status').textContent = `Retrieving buy quote for loop ${loop}, batch ${i + 1} on ${solanaNetwork}...`;
                    const buyQuote = await getQuote(solMint, tokenMint, solAmount, slippageBps, solanaNetwork);
                    const buyTx = await getSwapTransaction(buyQuote, publicKey, solanaNetwork);
                    swapTransactions.push({ direction: 'buy', tx: buyTx });
                    if (i < batchSize - 1 || tradeDirection === 'both') {
                        document.getElementById('swap-status').textContent = `Waiting ${transaction.delay_seconds} seconds before next ${tradeDirection === 'both' ? 'buy/sell' : 'batch'} in loop ${loop}...`;
                        await delay(delaySeconds);
                    }
                }
                if (tradeDirection === 'sell' || tradeDirection === 'both') {
                    document.getElementById('swap-status').textContent = `Retrieving sell quote for loop ${loop}, batch ${i + 1} on ${solanaNetwork}...`;
                    const sellQuote = await getQuote(tokenMint, solMint, tokenAmount, slippageBps, solanaNetwork);
                    const sellTx = await getSwapTransaction(sellQuote, publicKey, solanaNetwork);
                    swapTransactions.push({ direction: 'sell', tx: sellTx });
                    if (i < batchSize - 1) {
                        document.getElementById('swap-status').textContent = `Waiting ${transaction.delay_seconds} seconds before next batch in loop ${loop}...`;
                        await delay(delaySeconds);
                    }
                }
            }
            if (loop < loopCount) {
                document.getElementById('swap-status').textContent = `Waiting ${transaction.delay_seconds} seconds before next loop on ${solanaNetwork}...`;
                await delay(delaySeconds);
            }
        }

        // Execute swaps
        document.getElementById('swap-status').textContent = `Executing swap transactions on ${solanaNetwork}...`;
        const swapResult = await executeSwapTransactions(transactionId, swapTransactions, solanaNetwork);
        const successCount = swapResult.results.filter(r => r.status === 'success').length;
        const totalTransactions = swapTransactions.length;
        await updateTransactionStatus(successCount === totalTransactions ? 'success' : 'partial', `Completed ${successCount} of ${totalTransactions} transactions on ${solanaNetwork}`);
        showSuccess(`Completed ${successCount} of ${totalTransactions} transactions on ${solanaNetwork}`, swapResult.results, solanaNetwork);
    } catch (err) {
        showError('Error during swap process: ' + err.message);
    }
});
