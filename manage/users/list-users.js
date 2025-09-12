// ============================================================================
// File: manage/users/list-users.js
// Description: JavaScript file of list users.
// Created by: Vina Network
// ============================================================================

// DOM
document.addEventListener('DOMContentLoaded', () => {
    console.log('list-users.js loaded');
    log_message('list-users.js loaded', 'manage-users.log', 'accounts', 'DEBUG');

    // Copy functionality
    const copyIcons = document.querySelectorAll('.copy-icon');
    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');
            log_message('Copy icon clicked', 'manage-users.log', 'accounts', 'INFO');

            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);
            log_message(`Attempting to copy address: ${shortAddress}`, 'manage-users.log', 'accounts', 'DEBUG');

            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
                log_message('Copy successful', 'manage-users.log', 'accounts', 'INFO');
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
                    log_message('Copy feedback removed', 'manage-users.log', 'accounts', 'DEBUG');
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                log_message(`Clipboard API failed: ${err.message}`, 'manage-users.log', 'accounts', 'ERROR');
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });
});

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
        const response = await axios.post('/manage/write-logs', {
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
