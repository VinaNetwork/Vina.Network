// ============================================================================
// File: make-market/process/process.js
// Description: JavaScript for processing token mint and liquidity checks
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

// Check token mint existence
async function checkTokenMint(tokenMint) {
    const tokenMintCheck = document.getElementById('token-mint-check');
    tokenMintCheck.textContent = 'Checking token mint...';
    try {
        const connection = new window.solanaWeb3.Connection('https://api.mainnet-beta.solana.com');
        const publicKey = new window.solanaWeb3.PublicKey(tokenMint);
        const accountInfo = await connection.getAccountInfo(publicKey);
        if (!accountInfo) {
            tokenMintCheck.textContent = 'Address does not exist';
            tokenMintCheck.classList.add('error');
            showError('Address does not exist');
            return false;
        }
        tokenMintCheck.textContent = 'Done';
        tokenMintCheck.classList.add('done');
        log_message(`Token mint check passed: ${tokenMint}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Token mint check passed:', tokenMint);
        return true;
    } catch (err) {
        tokenMintCheck.textContent = 'Address does not exist';
        tokenMintCheck.classList.add('error');
        showError('Address does not exist');
        log_message(`Token mint check failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Token mint check failed:', err.message);
        return false;
    }
}

// Check liquidity on Jupiter API
async function checkLiquidity(tokenMint, solAmount) {
    const liquidityCheck = document.getElementById('liquidity-check');
    liquidityCheck.textContent = 'Checking liquidity...';
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
        const liquidityValue = response.data.outAmount / 1e9; // Convert to tokens
        liquidityCheck.textContent = `Done (${liquidityValue.toFixed(6)})`;
        liquidityCheck.classList.add('done');
        log_message(`Liquidity check passed: ${liquidityValue} tokens`, 'make-market.log', 'make-market', 'INFO');
        console.log('Liquidity check passed:', liquidityValue);
        return true;
    } catch (err) {
        liquidityCheck.textContent = 'Insufficient liquidity';
        liquidityCheck.classList.add('error');
        showError('Insufficient liquidity');
        log_message(`Liquidity check failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Liquidity check failed:', err.message);
        return false;
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
        log_message(`Transaction fetched: ID=${transactionId}, token_mint=${transaction.token_mint}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Transaction fetched:', transaction);
    } catch (err) {
        showError('Failed to fetch transaction: ' + err.message);
        return;
    }

    // Update status to pending
    await updateTransactionStatus('pending');

    // Step 1: Check token mint
    const tokenMintValid = await checkTokenMint(transaction.token_mint);
    if (!tokenMintValid) {
        return;
    }

    // Step 2: Check liquidity
    const liquidityValid = await checkLiquidity(transaction.token_mint, transaction.sol_amount);
    if (!liquidityValid) {
        return;
    }

    // If both checks pass, update status to processing
    await updateTransactionStatus('processing');
    log_message('All checks passed, ready for trading', 'make-market.log', 'make-market', 'INFO');
    console.log('All checks passed, ready for trading');
    document.getElementById('process-result').innerHTML = '<p style="color: green;">All checks passed. Ready for trading.</p>';
    document.getElementById('process-result').classList.add('active');
});

// Copy functionality (same as mm.js)
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
