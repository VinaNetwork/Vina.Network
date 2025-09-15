// mm/private-key/add-pk.js
// ============================================================================
// File: mm/private-key/add-pk.js
// Description: JavaScript file for form handling, validation, and client-side encryption
// Created by: Vina Network
// ============================================================================

// Hàm tạo khóa mã hóa ngẫu nhiên
async function generateEncryptionKey() {
    return await window.crypto.subtle.generateKey(
        {
            name: 'AES-GCM',
            length: 256
        },
        true, // Có thể xuất khóa
        ['encrypt', 'decrypt']
    );
}

// Hàm mã hóa private key
async function encryptPrivateKey(privateKey, encryptionKey) {
    const iv = window.crypto.getRandomValues(new Uint8Array(12)); // IV ngẫu nhiên cho AES-GCM
    const encodedPrivateKey = new TextEncoder().encode(privateKey);
    const encrypted = await window.crypto.subtle.encrypt(
        {
            name: 'AES-GCM',
            iv: iv
        },
        encryptionKey,
        encodedPrivateKey
    );
    return {
        iv: Array.from(iv), // Lưu IV để giải mã sau này
        ciphertext: Array.from(new Uint8Array(encrypted)), // Dữ liệu mã hóa
        authTag: Array.from(new Uint8Array(encrypted.slice(-16))) // Authentication tag (cho AES-GCM)
    };
}

// Hàm xuất khóa mã hóa để người dùng lưu trữ
async function exportEncryptionKey(encryptionKey) {
    const exportedKey = await window.crypto.subtle.exportKey('raw', encryptionKey);
    return Array.from(new Uint8Array(exportedKey));
}

// Log message function (giữ nguyên từ mã của bạn)
async function log_message(message, log_file = 'private-key-page.log', module = 'make-market', log_type = 'INFO') {
    // Loại bỏ authToken vì không nên nhúng JWT_SECRET vào client-side
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
                'X-Requested-With': 'XMLHttpRequest'
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

// Refresh CSRF token (giữ nguyên từ mã của bạn)
async function refreshCSRFToken() {
    const response = await axios.get('/mm/refresh-csrf', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        withCredentials: true
    });
    if (response.status !== 200 || !response.data.csrf_token) {
        throw new Error('Failed to refresh CSRF token');
    }
    return response.data.csrf_token;
}

// Handle form submission
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('privateKeysContainer');
    const addButton = document.getElementById('addPrivateKey');
    const form = document.getElementById('addPrivateKeyForm');

    // Add new private key field
    addButton.addEventListener('click', () => {
        const newRow = document.createElement('div');
        newRow.className = 'privateKeyRow';
        newRow.innerHTML = `
            <label><i class="fas fa-wallet"></i> Wallet name (optional):</label>
            <input type="text" name="walletNames[]" placeholder="Enter wallet name...">
            <label><i class="fas fa-key"></i> Private Key:</label>
            <textarea name="privateKeys[]" required placeholder="Enter private key..."></textarea>
            <button type="button" class="removeKey"><i class="fas fa-trash"></i> Delete</button>
        `;
        container.appendChild(newRow);
        log_message('Added new private key field', 'private-key-page.log', 'make-market', 'INFO');
    });

    // Remove private key field
    container.addEventListener('click', (e) => {
        if (e.target.classList.contains('removeKey') && container.children.length > 1) {
            e.target.parentElement.remove();
            log_message('Removed private key field', 'private-key-page.log', 'make-market', 'INFO');
        }
    });

    // Handle form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitButton = e.target.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        // Lấy danh sách private key và wallet name
        const privateKeyInputs = Array.from(container.querySelectorAll('textarea[name="privateKeys[]"]')).map(input => input.value.trim());
        const walletNames = Array.from(container.querySelectorAll('input[name="walletNames[]"]')).map(input => input.value.trim());
        const uniqueKeys = new Set(privateKeyInputs.filter(key => key !== ''));
        if (uniqueKeys.size < privateKeyInputs.length) {
            showError('There is a duplicate private key in the form');
            log_message('Duplicate private keys in form', 'private-key-page.log', 'make-market', 'ERROR');
            submitButton.disabled = false;
            return;
        }

        try {
            // Tạo khóa mã hóa
            const encryptionKey = await generateEncryptionKey();
            const exportedKey = await exportEncryptionKey(encryptionKey);

            // Mã hóa tất cả private key
            const encryptedKeys = [];
            for (let i = 0; i < privateKeyInputs.length; i++) {
                if (privateKeyInputs[i]) {
                    const encrypted = await encryptPrivateKey(privateKeyInputs[i], encryptionKey);
                    encryptedKeys.push({
                        walletName: walletNames[i] || `Wallet ${i}`,
                        iv: encrypted.iv,
                        ciphertext: encrypted.ciphertext,
                        authTag: encrypted.authTag
                    });
                }
            }

            // Yêu cầu người dùng lưu khóa mã hóa
            const keyBlob = new Blob([JSON.stringify(exportedKey)], { type: 'application/json' });
            const keyUrl = URL.createObjectURL(keyBlob);
            const downloadLink = document.createElement('a');
            downloadLink.href = keyUrl;
            downloadLink.download = 'encryption_key.json';
            downloadLink.textContent = 'Download your encryption key';
            document.getElementById('mm-result').innerHTML = `
                <p>Please download and securely store your encryption key. You will need it to decrypt your private keys later.</p>
            `;
            document.getElementById('mm-result').appendChild(downloadLink);
            document.getElementById('mm-result').classList.add('active', 'success');

            // Gửi dữ liệu mã hóa lên server
            const formData = new FormData();
            formData.append('csrf_token', await refreshCSRFToken());
            formData.append('encryptedKeys', JSON.stringify(encryptedKeys));

            const response = await axios.post('/mm/add-private-key', formData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': formData.get('csrf_token')
                },
                withCredentials: true
            });

            if (response.data.status === 'success') {
                showSuccess(response.data.message);
                container.innerHTML = `
                    <div class="privateKeyRow">
                        <label><i class="fas fa-wallet"></i> Wallet name (optional):</label>
                        <input type="text" name="walletNames[]" placeholder="Enter wallet name...">
                        <label><i class="fas fa-key"></i> Private Key:</label>
                        <textarea name="privateKeys[]" required placeholder="Enter private key..."></textarea>
                    </div>
                `;
                log_message('Private key added successfully, redirecting to list', 'private-key-page.log', 'make-market', 'INFO');
                setTimeout(() => window.location.href = '/mm/list-private-key', 1000);
            } else {
                showError(response.data.message);
                log_message(`Add private key failed: ${response.data.message}`, 'private-key-page.log', 'make-market', 'ERROR');
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message;
            showError(`Form submission error: ${errorMessage}`);
            log_message(`Form submission error: ${errorMessage}`, 'private-key-page.log', 'make-market', 'ERROR');
        } finally {
            submitButton.disabled = false;
        }
    });
});

function showSuccess(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>${message}</p>`;
    resultDiv.classList.add('active', 'success');
}

function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>${message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active', 'error');">Clear notification</button>`;
    resultDiv.classList.add('active', 'error');
}