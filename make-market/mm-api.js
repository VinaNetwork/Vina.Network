// ============================================================================
// File: make-market/mm-api.js
// Description: JavaScript file for automated token trading on Solana using Jupiter API
// Created by: Vina Network
// ============================================================================

// Cấu hình
const JUPITER_API = 'https://quote-api.jup.ag/v6';
const RPC_ENDPOINT = 'https://api.mainnet-beta.solana.com';
const connection = new solanaWeb3.Connection(RPC_ENDPOINT, 'confirmed');

// Hàm log_message
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

// Hàm cập nhật trạng thái giao dịch vào database
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

// Hàm chính xử lý make market
async function makeMarket(
    processName,
    privateKey,
    tokenMint,
    solAmount,
    slippage,
    delay,
    loopCount,
    transactionId
) {
    const resultDiv = document.getElementById('mm-result');
    const submitButton = document.querySelector('#makeMarketForm button');
    log_message(`Starting makeMarket: process=${processName}, tokenMint=${tokenMint}, solAmount=${solAmount}, slippage=${slippage}, loopCount=${loopCount}, transactionId=${transactionId}`, 'make-market.log', 'make-market', 'INFO');

    try {
        // Kiểm tra các thư viện cần thiết
        if (typeof window.solanaWeb3 === 'undefined') {
            log_message('solanaWeb3 is not defined', 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'solanaWeb3 is not defined' });
            throw new Error('solanaWeb3 is not defined');
        }
        if (typeof window.bs58 === 'undefined') {
            log_message('bs58 library is not loaded', 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'bs58 library is not loaded' });
            throw new Error('bs58 library is not loaded');
        }
        if (typeof window.anchor === 'undefined') {
            log_message('anchor is not defined', 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'anchor is not defined' });
            throw new Error('anchor is not defined');
        }
        if (typeof window.splToken === 'undefined') {
            log_message('splToken is not defined', 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'splToken is not defined' });
            throw new Error('splToken is not defined');
        }
        if (typeof window.axios === 'undefined') {
            log_message('axios is not defined', 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'axios is not defined' });
            throw new Error('axios is not defined');
        }

        // Kiểm tra private key
        let keypair;
        try {
            const decodedKey = window.bs58.decode(privateKey);
            if (decodedKey.length !== 64) {
                log_message(`Invalid private key length: ${decodedKey.length}, expected 64 bytes`, 'make-market.log', 'make-market', 'ERROR');
                updateTransaction(transactionId, { status: 'failed', error: `Invalid private key length: ${decodedKey.length} bytes` });
                throw new Error(`Invalid private key length: ${decodedKey.length} bytes`);
            }
            keypair = window.solanaWeb3.Keypair.fromSecretKey(decodedKey);
            const publicKey = keypair.publicKey.toBase58();
            if (!/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(publicKey)) {
                log_message(`Invalid public key format derived from private key`, 'make-market.log', 'make-market', 'ERROR');
                updateTransaction(transactionId, { status: 'failed', error: 'Invalid public key format' });
                throw new Error('Invalid public key format');
            }
        } catch (error) {
            log_message(`Invalid private key: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'Invalid private key' });
            throw new Error('Invalid private key');
        }
        const wallet = new window.anchor.Wallet(keypair);

        // Kiểm tra token mint
        try {
            await window.splToken.getMint(connection, new window.solanaWeb3.PublicKey(tokenMint));
            log_message(`Token mint validated: ${tokenMint}`, 'make-market.log', 'make-market', 'INFO');
        } catch (error) {
            log_message(`Invalid token mint: ${tokenMint}, error: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'Invalid token mint' });
            throw new Error('Invalid token mint');
        }

        // Kiểm tra số dư ví
        const balance = await connection.getBalance(wallet.publicKey);
        const requiredLamports = solAmount * 1_000_000_000 + 5000; // SOL + phí (~0.005 SOL)
        if (balance < requiredLamports) {
            log_message(`Insufficient balance: ${balance / 1_000_000_000} SOL, required: ${requiredLamports / 1_000_000_000} SOL`, 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: `Insufficient balance: ${balance / 1_000_000_000} SOL available, ${requiredLamports / 1_000_000_000} SOL required` });
            throw new Error('Insufficient balance');
        }
        log_message(`Balance validated: ${balance / 1_000_000_000} SOL available`, 'make-market.log', 'make-market', 'INFO');

        // Kiểm tra input
        if (!tokenMint.match(/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/)) {
            log_message(`Invalid token address: ${tokenMint}`, 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'Invalid token address' });
            throw new Error('Invalid token address');
        }
        if (solAmount <= 0) {
            log_message(`Invalid SOL amount: ${solAmount}`, 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'SOL amount must be positive' });
            throw new Error('SOL amount must be positive');
        }
        if (slippage < 0) {
            log_message(`Invalid slippage: ${slippage}`, 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'Slippage must be non-negative' });
            throw new Error('Slippage must be non-negative');
        }
        if (loopCount < 1) {
            log_message(`Invalid loop count: ${loopCount}`, 'make-market.log', 'make-market', 'ERROR');
            updateTransaction(transactionId, { status: 'failed', error: 'Loop count must be at least 1' });
            throw new Error('Loop count must be at least 1');
        }

        resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Starting market making...</p>`;
        log_message(`Market making started for process: ${processName}`, 'make-market.log', 'make-market', 'INFO');

        for (let i = 0; i < loopCount; i++) {
            resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Loop ${i + 1}/${loopCount}</p>`;
            log_message(`Starting loop ${i + 1}/${loopCount} for process: ${processName}`, 'make-market.log', 'make-market', 'INFO');

            // Thực hiện mua token
            log_message(`Initiating buy transaction for token: ${tokenMint}, amount: ${solAmount} SOL`, 'make-market.log', 'make-market', 'DEBUG');
            const buyTx = await swapSOLtoToken(wallet, tokenMint, solAmount, slippage);
            const buyTxId = await connection.sendRawTransaction(buyTx.serialize(), {
                skipPreflight: true
            });
            await connection.confirmTransaction(buyTxId);
            resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Buy transaction: <a href="https://solscan.io/tx/${buyTxId}" target="_blank">${buyTxId}</a></p>`;
            log_message(`Buy transaction completed: ${buyTxId}`, 'make-market.log', 'make-market', 'INFO');
            updateTransaction(transactionId, { buy_tx_id: buyTxId });

            // Delay giữa mua và bán
            if (delay > 0) {
                resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Waiting ${delay} seconds...</p>`;
                log_message(`Waiting ${delay} seconds before sell for process: ${processName}`, 'make-market.log', 'make-market', 'DEBUG');
                await new Promise(resolve => setTimeout(resolve, delay * 1000));
            }

            // Thực hiện bán token
            log_message(`Initiating sell transaction for token: ${tokenMint}`, 'make-market.log', 'make-market', 'DEBUG');
            const sellTx = await swapTokentoSOL(wallet, tokenMint, slippage);
            const sellTxId = await connection.sendRawTransaction(sellTx.serialize(), {
                skipPreflight: true
            });
            await connection.confirmTransaction(sellTxId);
            resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Sell transaction: <a href="https://solscan.io/tx/${sellTxId}" target="_blank">${sellTxId}</a></p>`;
            log_message(`Sell transaction completed: ${sellTxId}`, 'make-market.log', 'make-market', 'INFO');
            updateTransaction(transactionId, { sell_tx_id: sellTxId });
        }

        resultDiv.innerHTML += `<p style="color: green;"><strong>[${processName}]</strong> Market making completed</p>`;
        log_message(`Market making completed for process: ${processName}`, 'make-market.log', 'make-market', 'INFO');
        updateTransaction(transactionId, { status: 'success' });
        return true; // Trả về true để báo thành công
    } catch (error) {
        resultDiv.innerHTML += `<p style="color: red;"><strong>[${processName}]</strong> Error: ${error.message}</p>`;
        log_message(`Error in makeMarket: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        updateTransaction(transactionId, { status: 'failed', error: error.message });
        throw error; // Ném lại lỗi để mm.js xử lý
    } finally {
        submitButton.disabled = false;
        log_message(`makeMarket function ended for process: ${processName}`, 'make-market.log', 'make-market', 'DEBUG');
    }
}

