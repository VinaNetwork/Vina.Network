// ============================================================================
// File: make-market/process/process.js
// Description: JavaScript for processing Solana token swap using Jupiter Aggregator API
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

// Show error message and stop process
function showError(message) {
    const resultDiv = document.getElementById('process-result');
    resultDiv.innerHTML = `<p style="color: red;">Error: ${message}</p>`;
    resultDiv.classList.add('active');
    document.getElementById('swap-status').textContent = ''; // Clear swap-status
    log_message(`Process stopped: ${message}`, 'make-market.log', 'make-market', 'ERROR');
    console.error(`Process stopped: ${message}`);
    updateTransactionStatus('failed', message);
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
        document.getElementById('transaction-status').textContent = status;
    } catch (err) {
        log_message(`Failed to update transaction status: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to update transaction status:', err.message);
    }
}

// Check wallet balance
async function checkBalance(publicKey, solAmount) {
    try {
        const connection = new window.solanaWeb3.Connection('https://mainnet.helius-rpc.com/?api-key=YOUR_HELIUS_API_KEY');
        const publicKeyObj = new window.solanaWeb3.PublicKey(publicKey);
        const balance = await connection.getBalance(publicKeyObj);
        const balanceInSol = balance / 1e9; // Convert lamports to SOL
        const requiredAmount = solAmount + 0.005; // Add 0.005 SOL for fees
        if (balanceInSol < requiredAmount) {
            throw new Error(`Insufficient balance: ${balanceInSol} SOL available, ${requiredAmount} SOL required`);
        }
        log_message(`Balance check passed: ${balanceInSol} SOL available`, 'make-market.log', 'make-market', 'INFO');
        console.log('Balance check passed:', balanceInSol);
        return true;
    } catch (err) {
        log_message(`Balance check failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Balance check failed:', err.message);
        throw err;
    }
}

