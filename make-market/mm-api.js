// ============================================================================
// File: make-market/mm-api.js
// Description: JavaScript file for automated token trading on Solana using Jupiter API
// Created by: Vina Network
// ============================================================================

// Cấu hình
const JUPITER_API = 'https://quote-api.jup.ag/v6';
const RPC_ENDPOINT = 'https://api.mainnet-beta.solana.com';
const connection = new solanaWeb3.Connection(RPC_ENDPOINT, 'confirmed');

// Hàm chính xử lý make market
async function makeMarket(
  processName,
  privateKey,
  tokenMint,
  solAmount,
  slippage,
  delay,
  loopCount
) {
  const resultDiv = document.getElementById('mm-result');
  const submitButton = document.querySelector('#makeMarketForm button');
  try {
    // Kiểm tra input
    if (!tokenMint.match(/^[A-Za-z0-9]{32,44}$/)) throw new Error('Invalid token address');
    if (solAmount <= 0) throw new Error('SOL amount must be positive');
    if (slippage < 0) throw new Error('Slippage must be non-negative');
    if (loopCount < 1) throw new Error('Loop count must be at least 1');

    // Decode private key
    const keypair = solanaWeb3.Keypair.fromSecretKey(bs58.decode(privateKey));
    const wallet = new anchor.Wallet(keypair);

    resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Starting market making...</p>`;

    for (let i = 0; i < loopCount; i++) {
      resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Loop ${i + 1}/${loopCount}</p>`;

      // Thực hiện mua token
      const buyTx = await swapSOLtoToken(wallet, tokenMint, solAmount, slippage);
      const buyTxId = await connection.sendRawTransaction(buyTx.serialize(), {
        skipPreflight: true
      });
      await connection.confirmTransaction(buyTxId);
      resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Buy transaction: <a href="https://solscan.io/tx/${buyTxId}" target="_blank">${buyTxId}</a></p>`;

      // Delay giữa mua và bán
      if (delay > 0) {
        resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Waiting ${delay} seconds...</p>`;
        await new Promise(resolve => setTimeout(resolve, delay * 1000));
      }

      // Thực hiện bán token
      const sellTx = await swapTokentoSOL(wallet, tokenMint, slippage);
      const sellTxId = await connection.sendRawTransaction(sellTx.serialize(), {
        skipPreflight: true
      });
      await connection.confirmTransaction(sellTxId);
      resultDiv.innerHTML += `<p><strong>[${processName}]</strong> Sell transaction: <a href="https://solscan.io/tx/${sellTxId}" target="_blank">${sellTxId}</a></p>`;
    }

    resultDiv.innerHTML += `<p style="color: green;"><strong>[${processName}]</strong> Market making completed</p>`;
  } catch (error) {
    resultDiv.innerHTML += `<p style="color: red;"><strong>[${processName}]</strong> Error: ${error.message}</p>`;
  } finally {
    submitButton.disabled = false;
  }
}

// Hàm swap SOL sang token
async function swapSOLtoToken(wallet, tokenMint, solAmount, slippage) {
  try {
    // Lấy quote từ Jupiter
    const quoteResponse = await axios.get(`${JUPITER_API}/quote`, {
      params: {
        inputMint: 'So11111111111111111111111111111111111111112', // SOL
        outputMint: tokenMint,
        amount: solAmount * 1_000_000_000, // Convert to lamports
        slippageBps: Math.floor(slippage * 100)
      }
    });

    // Lấy serialized transaction
    const swapResponse = await axios.post(`${JUPITER_API}/swap`, {
      quoteResponse: quoteResponse.data,
      userPublicKey: wallet.publicKey.toBase58(),
      wrapAndUnwrapSol: true
    });

    // Deserialize transaction
    const swapTransactionBuf = Buffer.from(swapResponse.data.swapTransaction, 'base64');
    const transaction = solanaWeb3.Transaction.from(swapTransactionBuf);
    transaction.partialSign(wallet.payer);
    return transaction;
  } catch (error) {
    throw new Error(`Failed to get swap quote: ${error.message}`);
  }
}

// Hàm swap token sang SOL
async function swapTokentoSOL(wallet, tokenMint, slippage) {
  try {
    // Lấy associated token address
    const tokenAccount = await splToken.getAssociatedTokenAddress(
      new solanaWeb3.PublicKey(tokenMint),
      wallet.publicKey
    );
    const tokenBalance = await connection.getTokenAccountBalance(tokenAccount);
    const amount = tokenBalance.value.amount;

    // Lấy quote từ Jupiter
    const quoteResponse = await axios.get(`${JUPITER_API}/quote`, {
      params: {
        inputMint: tokenMint,
        outputMint: 'So11111111111111111111111111111111111111112', // SOL
        amount: amount,
        slippageBps: Math.floor(slippage * 100)
      }
    });

    // Lấy serialized transaction
    const swapResponse = await axios.post(`${JUPITER_API}/swap`, {
      quoteResponse: quoteResponse.data,
      userPublicKey: wallet.publicKey.toBase58(),
      wrapAndUnwrapSol: true
    });

    // Deserialize transaction
    const swapTransactionBuf = Buffer.from(swapResponse.data.swapTransaction, 'base64');
    const transaction = solanaWeb3.Transaction.from(swapTransactionBuf);
    transaction.partialSign(wallet.payer);
    return transaction;
  } catch (error) {
    throw new Error(`Failed to get swap quote: ${error.message}`);
  }
}
