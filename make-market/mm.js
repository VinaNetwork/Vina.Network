// ============================================================================
// File: make-market/mm.js
// Description: File JavaScript xử lý logic mua bán
// Created by: Vina Network
// ============================================================================

import { Connection, Keypair, Transaction, PublicKey } from 'https://cdn.jsdelivr.net/npm/@solana/web3.js@1.95.3/+esm';
import { Wallet } from 'https://cdn.jsdelivr.net/npm/@project-serum/anchor@0.26.0/dist/browser/index.js';
import { getAssociatedTokenAddress } from 'https://cdn.jsdelivr.net/npm/@solana/spl-token@0.4.8/+esm';
import axios from 'https://cdn.jsdelivr.net/npm/axios@1.7.7/+esm';
import bs58 from 'https://cdn.jsdelivr.net/npm/bs58@6.0.0/+esm';

// Cấu hình
const JUPITER_API = 'https://quote-api.jup.ag/v6';
const RPC_ENDPOINT = 'https://api.mainnet-beta.solana.com';
const connection = new Connection(RPC_ENDPOINT, 'confirmed');

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
  try {
    // Decode private key
    const keypair = Keypair.fromSecretKey(bs58.decode(privateKey));
    const wallet = new Wallet(keypair);

    document.getElementById('tx-result').innerHTML = `[${processName}] Starting market making...`;

    for (let i = 0; i < loopCount; i++) {
      document.getElementById('tx-result').innerHTML += `<br>[${processName}] Loop ${i + 1}/${loopCount}`;

      // Thực hiện mua token
      const buyTx = await swapSOLtoToken(wallet, tokenMint, solAmount, slippage);
      const buyTxId = await connection.sendRawTransaction(buyTx.serialize(), {
        skipPreflight: true
      });
      await connection.confirmTransaction(buyTxId);
      document.getElementById('tx-result').innerHTML += `<br>[${processName}] Buy transaction: <a href="https://solscan.io/tx/${buyTxId}" target="_blank">${buyTxId}</a>`;

      // Delay giữa mua và bán
      if (delay > 0) {
        document.getElementById('tx-result').innerHTML += `<br>[${processName}] Waiting ${delay} seconds...`;
        await new Promise(resolve => setTimeout(resolve, delay * 1000));
      }

      // Thực hiện bán token
      const sellTx = await swapTokentoSOL(wallet, tokenMint, slippage);
      const sellTxId = await connection.sendRawTransaction(sellTx.serialize(), {
        skipPreflight: true
      });
      await connection.confirmTransaction(sellTxId);
      document.getElementById('tx-result').innerHTML += `<br>[${processName}] Sell transaction: <a href="https://solscan.io/tx/${sellTxId}" target="_blank">${sellTxId}</a>`;
    }

    document.getElementById('tx-result').innerHTML += `<br><p style="color: green;">[${processName}] Market making completed</p>`;
  } catch (error) {
    document.getElementById('tx-result').innerHTML += `<br><p style="color: red;">[${processName}] Error: ${error.message}</p>`;
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
    const transaction = Transaction.from(swapTransactionBuf);
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
    const tokenAccount = await getAssociatedTokenAddress(
      new PublicKey(tokenMint),
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
    const transaction = Transaction.from(swapTransactionBuf);
    transaction.partialSign(wallet.payer);
    return transaction;
  } catch (error) {
    throw new Error(`Failed to get swap quote: ${error.message}`);
  }
}

// Xử lý form submit
document.getElementById('makeMarketForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const resultDiv = document.getElementById('tx-result');
  resultDiv.innerHTML = 'Processing...';

  const formData = new FormData(e.target);
  const params = {
    processName: formData.get('processName'),
    privateKey: formData.get('privateKey'),
    tokenMint: formData.get('tokenMint'),
    solAmount: parseFloat(formData.get('solAmount')),
    slippage: parseFloat(formData.get('slippage')),
    delay: parseInt(formData.get('delay')),
    loopCount: parseInt(formData.get('loopCount'))
  };

  await makeMarket(
    params.processName,
    params.privateKey,
    params.tokenMint,
    params.solAmount,
    params.slippage,
    params.delay,
    params.loopCount
  );
});