// Hàm swap SOL sang token
async function swapSOLtoToken(wallet, tokenMint, solAmount, slippage) {
    log_message(`Fetching quote for SOL to token swap: tokenMint=${tokenMint}, amount=${solAmount}, slippage=${slippage}`, 'make-market.log', 'make-market', 'DEBUG');
    try {
        // Lấy quote từ Jupiter
        const quoteResponse = await window.axios.get(`${JUPITER_API}/quote`, {
            params: {
                inputMint: 'So11111111111111111111111111111111111111112', // SOL
                outputMint: tokenMint,
                amount: solAmount * 1_000_000_000, // Convert to lamports
                slippageBps: Math.floor(slippage * 100)
            }
        });
        // Kiểm tra thanh khoản
        if (!quoteResponse.data || !quoteResponse.data.outAmount) {
            log_message(`Insufficient liquidity for SOL to token swap: tokenMint=${tokenMint}`, 'make-market.log', 'make-market', 'ERROR');
            throw new Error('Insufficient liquidity for swap');
        }
        log_message(`Quote fetched for SOL to token swap: tokenMint=${tokenMint}, outAmount=${quoteResponse.data.outAmount}`, 'make-market.log', 'make-market', 'INFO');

        // Lấy serialized transaction
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
        log_message(`Failed to get swap quote for SOL to token: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        throw new Error(`Failed to get swap quote: ${error.message}`);
    }
}

// Hàm swap token sang SOL
async function swapTokentoSOL(wallet, tokenMint, slippage) {
    log_message(`Fetching token account for token: ${tokenMint}`, 'make-market.log', 'make-market', 'DEBUG');
    try {
        // Lấy associated token address
        const tokenAccount = await window.splToken.getAssociatedTokenAddress(
            new window.solanaWeb3.PublicKey(tokenMint),
            wallet.publicKey
        );
        const tokenBalance = await connection.getTokenAccountBalance(tokenAccount);
        const amount = tokenBalance.value.amount;
        log_message(`Token balance fetched: ${amount} for token: ${tokenMint}`, 'make-market.log', 'make-market', 'INFO');

        // Lấy quote từ Jupiter
        log_message(`Fetching quote for token to SOL swap: tokenMint=${tokenMint}, amount=${amount}, slippage=${slippage}`, 'make-market.log', 'make-market', 'DEBUG');
        const quoteResponse = await window.axios.get(`${JUPITER_API}/quote`, {
            params: {
                inputMint: tokenMint,
                outputMint: 'So11111111111111111111111111111111111111112', // SOL
                amount: amount,
                slippageBps: Math.floor(slippage * 100)
            }
        });
        // Kiểm tra thanh khoản
        if (!quoteResponse.data || !quoteResponse.data.outAmount) {
            log_message(`Insufficient liquidity for token to SOL swap: tokenMint=${tokenMint}`, 'make-market.log', 'make-market', 'ERROR');
            throw new Error('Insufficient liquidity for swap');
        }
        log_message(`Quote fetched for token to SOL swap: tokenMint=${tokenMint}, outAmount=${quoteResponse.data.outAmount}`, 'make-market.log', 'make-market', 'INFO');

        // Lấy serialized transaction
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
        log_message(`Failed to get swap quote for token to SOL: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        throw new Error(`Failed to get swap quote: ${error.message}`);
    }
}
