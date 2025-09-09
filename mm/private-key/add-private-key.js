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

    // Add new private key
    addButton.addEventListener('click', () => {
        const newRow = document.createElement('div');
        newRow.className = 'privateKeyRow';
        newRow.innerHTML = `
            <label>Wallet name (optional):</label>
            <input type="text" name="walletNames[]" placeholder="Enter wallet name...">
            <label>Private Key:</label>
            <textarea name="privateKeys[]" required placeholder="Enter private key..."></textarea>
            <button type="button" class="removeKey">Remove</button>
        `;
        container.appendChild(newRow);
        log_message('Added new private key field', 'private-key-page.log', 'make-market', 'INFO');
    });

    // Remove private key
    container.addEventListener('click', (e) => {
        if (e.target.classList.contains('removeKey') && container.children.length > 1) {
            e.target.parentElement.remove();
            log_message('Removed private key field', 'private-key-page.log', 'make-market', 'INFO');
        }
    });

    // Handle form submission
    document.getElementById('addPrivateKeyForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const submitButton = e.target.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        // Check for duplicate private keys in form
        const privateKeyInputs = Array.from(container.querySelectorAll('textarea[name="privateKeys[]"]')).map(input => input.value.trim());
        const uniqueKeys = new Set(privateKeyInputs.filter(key => key !== ''));
        if (uniqueKeys.size < privateKeyInputs.length) {
            showError('Có private key trùng lặp trong form');
            submitButton.disabled = false;
            return;
        }

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
                // Reset form to one empty row
                container.innerHTML = `
                    <div class="privateKeyRow">
                        <label>Wallet name (optional):</label>
                        <input type="text" name="walletNames[]" placeholder="Enter wallet name...">
                        <label>Private Key:</label>
                        <textarea name="privateKeys[]" required placeholder="Enter private key..."></textarea>
                        <button type="button" class="removeKey">Remove</button>
                    </div>
                `;
                setTimeout(() => window.location.href = '/mm lust-private-key', 1000);
            } else {
                showError(response.data.message);
            }
        } catch (error) {
            showError(`Form submission error: ${error.response?.data?.message || error.message}`);
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