// Get quote from Jupiter API
async function getQuote(tokenMint, solAmount) {
    try {
        const response = await axios.get('https://quote-api.jup.ag/v6/quote', {
            params: {
                inputMint: 'So11111111111111111111111111111111111111112', // SOL
                outputMint: tokenMint,
                amount: Math.floor(solAmount * 1e9), // Convert SOL to lamports
                slippageBps: 50 // 0.5% slippage
            }
        });
        if (response.status !== 200 || !response.data) {
            throw new Error('Invalid response from Jupiter API');
        }
        log_message(`Quote retrieved: ${response.data.outAmount / 1e9} tokens`, 'make-market.log', 'make-market', 'INFO');
        console.log('Quote retrieved:', response.data);
        return response.data;
    } catch (err) {
        log_message(`Failed to get quote: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to get quote:', err.message);
        throw err;
    }
}

// Execute swap using Jupiter API
async function executeSwap(quote, publicKey, transactionId) {
    try {
        const response = await axios.post('https://quote-api.jup.ag/v6/swap', {
            quoteResponse: quote,
            userPublicKey: publicKey,
            wrapAndUnwrapSol: true,
            dynamicComputeUnitLimit: true,
            prioritizationFeeLamports: 10000
        });
        if (response.status !== 200 || !response.data) {
            throw new Error('Invalid response from Jupiter swap API');
        }
        const { swapTransaction } = response.data;
        log_message(`Swap transaction prepared: ${swapTransaction}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Swap transaction prepared:', swapTransaction);

        // Send to server for signing
        const swapResponse = await fetch('/make-market/process/swap.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: transactionId, swap_transaction: swapTransaction })
        });
        if (!swapResponse.ok) {
            if (swapResponse.status === 404) {
                throw new Error('Server error: swap.php not found');
            }
            throw new Error(`Server error: HTTP ${swapResponse.status}`);
        }
        const swapResult = await swapResponse.json();
        if (swapResult.status !== 'success') {
            throw new Error(swapResult.message);
        }
        log_message(`Swap executed: txid=${swapResult.txid}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Swap executed:', swapResult.txid);
        return swapResult.txid;
    } catch (err) {
        log_message(`Swap failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Swap failed:', err.message);
        throw err;
    }
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
        log_message(`Transaction fetched: ID=${transactionId}, token_mint=${transaction.token_mint}, public_key=${transaction.public_key}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Transaction fetched:', transaction);
    } catch (err) {
        showError('Failed to fetch transaction: ' + err.message);
        return;
    }

    // Fetch public key from private key
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
        showError('Failed to fetch public key: ' + err.message);
        return;
    }

    // Update status to pending
    await updateTransactionStatus('pending');

    // Check balance
    try {
        document.getElementById('swap-status').textContent = 'Checking balance...';
        await checkBalance(publicKey, transaction.sol_amount);
    } catch (err) {
        showError(err.message);
        return;
    }

    // Get quote and execute swap
    try {
        document.getElementById('swap-status').textContent = 'Getting quote...';
        const quote = await getQuote(transaction.token_mint, transaction.sol_amount);
        document.getElementById('swap-status').textContent = 'Preparing swap...';
        
        const txid = await executeSwap(quote, publicKey, transactionId);
        await updateTransactionStatus('success');
        document.getElementById('swap-status').textContent = `Swap completed: txid=${txid}`;
        log_message('Swap completed, ready for trading', 'make-market.log', 'make-market', 'INFO');
        console.log('Swap completed, ready for trading');
        document.getElementById('process-result').innerHTML = `<p style="color: green;">Swap completed. Transaction ID: <a href="https://solscan.io/tx/${txid}" target="_blank">${txid}</a></p>`;
        document.getElementById('process-result').classList.add('active');
    } catch (err) {
        showError('Swap failed: ' + err.message);
    }
});

// Copy functionality
document.addEventListener('DOMContentLoaded', () => {
    const copyIcons = document.querySelectorAll('.copy-icon');
    log_message(`Found ${copyIcons.length} copy icons`, 'make-market.log', 'make-market', 'DEBUG');
    if (copyIcons.length === 0) {
        log_message('No .copy-icon elements found in DOM', 'make-market.log', 'make-market', 'ERROR');
        return;
    }

    copyIcons.forEach(icon => {
        log_message('Attaching click event to copy icon', 'make-market.log', 'make-market', 'DEBUG');
        icon.addEventListener('click', (e) => {
            log_message('Copy icon clicked', 'make-market.log', 'make-market', 'INFO');
            console.log('Copy icon clicked');

            if (!window.isSecureContext) {
                log_message('Copy blocked: Not in secure context', 'make-market.log', 'make-market', 'ERROR');
                console.error('Copy blocked: Not in secure context');
                document.getElementById('process-result').innerHTML = '<p style="color: red;">Error: Unable to copy: This feature requires HTTPS</p>';
                document.getElementById('process-result').classList.add('active');
                return;
            }

            const fullAddress = icon.getAttribute('data-full');
            if (!fullAddress) {
                log_message('Copy failed: data-full attribute not found or empty', 'make-market.log', 'make-market', 'ERROR');
                console.error('Copy failed: data-full attribute not found or empty');
                document.getElementById('process-result').innerHTML = '<p style="color: red;">Error: Unable to copy: Invalid address</p>';
                document.getElementById('process-result').classList.add('active');
                return;
            }

            const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
            if (!base58Regex.test(fullAddress)) {
                log_message(`Invalid address format: ${fullAddress}`, 'make-market.log', 'make-market', 'ERROR');
                console.error(`Invalid address format: ${fullAddress}`);
                document.getElementById('process-result').innerHTML = '<p style="color: red;">Error: Unable to copy: Invalid address format</p>';
                document.getElementById('process-result').classList.add('active');
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
                console.error('Clipboard API failed:', err.message);
                document.getElementById('process-result').innerHTML = `<p style="color: red;">Error: Unable to copy: ${err.message}</p>`;
                document.getElementById('process-result').classList.add('active');
            });
        });
    });
});
