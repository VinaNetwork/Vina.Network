// ============================================================================
// File: acc/profile-page/profile.js
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
            userAgent: navigator.userAgent
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

    // Handle logout form submission
    const checkForm = () => {
        let retryCount = 0;
        const maxRetries = 50;
        const tryCheckForm = () => {
            const logoutForm = document.querySelector('#logout-form');
            if (logoutForm) {
                console.log('Logout form found, attaching submit event');
                log_message('Logout form found, attaching submit event', 'accounts.log', 'accounts', 'INFO');
                logoutForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    console.log('Logout form submitted');
                    log_message('Logout form submitted', 'accounts.log', 'accounts', 'INFO');

                    try {
                        const formData = new FormData(logoutForm);

                        console.log('Sending logout request');
                        await log_message('Sending logout request', 'accounts.log', 'accounts', 'DEBUG');

                        const response = await fetch('/acc/disconnect', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-Auth-Token': authToken
                            },
                            body: JSON.stringify(Object.fromEntries(formData))
                        });

                        const result = await response.json();
                        await log_message(`Logout response: ${JSON.stringify(result)}`, 'accounts.log', 'accounts', result.status === 'error' ? 'ERROR' : 'INFO');

                        if (result.status === 'success') {
                            console.log('Logout successful, redirecting to:', result.redirect || '/acc/connect');
                            log_message(`Logout successful, redirecting to: ${result.redirect || '/acc/connect'}`, 'accounts.log', 'accounts', 'INFO');
                            window.location.href = result.redirect || '/acc/connect';
                        } else {
                            showError(result.message || 'Logout failed. Please try again.');
                        }
                    } catch (error) {
                        console.error('Error during logout:', error.message);
                        await log_message(`Error during logout: ${error.message}`, 'accounts.log', 'accounts', 'ERROR');
                        showError('Error during logout: ' + error.message);
                    }
                });
            } else if (retryCount < maxRetries) {
                console.warn('Logout form not found, retrying in 100ms');
                log_message('Logout form not found, retrying in 100ms', 'accounts.log', 'accounts', 'WARNING');
                retryCount++;
                setTimeout(tryCheckForm, 100);
            } else {
                console.error('Max retries reached, logout form not found');
                log_message('Max retries reached, logout form not found', 'accounts.log', 'accounts', 'ERROR');
            }
        };
        tryCheckForm();
    };

    checkForm();
});
