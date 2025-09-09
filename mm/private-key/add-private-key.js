// ============================================================================
// File: mm/private-key/add-private-key.js
// Description: JavaScript file for form handling and validation
// Created by: Vina Network
// ============================================================================

// Copy functionality
document.addEventListener('DOMContentLoaded', () => {
    console.log('mm.js loaded');
    log_message('mm.js loaded', 'private-key-page.log', 'make-market', 'DEBUG');

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

// Add Private key
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('privateKeysContainer');
    const addButton = document.getElementById('addPrivateKey');

    // Thêm private key mới
    addButton.addEventListener('click', () => {
        const newRow = document.createElement('div');
        newRow.className = 'privateKeyRow';
        newRow.innerHTML = `
            <label>Tên ví (tùy chọn):</label>
            <input type="text" name="walletNames[]" placeholder="Nhập tên ví...">
            <label>Private Key:</label>
            <textarea name="privateKeys[]" required placeholder="Nhập private key..."></textarea>
            <button type="button" class="removeKey">Xóa</button>
        `;
        container.appendChild(newRow);
        log_message('Thêm trường private key mới', 'private-key-page.log', 'make-market', 'INFO');
    });

    // Xóa private key
    container.addEventListener('click', (e) => {
        if (e.target.classList.contains('removeKey') && container.children.length > 1) {
            e.target.parentElement.remove();
            log_message('Xóa trường private key', 'private-key-page.log', 'make-market', 'INFO');
        }
    });

    // Xử lý gửi form
    document.getElementById('addPrivateKeyForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const submitButton = e.target.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        try {
            const csrfToken = await refreshCSRFToken();
            formData.set('csrf_token', csrfToken);
            const response = await axios.post('/mm/add-private-key', formData, {
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
            showError(`Lỗi gửi form: ${error.response?.data?.message || error.message}`);
        } finally {
            submitButton.disabled = false;
        }
    });

    // Xóa ví
    document.querySelectorAll('.deleteWallet').forEach(button => {
        button.addEventListener('click', async () => {
            const walletId = button.getAttribute('data-id');
            try {
                const csrfToken = await refreshCSRFToken();
                const response = await axios.post('/mm/delete-private-key', { walletId, csrf_token: csrfToken }, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken,
                        'X-Auth-Token': authToken
                    },
                    withCredentials: true
                });

                if (response.data.status === 'success') {
                    showSuccess('Xóa ví thành công');
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

function showSuccess(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>${message}</p>`;
    resultDiv.classList.add('active', 'success');
}

function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>${message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active', 'error');">Xóa thông báo</button>`;
    resultDiv.classList.add('active', 'error');
}
