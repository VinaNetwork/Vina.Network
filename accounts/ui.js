// ============================================================================
// File: accounts/ui.js
// Description: JavaScript file for UI interactions (copy functionality) on Accounts page
// Created by: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('acc.js loaded');
    
    // Copy functionality for public_key
    document.addEventListener('click', function(e) {
        const icon = e.target.closest('.copy-icon');
        if (!icon) return;

        // Check HTTPS
        if (!window.isSecureContext) {
            logToServer('Copy blocked: Not in secure context', 'ERROR');
            alert('Unable to copy: This feature requires HTTPS');
            return;
        }

        console.log('Copy icon clicked:', icon);

        // Get address from data-full
        const fullAddress = icon.getAttribute('data-full');
        if (!fullAddress) {
            console.error('Copy failed: data-full attribute not found or empty');
            logToServer('Copy failed: data-full attribute not found or empty', 'ERROR');
            alert('Unable to copy address: Invalid address');
            return;
        }

        // Validate address format (Base58) to prevent XSS
        const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
        if (!base58Regex.test(fullAddress)) {
            console.error('Invalid address format:', fullAddress);
            logToServer(`Copy blocked: Invalid address format in data-full: ${fullAddress.substring(0, 8)}...`, 'ERROR');
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
                logToServer(`Copied public_key: ${shortAddress}`, 'INFO');
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
                logToServer(`Copied public_key: ${shortText}`, 'INFO');
            } else {
                console.error('Fallback copy failed');
                logToServer('Fallback copy failed', 'ERROR');
                alert('Unable to copy address: Copy error');
            }
        } catch (err) {
            console.error('Fallback copy error:', err);
            logToServer(`Fallback copy error: ${err.message}`, 'ERROR');
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
        }, 2000); // 2 seconds for clarity
        console.log('Copy successful');
    }
});
