// ============================================================================
// File: acc/users/profile.js
// Description: Script for managing profile page functionality for acc/profile.php.
// Created by: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('profile.js loaded');
    log_message('profile.js loaded', 'accounts.log', 'accounts', 'DEBUG');

    // Log message function
    async function log_message(message, log_file = 'accounts.log', module = 'accounts', log_type = 'INFO') {
        // Check authToken
        if (!authToken) {
            console.error('Log failed: authToken is missing');
            return;
        }

        // Filter sensitive information (if necessary)
        const sanitizedMessage = message.replace(/privateKey=[^\s]+/g, 'privateKey=[HIDDEN]');

        try {
            const response = await axios.post('/acc/write-logs', {
                message: sanitizedMessage,
                log_file,
                module,
                log_type,
                url: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString()
            }, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Auth-Token': authToken
                },
                withCredentials: true
            });

            if (response.status === 200 && response.data.status === 'success') {
                console.log(`Log sent successfully: ${sanitizedMessage}`);
            } else {
                console.error(`Log failed: HTTP ${response.status}, message=${response.data.message || response.statusText}`);
            }
        } catch (err) {
            console.error('Log error:', {
                message: err.message,
                status: err.response?.status,
                data: err.response?.data
            });
        }
    }

    // Copy Wallet address
    const copyIcons = document.querySelectorAll('.copy-icon');
    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');
            log_message('Copy icon clicked', 'accounts.log', 'accounts', 'INFO');

            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);
            log_message(`Attempting to copy address: ${shortAddress}`, 'accounts.log', 'accounts', 'DEBUG');

            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
                log_message('Copy successful', 'accounts.log', 'accounts', 'INFO');
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
                    console.log('Copy feedback removed');
                    log_message('Copy feedback removed', 'accounts.log', 'accounts', 'DEBUG');
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                log_message(`Clipboard API failed: ${err.message}`, 'accounts.log', 'accounts', 'ERROR');
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });

    // Function to show error messages
    function showError(message) {
        console.log('Showing error:', message);
        log_message(`Showing error: ${message}`, 'accounts.log', 'accounts', 'ERROR');
        const statusSpan = document.getElementById('status') || document.createElement('span');
        statusSpan.id = 'status';
        statusSpan.textContent = message;
        statusSpan.style.color = 'red';
        const walletInfo = document.getElementById('wallet-info') || document.createElement('div');
        walletInfo.id = 'wallet-info';
        walletInfo.style.display = 'block';
        if (!walletInfo.contains(statusSpan)) {
            walletInfo.appendChild(statusSpan);
        }
    }
});
