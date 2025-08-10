// ============================================================================
// File: make-market/ui.js
// Description: JavaScript file for UI interactions (copy functionality) on Make Market page
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

// Show error message
function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>Error: ${message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear notification</button>`;
    resultDiv.classList.add('active');
    document.querySelector('#makeMarketForm button').disabled = false;
}

// Copy functionality for public_key
document.addEventListener('DOMContentLoaded', () => {
    console.log('ui.js loaded');
    log_message('ui.js loaded', 'make-market.log', 'make-market', 'DEBUG');

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
                showError('Unable to copy: This feature requires HTTPS');
                return;
            }

            const fullAddress = icon.getAttribute('data-full');
            if (!fullAddress) {
                log_message('Copy failed: data-full attribute not found or empty', 'make-market.log', 'make-market', 'ERROR');
                console.error('Copy failed: data-full attribute not found or empty');
                showError('Unable to copy: Invalid address');
                return;
            }

            const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
            if (!base58Regex.test(fullAddress)) {
                log_message(`Invalid address format: ${fullAddress}`, 'make-market.log', 'make-market', 'ERROR');
                console.error(`Invalid address format: ${fullAddress}`);
                showError('Unable to copy: Invalid address format');
                return;
            }

            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            log_message(`Attempting to copy address: ${shortAddress}`, 'make-market.log', 'make-market', 'DEBUG');
            console.log(`Attempting to copy address: ${shortAddress}`);

            if (!navigator.clipboard) {
                log_message('Clipboard API unavailable', 'make-market.log', 'make-market', 'ERROR');
                console.error('Clipboard API unavailable');
                showError('Unable to copy: Browser does not support this feature. Please copy manually.');
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
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });
});
