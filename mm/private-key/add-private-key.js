// ============================================================================
// File: mm/private-key/add-private-key.js
// Description: JavaScript file for form handling and validation
// Created by: Vina Network
// ============================================================================

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
