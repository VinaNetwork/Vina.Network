// ============================================================================
// File: make-market/mm-api.js
// Description: JavaScript file for automated token trading on Solana using Jupiter API
// Created by: Vina Network
// ============================================================================

// Configuration
const JUPITER_API = 'https://quote-api.jup.ag/v6';
const RPC_ENDPOINT = 'https://api.mainnet-beta.solana.com';
const connection = new solanaWeb3.Connection(RPC_ENDPOINT, 'confirmed');

// Log message function
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
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

// Update transaction status
function updateTransaction(transactionId, updates) {
    fetch('/make-market/status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ transactionId, ...updates })
    }).catch(err => {
        console.error('Update transaction error:', err);
        log_message(`Failed to update transaction ${transactionId}: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
    });
}

// Delete private_key from database
async function deletePrivateKey(transactionId) {
    try {
        const response = await fetch('/make-market/status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transactionId, action: 'delete_private_key' })
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Failed to delete private_key`);
        }
        const result = await response.json();
        if (result.status === 'success') {
            log_message(`Successfully deleted private_key for transaction ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
        } else {
            throw new Error(result.message || 'Failed to delete private_key');
        }
    } catch (err) {
        log_message(`Error deleting private_key for transaction ${transactionId}: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
    }
}

// Fetch and decode private_key from database
async function getDecryptedPrivateKey(transactionId) {
    try {
        const response = await fetch(`/make-market/get-private-key.php?transactionId=${transactionId}`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Failed to fetch private_key`);
        }
        const result = await response.json();
        if (result.status !== 'success' || !result.privateKey) {
            throw new Error(result.message || 'No private key found');
        }
        return result.privateKey;
    } catch (err) {
        log_message(`Error fetching private_key for transaction ${transactionId}: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        throw err;
    }
}

// Check private_key status
async function checkPrivateKeyStatus(transactionId) {
    try {
        const response = await fetch('/make-market/check-private-key.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ transaction_id: transactionId })
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Failed to check private_key status`);
        }
        const result = await response.json();
        if (result.status === 'error') {
            throw new Error(result.message);
        }
        return result.isPending;
    } catch (err) {
        log_message(`Error checking private_key status for transaction ${transactionId}: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        throw err;
    }
}

// Wait for required libraries
async function waitForLibraries() {
    const maxAttempts = 20; // 10 seconds
    let attempts = 0;
    while (attempts < maxAttempts) {
        if (
            typeof window.solanaWeb3 !== 'undefined' &&
            typeof window.bs58 !== 'undefined' &&
            typeof window.axios !== 'undefined' &&
            typeof window.splToken !== 'undefined'
        ) {
            log_message('All libraries loaded for makeMarket', 'make-market.log', 'make-market', 'INFO');
            return true;
        }
        log_message(`Waiting for libraries in makeMarket... attempt ${attempts + 1}, solanaWeb3: ${typeof window.solanaWeb3}, bs58: ${typeof window.bs58}, axios: ${typeof window.axios}, splToken: ${typeof window.splToken}`, 'make-market.log', 'make-market', 'DEBUG');
        await new Promise(resolve => setTimeout(resolve, 500));
        attempts++;
    }
    log_message('Failed to load all libraries for makeMarket after max attempts', 'make-market.log', 'make-market', 'ERROR');
    return false;
}

// Process a single transaction pair (buy + sell)
async function processTransactionPair(wallet, tokenMint, solAmount, slippage, delay, processName, transactionId, loopIndex) {
    try {
        // Perform buy transaction
        log_message(`Initiating buy transaction ${loopIndex + 1} for token: ${tokenMint}, amount: ${solAmount} SOL`, 'make-market.log', 'make-market', 'DEBUG');
        let buyTx;
        try {
            buyTx = await swapSOLtoToken(wallet, tokenMint, solAmount, slippage);
        } catch (error) {
            let errorMessage = error.message;
            if (errorMessage.includes('insufficient funds') || errorMessage.includes('NotEnoughBalance')) {
                errorMessage = 'Insufficient SOL balance to complete the transaction';
            }
            log_message(`Buy transaction ${loopIndex + 1} failed: ${errorMessage}`, 'make-market.log', 'make-market', 'ERROR');
            throw new Error(errorMessage);
        }
        const buyTxId = await connection.sendRawTransaction(buyTx.serialize(), {
            skipPreflight: true
        });
        await connection.confirmTransaction(buyTxId);
        log_message(`Buy transaction ${loopIndex + 1} completed: ${buyTxId}`, 'make-market.log', 'make-market', 'INFO');
        updateTransaction(transactionId, { buy_tx_id: buyTxId });

        // Delay between buy and sell
        if (delay > 0) {
            log_message(`Waiting ${delay} seconds before sell for transaction ${loopIndex + 1}`, 'make-market.log', 'make-market', 'DEBUG');
            await new Promise(resolve => setTimeout(resolve, delay * 1000));
        }

        // Perform sell transaction
        log_message(`Initiating sell transaction ${loopIndex + 1} for token: ${tokenMint}`, 'make-market.log', 'make-market', 'DEBUG');
        let sellTx;
        try {
            sellTx = await swapTokentoSOL(wallet, tokenMint, slippage);
        } catch (error) {
            let errorMessage = error.message;
            if (errorMessage.includes('insufficient funds') || errorMessage.includes('NotEnoughBalance')) {
                errorMessage = 'Insufficient token balance to complete the transaction';
            }
            log_message(`Sell transaction ${loopIndex + 1} failed: ${errorMessage}`, 'make-market.log', 'make-market', 'ERROR');
            throw new Error(errorMessage);
        }
        const sellTxId = await connection.sendRawTransaction(sellTx.serialize(), {
            skipPreflight: true
        });
        await connection.confirmTransaction(sellTxId);
        log_message(`Sell transaction ${loopIndex + 1} completed: ${sellTxId}`, 'make-market.log', 'make-market', 'INFO');
        updateTransaction(transactionId, { sell_tx_id: sellTxId });

        return { loopIndex, success: true, buyTxId, sellTxId };
    } catch (error) {
        log_message(`Error in transaction pair ${loopIndex + 1}: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        throw error;
    }
}

// Main function to handle market making
async function makeMarket(
    processName,
    privateKey, // Not used directly, fetched from database
    tokenMint,
    solAmount,
    slippage,
    delay,
    loopCount,
    batchSize,
    transactionId
) {
    const resultDiv = document.getElementById('mm-result');
    const submitButton = document.querySelector('#makeMarketForm button');
    log_message(`Starting makeMarket: process=${processName}, tokenMint=${tokenMint}, solAmount=${solAmount}, slippage=${slippage}, loopCount=${loopCount}, batchSize=${batchSize}, transactionId=${transactionId}`, 'make-market.log', 'make-market', 'INFO');

    // Wait for libraries
    if (!(await waitForLibraries())) {
        log_message('Required libraries not loaded for makeMarket', 'make-market.log', 'make-market', 'ERROR');
        updateTransaction(transactionId, { status: 'failed', error: 'Required libraries not loaded' });
        resultDiv.innerHTML = `<p style="color: red;"><strong>[${processName}]</strong> Error: Required libraries not loaded</p><button onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Xóa thông báo</button>`;
        resultDiv.classList.add('active');
        submitButton.disabled = false;
        await deletePrivateKey(transactionId);
        return false;
    }

    try {
        // Check required libraries
        if (typeof window.solanaWeb3 === 'undefined') {
            throw new Error('solanaWeb3 is not defined');
        }
        if (typeof window.bs58 === 'undefined') {
            throw new Error('bs58 library is not loaded');
        }
        if (typeof window.splToken === 'undefined') {
            throw new Error('splToken is not defined');
        }
        if (typeof window.axios === 'undefined') {
            throw new Error('axios is not defined');
        }

        // Check private_key status
        try {
            const isPending = await checkPrivateKeyStatus(transactionId);
            if (isPending) {
                throw new Error('This private key is currently running another process. Please wait for it to complete or use a different private key.');
            }
            log_message(`Private key is not running any pending process for transaction ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
        } catch (error) {
            updateTransaction(transactionId, { status: 'failed', error: `Failed to check private_key status: ${error.message}` });
            throw error;
        }

        // Fetch and decode private_key
        let decodedPrivateKey;
        try {
            decodedPrivateKey = await getDecryptedPrivateKey(transactionId);
            log_message(`Successfully fetched and decrypted private_key for transaction ${transactionId}`, 'make-market.log', 'make-market', 'INFO');
        } catch (error) {
            updateTransaction(transactionId, { status: 'failed', error: `Failed to fetch or decrypt private_key: ${error.message}` });
            throw error;
        }

        // Validate private key
        let keypair;
        try {
            const decodedKey = window.bs58.decode(decodedPrivateKey);
            if (decodedKey.length !== 64) {
                throw new Error(`Invalid private key length: ${decodedKey.length} bytes`);
            }
            keypair = window.solanaWeb3.Keypair.fromSecretKey(decodedKey);
            const publicKey = keypair.publicKey.toBase58();
            if (!/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(publicKey)) {
                throw new Error('Invalid public key format');
            }
        } catch (error) {
            log_message(`Invalid private key: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: `Invalid private key: ${error.message}` });
            throw error;
        }
        const wallet = { publicKey: keypair.publicKey, payer: keypair };

        // Validate token mint
        try {
            await window.splToken.getMint(connection, new window.solanaWeb3.PublicKey(tokenMint));
            log_message(`Token mint validated: ${tokenMint}`, 'make-market.log', 'make-market', 'INFO');
        } catch (error) {
            log_message(`Invalid token mint: ${tokenMint}, error: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'Invalid token mint' });
            throw new Error('Invalid token mint');
        }

        // Validate inputs
        if (!tokenMint.match(/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/)) {
            throw new Error('Invalid token address');
        }
        if (solAmount <= 0) {
            throw new Error('SOL amount must be positive');
        }
        if (slippage < 0) {
            throw new Error('Slippage must be non-negative');
        }
        if (loopCount < 1) {
            throw new Error('Loop count must be at least 1');
        }
        if (batchSize < 1 || batchSize > 10) {
            throw new Error('Batch size must be between 1 and 10');
        }

        updateTransaction(transactionId, { status: 'pending', current_loop: 1 });
        resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Starting market making with ${loopCount} loops in batches of ${batchSize}...</p><button onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Xóa thông báo</button>`;
        log_message(`Market making started for process: ${processName}`, 'make-market.log', 'make-market', 'INFO');

        // Process batches
        const totalBatches = Math.ceil(loopCount / batchSize);
        for (let batchIndex = 0; batchIndex < totalBatches; batchIndex++) {
            const startLoop = batchIndex * batchSize;
            const endLoop = Math.min(startLoop + batchSize, loopCount);
            const batchLoops = endLoop - startLoop;

            resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Processing batch ${batchIndex + 1}/${totalBatches} (loops ${startLoop + 1} to ${endLoop})</p>`;
            log_message(`Processing batch ${batchIndex + 1}/${totalBatches} with ${batchLoops} loops for process: ${processName}`, 'make-market.log', 'make-market', 'INFO');

            // Process transactions in parallel
            const batchPromises = [];
            for (let i = startLoop; i < endLoop; i++) {
                batchPromises.push(processTransactionPair(wallet, tokenMint, solAmount, slippage, delay, processName, transactionId, i));
            }

            try {
                const batchResults = await Promise.all(batchPromises.map(p => p.catch(e => ({ loopIndex: null, success: false, error: e }))));
                for (const result of batchResults) {
                    if (result.success) {
                        resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Loop ${result.loopIndex + 1}: Buy transaction: <a href="https://solscan.io/tx/${result.buyTxId}" target="_blank">${result.buyTxId}</a>, Sell transaction: <a href="https://solscan.io/tx/${result.sellTxId}" target="_blank">${result.sellTxId}</a></p>`;
                    } else {
                        resultDiv.innerHTML += `<p style="color: red;"><strong>[${processName}]</strong> Loop ${result.loopIndex + 1}: Error: ${result.error.message}</p>`;
                        log_message(`Batch ${batchIndex + 1} failed at loop ${result.loopIndex + 1}: ${result.error.message}`, 'make-market.log', 'make-market', 'ERROR');
                        updateTransaction(transactionId, { status: 'failed', error: `Batch ${batchIndex + 1} failed at loop ${result.loopIndex + 1}: ${result.error.message}` });
                        throw new Error(`Batch ${batchIndex + 1} failed at loop ${result.loopIndex + 1}: ${result.error.message}`);
                    }
                }
                resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Batch ${batchIndex + 1}/${totalBatches} completed</p>`;
                log_message(`Batch ${batchIndex + 1}/${totalBatches} completed for process: ${processName}`, 'make-market.log', 'make-market', 'INFO');
                updateTransaction(transactionId, { current_loop: endLoop });
            } catch (error) {
                throw error;
            }
        }

        resultDiv.innerHTML += `<p style="color: green;"><strong>[${processName}]</strong> Market making completed</p><button onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Xóa thông báo</button>`;
        log_message(`Market making completed for process: ${processName}`, 'make-market.log', 'make-market', 'INFO');
        updateTransaction(transactionId, { status: 'success' });
        return true;
    } catch (error) {
        resultDiv.innerHTML += `<p style="color: red;"><strong>[${processName}]</strong> Error: ${error.message}</p><button onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Xóa thông báo</button>`;
        log_message(`Error in makeMarket: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        updateTransaction(transactionId, { status: 'failed', error: error.message });
        throw error;
    } finally {
        submitButton.disabled = false;
        log_message(`makeMarket function ended for process: ${processName}`, 'make-market.log', 'make-market', 'DEBUG');
        await deletePrivateKey(transactionId);
    }
}

// Swap SOL to token
async function swapSOLtoToken(wallet, tokenMint, solAmount, slippage) {
    log_message(`Fetching quote for SOL to token swap: tokenMint=${tokenMint}, amount=${solAmount}, slippage=${slippage}`, 'make-market.log', 'make-market', 'DEBUG');
    try {
        // Fetch quote from Jupiter
        const quoteResponse = await window.axios.get(`${JUPITER_API}/quote`, {
            params: {
                inputMint: 'So11111111111111111111111111111111111111112', // SOL
                outputMint: tokenMint,
                amount: solAmount * 1_000_000_000, // Convert to lamports
                slippageBps: Math.floor(slippage * 100)
            }
        });
        if (!quoteResponse.data || !quoteResponse.data.outAmount) {
            log_message(`Insufficient liquidity for SOL to token swap: tokenMint=${tokenMint}`, 'make-market.log', 'make-market', 'ERROR');
            throw new Error('Insufficient liquidity for swap');
        }
        log_message(`Quote fetched for SOL to token swap: tokenMint=${tokenMint}, outAmount=${quoteResponse.data.outAmount}`, 'make-market.log', 'make-market', 'INFO');

        // Fetch serialized transaction
        log_message(`Requesting swap transaction for SOL to token`, 'make-market.log', 'make-market', 'DEBUG');
        const swapResponse = await window.axios.post(`${JUPITER_API}/swap`, {
            quoteResponse: quoteResponse.data,
            userPublicKey: wallet.publicKey.toBase58(),
            wrapAndUnwrapSol: true
        });

        // Deserialize transaction
        const swapTransactionBuf = Buffer.from(swapResponse.data.swapTransaction, 'base64');
        const transaction = window.solanaWeb3.Transaction.from(swapTransactionBuf);
        transaction.partialSign(wallet.payer);
        log_message(`Swap transaction prepared for SOL to token`, 'make-market.log', 'make-market', 'DEBUG');
        return transaction;
    } catch (error) {
        let errorMessage = error.message;
        if (errorMessage.includes('insufficient funds') || errorMessage.includes('NotEnoughBalance')) {
            errorMessage = 'Insufficient SOL balance to complete the transaction';
        }
        log_message(`Failed to get swap quote for SOL to token: ${errorMessage}`, 'make-market.log', 'make-market', 'ERROR');
        throw new Error(`Failed to get swap quote: ${errorMessage}`);
    }
}

// Swap token to SOL
async function swapTokentoSOL(wallet, tokenMint, slippage) {
    log_message(`Fetching token account for token: ${tokenMint}`, 'make-market.log', 'make-market', 'DEBUG');
    try {
        // Get associated token address
        const tokenAccount = await window.splToken.getAssociatedTokenAddress(
            new window.solanaWeb3.PublicKey(tokenMint),
            wallet.publicKey
        );
        const tokenBalance = await connection.getTokenAccountBalance(tokenAccount);
        const amount = tokenBalance.value.amount;
        log_message(`Token balance fetched: ${amount} for token: ${tokenMint}`, 'make-market.log', 'make-market', 'INFO');

        // Fetch quote from Jupiter
        log_message(`Fetching quote for token to SOL swap: tokenMint=${tokenMint}, amount=${amount}, slippage=${slippage}`, 'make-market.log', 'make-market', 'DEBUG');
        const quoteResponse = await window.axios.get(`${JUPITER_API}/quote`, {
            params: {
                inputMint: tokenMint,
                outputMint: 'So11111111111111111111111111111111111111112', // SOL
                amount: amount,
                slippageBps: Math.floor(slippage * 100)
            }
        });
        if (!quoteResponse.data || !quoteResponse.data.outAmount) {
            log_message(`Insufficient liquidity for token to SOL swap: tokenMint=${tokenMint}`, 'make-market.log', 'make-market', 'ERROR');
            throw new Error('Insufficient liquidity for swap');
        }
        log_message(`Quote fetched for token to SOL swap: tokenMint=${tokenMint}, outAmount=${quoteResponse.data.outAmount}`, 'make-market.log', 'make-market', 'INFO');

        // Fetch serialized transaction
        log_message(`Requesting swap transaction for token to SOL`, 'make-market.log', 'make-market', 'DEBUG');
        const swapResponse = await window.axios.post(`${JUPITER_API}/swap`, {
            quoteResponse: quoteResponse.data,
            userPublicKey: wallet.publicKey.toBase58(),
            wrapAndUnwrapSol: true
        });

        // Deserialize transaction
        const swapTransactionBuf = Buffer.from(swapResponse.data.swapTransaction, 'base64');
        const transaction = window.solanaWeb3.Transaction.from(swapTransactionBuf);
        transaction.partialSign(wallet.payer);
        log_message(`Swap transaction prepared for token to SOL`, 'make-market.log', 'make-market', 'DEBUG');
        return transaction;
    } catch (error) {
        let errorMessage = error.message;
        if (errorMessage.includes('insufficient funds') || errorMessage.includes('NotEnoughBalance')) {
            errorMessage = 'Insufficient token balance to complete the transaction';
        }
        log_message(`Failed to get swap quote for token to SOL: ${errorMessage}`, 'make-market.log', 'make-market', 'ERROR');
        throw new Error(`Failed to get swap quote: ${errorMessage}`);
    }
}
