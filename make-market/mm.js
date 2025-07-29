// ============================================================================
// File: make-market/mm.js
// Description: JavaScript file for UI interactions on Make Market page
// Created by: Vina Network
// ============================================================================

// Hàm log_message (gọi từ PHP qua inline script trong index.php)
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
    fetch('/log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, log_file, module, log_type })
    }).catch(err => console.error('Log error:', err));
}

// Xử lý form submit
document.getElementById('makeMarketForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const resultDiv = document.getElementById('mm-result');
  const submitButton = document.querySelector('#makeMarketForm button');
  submitButton.disabled = true;
  resultDiv.innerHTML = '<p><strong>Processing...</strong></p>';
  log_message('Form submitted', 'make-market.log', 'make-market', 'INFO');

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
  log_message(`Form data: processName=${params.processName}, tokenMint=${params.tokenMint}, solAmount=${params.solAmount}, slippage=${params.slippage}, delay=${params.delay}, loopCount=${params.loopCount}`, 'make-market.log', 'make-market', 'DEBUG');

  try {
    await makeMarket(
      params.processName,
      params.privateKey,
      params.tokenMint,
      params.solAmount,
      params.slippage,
      params.delay,
      params.loopCount
    );
    log_message('makeMarket called successfully', 'make-market.log', 'make-market', 'INFO');
  } catch (error) {
    log_message(`Error calling makeMarket: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
    resultDiv.innerHTML += `<p style="color: red;">Error: ${error.message}</p>`;
  }
});

// Copy functionality for public_key
document.addEventListener('DOMContentLoaded', () => {
  console.log('mm.js loaded');
  log_message('mm.js loaded', 'make-market.log', 'make-market', 'DEBUG');

  // Tìm và gắn sự kiện trực tiếp cho .copy-icon
  const copyIcons = document.querySelectorAll('.copy-icon');
  console.log('Found copy icons:', copyIcons.length, copyIcons);
  log_message(`Found ${copyIcons.length} copy icons`, 'make-market.log', 'make-market', 'DEBUG');
  if (copyIcons.length === 0) {
    console.error('No .copy-icon elements found in DOM');
    log_message('No .copy-icon elements found in DOM', 'make-market.log', 'make-market', 'ERROR');
    return;
  }

  copyIcons.forEach(icon => {
    console.log('Attaching click event to:', icon);
    log_message('Attaching click event to copy icon', 'make-market.log', 'make-market', 'DEBUG');
    icon.addEventListener('click', (e) => {
      console.log('Copy icon clicked:', icon);
      log_message('Copy icon clicked', 'make-market.log', 'make-market', 'INFO');

      // Check HTTPS
      if (!window.isSecureContext) {
        console.error('Copy blocked: Not in secure context');
        log_message('Copy blocked: Not in secure context', 'make-market.log', 'make-market', 'ERROR');
        alert('Unable to copy: This feature requires HTTPS');
        return;
      }

      // Get address from data-full
      const fullAddress = icon.getAttribute('data-full');
      if (!fullAddress) {
        console.error('Copy failed: data-full attribute not found or empty');
        log_message('Copy failed: data-full attribute not found or empty', 'make-market.log', 'make-market', 'ERROR');
        alert('Unable to copy address: Invalid address');
        return;
      }

      // Validate address format (Base58) to prevent XSS
      const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
      if (!base58Regex.test(fullAddress)) {
        console.error('Invalid address format:', fullAddress);
        log_message(`Invalid address format: ${fullAddress}`, 'make-market.log', 'make-market', 'ERROR');
        alert('Unable to copy: Invalid address format');
        return;
      }

      const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' + fullAddress.substring(fullAddress.length - 4) : 'Invalid';
      console.log('Attempting to copy address:', shortAddress);
      log_message(`Attempting to copy address: ${shortAddress}`, 'make-market.log', 'make-market', 'DEBUG');

      // Try Clipboard API
      if (navigator.clipboard && window.isSecureContext) {
        console.log('Using Clipboard API');
        log_message('Using Clipboard API', 'make-market.log', 'make-market', 'DEBUG');
        navigator.clipboard.writeText(fullAddress).then(() => {
          showCopyFeedback(icon);
        }).catch(err => {
          console.error('Clipboard API failed:', err);
          log_message(`Clipboard API failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
          fallbackCopy(fullAddress, icon);
        });
      } else {
        console.warn('Clipboard API unavailable, using fallback');
        log_message('Clipboard API unavailable, using fallback', 'make-market.log', 'make-market', 'DEBUG');
        fallbackCopy(fullAddress, icon);
      }
    });
  });

  function fallbackCopy(text, icon) {
    const shortText = text.length >= 8 ? text.substring(0, 4) + '...' + text.substring(text.length - 4) : 'Invalid';
    console.log('Using fallback copy for:', shortText);
    log_message(`Using fallback copy for: ${shortText}`, 'make-market.log', 'make-market', 'DEBUG');
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
      log_message(`Fallback copy result: ${success}`, 'make-market.log', 'make-market', 'DEBUG');
      if (success) {
        showCopyFeedback(icon);
      } else {
        console.error('Fallback copy failed');
        log_message('Fallback copy failed', 'make-market.log', 'make-market', 'ERROR');
        alert('Unable to copy address: Copy error');
      }
    } catch (err) {
      console.error('Fallback copy error:', err);
      log_message(`Fallback copy error: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
      alert('Unable to copy address: ' + err.message);
    } finally {
      document.body.removeChild(textarea);
    }
  }

  function showCopyFeedback(icon) {
    console.log('Showing copy feedback');
    log_message('Showing copy feedback', 'make-market.log', 'make-market', 'DEBUG');
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
    }, 2000);
    console.log('Copy successful');
    log_message('Copy successful', 'make-market.log', 'make-market', 'INFO');
  }
});
