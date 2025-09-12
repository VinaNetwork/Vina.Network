// ============================================================================
// File: mm/private-key/list/list-pk.js
// Description: JavaScript for private key list page
// Created by: Vina Network
// ============================================================================

// Log message function
async function log_message(message, log_file = 'private-key-page.log', module = 'make-market', log_type = 'INFO') {
    // Check authToken
    if (!authToken) {
        console.error('Log failed: authToken is missing');
        return;
    }

    // Filter sensitive information (if necessary)
    const sanitizedMessage = message.replace(/privateKey=[^\s]+/g, 'privateKey=[HIDDEN]');

    try {
        const response = await axios.post('/mm/write-logs', {
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

// Refresh CSRF token
async function refreshCSRFToken() {
    const response = await axios.get('/mm/refresh-csrf', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-Auth-Token': authToken
        },
        withCredentials: true
    });
    if (response.status !== 200 || !response.data.csrf_token) {
        throw new Error('Failed to refresh CSRF token');
    }
    return response.data.csrf_token;
}

// Function to show success message
function showSuccess(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>${message}</p>`;
    resultDiv.classList.add('active', 'success');
    setTimeout(() => {
        resultDiv.innerHTML = '';
        resultDiv.classList.remove('active', 'success');
    }, 3000);
}

// Function to show error
function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>${message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active', 'error');">Clear notification</button>`;
    resultDiv.classList.add('active', 'error');
}

// DOM
document.addEventListener('DOMContentLoaded', () => {
    console.log('list-private-key.js loaded');
    log_message('list-private-key.js loaded', 'private-key-page.log', 'make-market', 'DEBUG');

    // Copy public key
    const copyIcons = document.querySelectorAll('.copy-icon');
    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');
            log_message('Copy icon clicked', 'private-key-page.log', 'make-market', 'INFO');

            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);
            log_message(`Attempting to copy address: ${shortAddress}`, 'private-key-page.log', 'make-market', 'DEBUG');

            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
                log_message('Copy successful', 'private-key-page.log', 'make-market', 'INFO');
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
                    log_message('Copy feedback removed', 'private-key-page.log', 'make-market', 'DEBUG');
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                log_message(`Clipboard API failed: ${err.message}`, 'private-key-page.log', 'make-market', 'ERROR');
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });

    // Delete wallet
    document.querySelectorAll('.deleteWallet').forEach(button => {
        button.addEventListener('click', async () => {
            const walletId = button.getAttribute('data-id');
            console.log(`Attempting to delete wallet with ID: ${walletId || 'null'}`);
            log_message(`Attempting to delete wallet with ID: ${walletId || 'null'}`, 'private-key-page.log', 'make-market', 'DEBUG');

            if (!walletId) {
                showError('Invalid wallet ID');
                log_message('No walletId found in button data-id', 'private-key-page.log', 'make-market', 'ERROR');
                return;
            }

            if (!confirm('Are you sure you want to delete this wallet?')) return;

            try {
                const csrfToken = await refreshCSRFToken();
                console.log(`Sending delete request for walletId: ${walletId}`);
                log_message(`Sending delete request for walletId: ${walletId}`, 'private-key-page.log', 'make-market', 'DEBUG');

                const formData = new FormData();
                formData.append('walletId', walletId);
                formData.append('csrf_token', csrfToken);

                const response = await axios.post('/mm/delete-private-key', formData, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken,
                        'X-Auth-Token': authToken
                    },
                    withCredentials: true
                });

                if (response.data.status === 'success') {
                    showSuccess(response.data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showError(response.data.message);
                    log_message(`Delete failed: ${response.data.message}`, 'private-key-page.log', 'make-market', 'ERROR');
                }
            } catch (error) {
                const errorMessage = error.response?.data?.message || error.message;
                showError(`Error deleting wallet: ${errorMessage}`);
                log_message(`Error deleting wallet: ${errorMessage}`, 'private-key-page.log', 'make-market', 'ERROR');
            }
        });
    });
});
