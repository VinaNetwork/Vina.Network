// ============================================================================
// File: mm/create-process/create.js
// Description: JavaScript file for form handling and validation on Make Market page
// Created by: Vina Network
// ============================================================================

// Copy functionality
document.addEventListener('DOMContentLoaded', () => {
    console.log('create.js loaded');
    log_message('create.js loaded', 'make-market.log', 'make-market', 'DEBUG');

    const copyIcons = document.querySelectorAll('.copy-icon');
    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');
            log_message('Copy icon clicked', 'make-market.log', 'make-market', 'INFO');

            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);
            log_message(`Attempting to copy address: ${shortAddress}`, 'make-market.log', 'make-market', 'DEBUG');

            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
                log_message('Copy successful', 'make-market.log', 'make-market', 'INFO');
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
                    log_message('Copy feedback removed', 'make-market.log', 'make-market', 'DEBUG');
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                log_message(`Clipboard API failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });
});

// Log message function
function log_message(message, log_file = 'private-key-page.log', module = 'make-market', log_type = 'INFO') {
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

// Get SOLANA_NETWORK from server
async function getSolanaNetwork() {
    try {
        const response = await axios.get('/mm/get-network', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Auth-Token': authToken
            },
            withCredentials: true
        });
        if (response.status === 200 && response.data.network) {
            return response.data.network;
        }
        throw new Error('Failed to fetch SOLANA_NETWORK');
    } catch (error) {
        log_message(`Failed to fetch SOLANA_NETWORK: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        throw error;
    }
}

// Show error message
function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    if (message.includes('Insufficient SOL balance')) {
        resultDiv.innerHTML = `<p>${message}. Please deposit at least the required amount of SOL to your wallet or enable "Skip Balance Check" to proceed.</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear notification</button>`;
    } else if (message.includes('Connection error while checking wallet balance')) {
        resultDiv.innerHTML = `<p>${message}. Please check your network connection or try again later.</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear notification</button>`;
    } else if (message.includes('Error checking token decimals')) {
        resultDiv.innerHTML = `<p>${message}. Please verify the token mint address and try again.</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear notification</button>`;
    } else {
        resultDiv.innerHTML = `<p>${message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear notification</button>`;
    }
    resultDiv.classList.add('active');
    document.querySelector('#makeMarketForm button').disabled = false;
    log_message(`Client-side error displayed: ${message}`, 'make-market.log', 'make-market', 'ERROR');
}

// Function to validate Trade Direction conditions
function isValidTradeDirection(tradeDirection, solAmount, tokenAmount) {
    if (tradeDirection === 'buy') {
        return solAmount > 0 && tokenAmount === 0;
    }
    if (tradeDirection === 'sell') {
        return tokenAmount > 0;
    }
    if (tradeDirection === 'both') {
        return solAmount > 0 && tokenAmount > 0;
    }
    return false;
}

// Helper function to get cookie by name
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

