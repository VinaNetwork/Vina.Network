// ============================================================================
// File: manage/admin/adm.js
// Description: JavaScript file
// Created by: Vina Network
// ============================================================================

// DOM
document.addEventListener('DOMContentLoaded', () => {
    console.log('admin.js loaded');
    log_message('admin.js loaded', 'manage-admin.log', 'accounts', 'DEBUG');

    // Copy functionality
    const copyIcons = document.querySelectorAll('.copy-icon');
    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');
            log_message('Copy icon clicked', 'manage-admin.log', 'accounts', 'INFO');

            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);
            log_message(`Attempting to copy address: ${shortAddress}`, 'manage-admin.log', 'accounts', 'DEBUG');

            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
                log_message('Copy successful', 'manage-admin.log', 'accounts', 'INFO');
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
                    log_message('Copy feedback removed', 'manage-admin.log', 'accounts', 'DEBUG');
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                log_message(`Clipboard API failed: ${err.message}`, 'manage-admin.log', 'accounts', 'ERROR');
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });
});

// Log message function
function log_message(message, log_file = 'manage-admin.log', module = 'accounts', log_type = 'INFO') {
    if (log_type === 'DEBUG' && (!window.ENVIRONMENT || window.ENVIRONMENT !== 'development')) {
        return;
    }
    axios.post('/acc/get-logs', { message, log_file, module, log_type, url: window.location.href, userAgent: navigator.userAgent }, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-Auth-Token': authToken
        },
        withCredentials: true
    }).then(response => {
        if (response.status !== 200) {
            console.error(`Log failed: HTTP ${response.status}, response=${response.statusText}`);
        }
    }).catch(err => console.error('Log error:', err.message));
}
