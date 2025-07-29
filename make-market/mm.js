// ============================================================================
// File: make-market/mm.js
// Description: JavaScript file for UI interactions on Make Market page
// Created by: Vina Network
// ============================================================================

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

  // Tìm và gắn sự kiện trực tiếp cho .copy-icon
  const copyIcons = document.querySelectorAll('.copy-icon');
  console.log('Found copy icons:', copyIcons.length, copyIcons);
  if (copyIcons.length === 0) {
    console.error('No .copy-icon elements found in DOM');
    return;
  }

  copyIcons.forEach(icon => {
    console.log('Attaching click event to:', icon);
    icon.addEventListener('click', (e) => {
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
