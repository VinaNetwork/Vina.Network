// ============================================================================
// File: make-market/mm.js
// Description: JavaScript file for automated token trading on Solana using Jupiter API
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
  const resultDiv = document.getElementById('mm-result');
  const submitButton = document.querySelector('#makeMarketForm button');
  try {
    // Kiểm tra input
    if (!tokenMint.match(/^[A-Za-z0-9]{32,44}$/)) throw new Error('Invalid token address');
    if (solAmount <= 0) throw new Error('SOL amount must be positive');
    if (slippage < 0) throw new Error('Slippage must be non-negative');
    if (loopCount < 1) throw new Error('Loop count must be at least 1');

    // Decode private key
    const keypair = Keypair.fromSecretKey(bs58.decode(privateKey));
    const wallet = new Wallet(keypair);

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
  const resultDiv = document.getElementById('mm-result');
  const submitButton = document.querySelector('#makeMarketForm button');
  submitButton.disabled = true;
  resultDiv.innerHTML = '<p><strong>Processing...</strong></p>';

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

// Copy functionality for public_key
document.addEventListener('DOMContentLoaded', () => {
  console.log('mm.js loaded');

  // Copy functionality
  document.addEventListener('click', (e) => {
    console.log('Click event detected on:', e.target);
    const icon = e.target.closest('.copy-icon');
    if (!icon) {
      console.log('No copy-icon found for click');
      return;
    }

    console.log('Copy icon clicked:', icon);

    // Check HTTPS
    if (!window.isSecureContext) {
      console.error('Copy blocked: Not in secure context');
      alert('Unable to copy: This feature requires HTTPS');
      return;
    }

    // Get address from data-full
    const fullAddress = icon.getAttribute('data-full');
    if (!fullAddress) {
      console.error('Copy failed: data-full attribute not found or empty');
      alert('Unable to copy address: Invalid address');
      return;
    }

    // Validate address format (Base58) to prevent XSS
    const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
    if (!base58Regex.test(fullAddress)) {
      console.error('Invalid address format:', fullAddress);
      alert('Unable to copy: Invalid address format');
      return;
    }

    const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' + fullAddress.substring(fullAddress.length - 4) : 'Invalid';
    console.log('Attempting to copy address:', shortAddress);

    // Try Clipboard API
    if (navigator.clipboard && window.isSecureContext) {
      console.log('Using Clipboard API');
      navigator.clipboard.writeText(fullAddress).then(() => {
        showCopyFeedback(icon);
      }).catch(err => {
        console.error('Clipboard API failed:', err);
        fallbackCopy(fullAddress, icon);
      });
    } else {
      console.warn('Clipboard API unavailable, using fallback');
      fallbackCopy(fullAddress, icon);
    }
  });

  function fallbackCopy(text, icon) {
    const shortText = text.length >= 8 ? text.substring(0, 4) + '...' + text.substring(text.length - 4) : 'Invalid';
    console.log('Using fallback copy for:', shortText);
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '0';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    try {
      const success = document.execCommand('copy');
      console.log('Fallback copy result:', success);
      if (success) {
        showCopyFeedback(icon);
      } else {
        console.error('Fallback copy failed');
        alert('Unable to copy address: Copy error');
      }
    } catch (err) {
      console.error('Fallback copy error:', err);
      alert('Unable to copy address: ' + err.message);
    } finally {
      document.body.removeChild(textarea);
    }
  }

  function showCopyFeedback(icon) {
    console.log('Showing copy feedback');
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
    }, 2000);
    console.log('Copy successful');
  }
});