// Handle form submission
document.getElementById('makeMarketForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const resultDiv = document.getElementById('mm-result');
    const submitButton = document.querySelector('#makeMarketForm button');
    submitButton.disabled = true;
    resultDiv.innerHTML = '<div class="spinner">Processing...</div>';
    resultDiv.classList.add('active');
    log_message('Form submitted', 'make-market.log', 'make-market', 'INFO');
    console.log('Form submitted');

    const formData = new FormData(e.target);
    let formDataObject = {};
    for (let [key, value] of formData.entries()) {
        formDataObject[key] = value;
    }
    log_message(`Raw FormData: ${JSON.stringify(formDataObject)}`, 'make-market.log', 'make-market', 'DEBUG');
    const tokenAmountValue = formData.get('tokenAmount');
    let csrfToken = formData.get('csrf_token');

    // Refresh CSRF token before submitting
    try {
        csrfToken = await refreshCSRFToken();
        formData.set('csrf_token', csrfToken);
        formDataObject.csrf_token = csrfToken;
        document.querySelector(`input[name="csrf_token"]`).value = csrfToken;
        log_message(`CSRF token refreshed before submit: ${csrfToken}`, 'make-market.log', 'make-market', 'INFO');
        console.log('CSRF token refreshed before submit:', csrfToken);
    } catch (error) {
        log_message(`Failed to refresh CSRF token: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to refresh CSRF token:', error.message);
        showError('Failed to refresh CSRF token. Please reload the page and try again.');
        submitButton.disabled = false;
        return;
    }

    // Get SOLANA_NETWORK
    let network;
    try {
        network = await getSolanaNetwork();
        log_message(`Fetched SOLANA_NETWORK: ${network}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Fetched SOLANA_NETWORK:', network);
    } catch (error) {
        log_message(`Failed to fetch SOLANA_NETWORK: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Failed to fetch network configuration. Please reload the page.');
        submitButton.disabled = false;
        return;
    }

    const params = {
        processName: formData.get('processName')?.trim() || '',
        privateKey: formData.get('privateKey')?.trim() || '',
        tokenMint: formData.get('tokenMint')?.trim() || '',
        tradeDirection: formData.get('tradeDirection') || 'buy',
        solAmount: formData.get('tradeDirection') === 'sell' ? 0 : parseFloat(formData.get('solAmount')) || 0,
        tokenAmount: tokenAmountValue !== null && tokenAmountValue !== '' ? parseFloat(tokenAmountValue) : 0,
        slippage: parseFloat(formData.get('slippage')) || 0.5,
        delay: parseInt(formData.get('delay')) || 0,
        loopCount: parseInt(formData.get('loopCount')) || 1,
        batchSize: parseInt(formData.get('batchSize')) || 2,
        csrf_token: csrfToken,
        skipBalanceCheck: formData.get('skipBalanceCheck') === '1' ? 1 : 0
    };
    log_message(`Processed Form data: ${JSON.stringify(params)}`, 'make-market.log', 'make-market', 'DEBUG');
    console.log('Processed Form data:', params);

    // Client-side validation for required fields
    if (!params.processName) {
        log_message('Process name is empty', 'make-market.log', 'make-market', 'ERROR');
        showError('Process name is required.');
        console.error('Process name is empty');
        submitButton.disabled = false;
        return;
    }
    if (!params.privateKey) {
        log_message('Private key is empty', 'make-market.log', 'make-market', 'ERROR');
        showError('Private key is required.');
        console.error('Private key is empty');
        submitButton.disabled = false;
        return;
    }
    if (!params.tokenMint) {
        log_message('Token mint is empty', 'make-market.log', 'make-market', 'ERROR');
        showError('Token mint address is required.');
        console.error('Token mint is empty');
        submitButton.disabled = false;
        return;
    }
    if (!/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{64,128}$/.test(params.privateKey)) {
        log_message('Private key is invalid format', 'make-market.log', 'make-market', 'ERROR');
        showError('Private key is invalid format. Please check again.');
        console.error('Private key is invalid format');
        submitButton.disabled = false;
        return;
    }
    if (!/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(params.tokenMint)) {
        log_message('Invalid token address', 'make-market.log', 'make-market', 'ERROR');
        showError('Invalid token address. Please check again.');
        console.error('Invalid token address');
        submitButton.disabled = false;
        return;
    }
    if (params.tradeDirection === 'buy') {
        if (isNaN(params.solAmount) || params.solAmount <= 0) {
            log_message(`Invalid SOL amount for buy: ${params.solAmount}`, 'make-market.log', 'make-market', 'ERROR');
            showError('SOL amount must be greater than 0 for buy transactions.');
            console.error('Invalid SOL amount for buy');
            submitButton.disabled = false;
            return;
        }
        if (params.tokenAmount !== 0) {
            log_message(`Invalid token amount for buy: ${params.tokenAmount}, must be exactly 0`, 'make-market.log', 'make-market', 'ERROR');
            showError('Token amount must be exactly 0 for buy transactions.');
            console.error('Invalid token amount for buy');
            submitButton.disabled = false;
            return;
        }
    } else if (params.tradeDirection === 'sell') {
        if (isNaN(params.tokenAmount) || params.tokenAmount <= 0) {
            log_message(`Invalid token amount for sell: ${params.tokenAmount}, must be greater than 0`, 'make-market.log', 'make-market', 'ERROR');
            showError('Token amount must be greater than 0 for sell transactions.');
            console.error('Invalid token amount for sell');
            submitButton.disabled = false;
            return;
        }
    } else if (params.tradeDirection === 'both') {
        if (isNaN(params.solAmount) || params.solAmount <= 0) {
            log_message(`Invalid SOL amount for both: ${params.solAmount}`, 'make-market.log', 'make-market', 'ERROR');
            showError('SOL amount must be greater than 0 for both transactions.');
            console.error('Invalid SOL amount for both');
            submitButton.disabled = false;
            return;
        }
        if (isNaN(params.tokenAmount) || params.tokenAmount <= 0) {
            log_message(`Invalid token amount for both: ${params.tokenAmount}, must be greater than 0`, 'make-market.log', 'make-market', 'ERROR');
            showError('Token amount must be greater than 0 for both transactions.');
            console.error('Invalid token amount for both');
            submitButton.disabled = false;
            return;
        }
    } else {
        log_message('Invalid trade direction', 'make-market.log', 'make-market', 'ERROR');
        showError('Please select a valid trade direction (Buy, Sell, or Both).');
        console.error('Invalid trade direction');
        submitButton.disabled = false;
        return;
    }
    if (isNaN(params.slippage) || params.slippage < 0) {
        log_message(`Invalid slippage: ${params.slippage}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Slippage must be non-negative.');
        console.error('Invalid slippage');
        submitButton.disabled = false;
        return;
    }
    if (isNaN(params.loopCount) || params.loopCount < 1) {
        log_message(`Invalid loop count: ${params.loopCount}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Loop count must be at least 1.');
        console.error('Invalid loop count');
        submitButton.disabled = false;
        return;
    }
    if (isNaN(params.batchSize) || params.batchSize < 2 || params.batchSize > 10) {
        log_message(`Invalid batch size: ${params.batchSize}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Batch size must be between 2 and 10.');
        console.error('Invalid batch size');
        submitButton.disabled = false;
        return;
    }
    // Log if balance check is skipped due to invalid trade direction or user choice
    if (params.skipBalanceCheck || !isValidTradeDirection(params.tradeDirection, params.solAmount, params.tokenAmount)) {
        log_message(`Balance check skipped: skipBalanceCheck=${params.skipBalanceCheck}, validTradeDirection=${isValidTradeDirection(params.tradeDirection, params.solAmount, params.tokenAmount)}`, 'make-market.log', 'make-market', 'INFO');
    }

    try {
        // Submit form data
        const response = await axios.post('/mm/create', formData, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
                'X-Auth-Token': authToken
            },
            withCredentials: true
        });
        log_message(`Form submission response: HTTP ${response.status}, Response: ${JSON.stringify(response.data)}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Form submission response:', response.data);
        if (response.status !== 200) {
            log_message(`Form submission failed: HTTP ${response.status}, Response: ${JSON.stringify(response.data)}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Form submission failed:', response.data);
            showError('Form submission failed. Please try again.');
            submitButton.disabled = false;
            return;
        }
        const result = response.data;
        if (result.status !== 'success') {
            log_message(`Form submission failed: ${result.message}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Form submission failed:', result.message);
            showError(result.message);
            submitButton.disabled = false;
            return;
        }
        log_message(`Form saved to database: transactionId=${result.transactionId}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Form saved to database: transactionId=', result.transactionId);
        const redirectUrl = result.redirect;
        if (!redirectUrl || !redirectUrl.includes('token=')) {
            log_message(`Invalid redirect URL: ${redirectUrl}, missing token parameter`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Invalid redirect URL:', redirectUrl);
            showError('Invalid redirect URL. Please try again.');
            submitButton.disabled = false;
            return;
        }
        log_message(`Redirecting to ${redirectUrl}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Redirecting to', redirectUrl);
        setTimeout(() => {
            window.location.href = redirectUrl;
            console.log('Redirect executed to', redirectUrl);
            log_message(`Redirect executed to ${redirectUrl}`, 'make-market.log', 'make-market', 'INFO');
        }, 100);
    } catch (error) {
        log_message(`Error submitting form: ${error.message}, response=${JSON.stringify(error.response?.data || 'none')}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Error submitting form:', error.message, error.response ? error.response.data : '');
        showError(`Error submitting form: ${error.response?.data?.message || error.message}. Please try again.`);
        submitButton.disabled = false;
    }
});

// Dynamic input handling based on tradeDirection
document.addEventListener('DOMContentLoaded', () => {
    console.log('mm.js loaded');
    log_message('mm.js loaded', 'make-market.log', 'make-market', 'DEBUG');

    const tradeDirectionSelect = document.getElementById('tradeDirection');
    const solAmountInput = document.getElementById('solAmount');
    const tokenAmountInput = document.getElementById('tokenAmount');

    tradeDirectionSelect.addEventListener('change', () => {
        if (tradeDirectionSelect.value === 'buy') {
            tokenAmountInput.value = '0';
            tokenAmountInput.disabled = true;
            solAmountInput.disabled = false;
            solAmountInput.required = true;
            log_message('Token amount set to 0 and disabled, SOL amount enabled for Buy direction', 'make-market.log', 'make-market', 'INFO');
        } else if (tradeDirectionSelect.value === 'sell') {
            solAmountInput.value = '0';
            solAmountInput.disabled = true;
            solAmountInput.required = false;
            tokenAmountInput.disabled = false;
            tokenAmountInput.required = true;
            log_message('SOL amount set to 0 and disabled, Token amount enabled for Sell direction', 'make-market.log', 'make-market', 'INFO');
        } else if (tradeDirectionSelect.value === 'both') {
            solAmountInput.disabled = false;
            solAmountInput.required = true;
            tokenAmountInput.disabled = false;
            tokenAmountInput.required = true;
            log_message('SOL and Token amount inputs enabled for Both direction', 'make-market.log', 'make-market', 'INFO');
        }
    });
});
