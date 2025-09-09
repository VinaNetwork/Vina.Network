// ============================================================================
// File: mm/private-key/list-private-key.js
// Description: JavaScript cho trang danh sách private key
// Created by: Vina Network
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('list-private-key.js loaded');
    log_message('list-private-key.js loaded', 'private-key-page.log', 'make-market', 'DEBUG');

    // Sao chép public key
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

    // Xóa ví
    document.querySelectorAll('.deleteWallet').forEach(button => {
        button.addEventListener('click', async () => {
            const walletId = button.getAttribute('data-id');
            if (!confirm('Bạn có chắc chắn muốn xóa ví này?')) return;

            try {
                const csrfToken = await refreshCSRFToken();
                const response = await axios.post('/mm/delete-private-key', {
                    walletId,
                    csrf_token: csrfToken
                }, {
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
                }
            } catch (error) {
                showError(`Lỗi xóa ví: ${error.response?.data?.message || error.message}`);
            }
        });
    });
});

// Log message function
function log_message(message, log_file = 'private-key-page.log', module = 'make-market', log_type = 'INFO') {
    if (log_type === 'DEBUG' && (!window.ENVIRONMENT || window.ENVIRONMENT !== 'development')) {
        return;
    }
    axios.post('/mm/get-logs', { message, log_file, module, log_type, url: window.location.href, userAgent: navigator.userAgent }, {
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

// Hàm hiển thị thông báo thành công
function showSuccess(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>${message}</p>`;
    resultDiv.classList.add('active', 'success');
    setTimeout(() => {
        resultDiv.innerHTML = '';
        resultDiv.classList.remove('active', 'success');
    }, 3000);
}

// Hàm hiển thị lỗi
function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>${message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active', 'error');">Xóa thông báo</button>`;
    resultDiv.classList.add('active', 'error');
}
