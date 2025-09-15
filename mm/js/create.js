// ============================================================================
// File: mm/js/create.js
// Description: JavaScript file for form handling and validation on Make Market page
// Created by: Vina Network
// ============================================================================

// Log message function
async function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
    if (!authToken) {
        console.error('Log failed: authToken is missing');
        return;
    }
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

// Show error message
function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    let userFriendlyMessage = 'An error occurred while submitting the form. Please try again.';
    let parsedError = {};

    // Parse error message if it's a string or an object
    try {
        if (typeof message === 'string') {
            if (message.includes('{"status":"error"')) {
                parsedError = JSON.parse(message);
            } else {
                parsedError = { message: message }; // Treat as plain message
            }
        } else if (typeof message === 'object' && message !== null) {
            parsedError = message; // Use object directly
        }
    } catch (e) {
        console.error('Failed to parse error message:', e.message, message);
        log_message(`Failed to parse error message: ${e.message}, raw_message=${JSON.stringify(message)}`, 'make-market.log', 'make-market', 'ERROR');
    }

    // Log debug information
    console.log('showError called with message:', message, 'parsedError:', parsedError);
    log_message(`showError called with message: ${JSON.stringify(message)}, parsedError: ${JSON.stringify(parsedError)}`, 'make-market.log', 'make-market', 'DEBUG');

    // Extract message and errorCode from parsedError
    const errorMessage = parsedError.message || (typeof message === 'string' ? message : 'Unknown error');
    const errorCode = parsedError.errorCode || '';

    // Handle specific errors
    if (errorCode === 'TOKEN_NOT_TRADABLE') {
        userFriendlyMessage = 'The selected token is not tradable on Jupiter. Please choose a different token.';
    } else if (errorCode === 'INSUFFICIENT_LIQUIDITY') {
        userFriendlyMessage = 'The token pool has insufficient liquidity. Please try a different token or adjust your transaction.';
    } else if (errorCode === 'NO_ROUTE_FOUND') {
        userFriendlyMessage = 'No trading route found for this token. Please try a different token or contact support.';
    } else if (errorMessage.includes('Insufficient SOL balance')) {
        userFriendlyMessage = `${errorMessage}. Please deposit more SOL to your wallet.`;
    } else if (errorMessage.includes('Connection error while checking wallet balance')) {
        userFriendlyMessage = `${errorMessage}. Please check your network connection or try again later.`;
    } else if (errorMessage.includes('Error checking token decimals')) {
        userFriendlyMessage = `${errorMessage}. Please verify the token mint address and try again.`;
    } else if (errorMessage.includes('Error checking token tradability')) {
        userFriendlyMessage = `${errorMessage}. Please try again or contact support.`;
    } else if (errorCode) {
        userFriendlyMessage = `Error: ${errorMessage} (Code: ${errorCode}). Please try again or contact support.`;
    } else if (errorMessage.includes('No private keys available')) {
        userFriendlyMessage = `${errorMessage}`;
        resultDiv.innerHTML = `<p>${userFriendlyMessage}</p><a href="/mm/add-private-key" class="cta-button">Add Private Key</a>`;
        resultDiv.classList.add('active');
        const submitButton = document.querySelector('#makeMarketForm button');
        if (submitButton) {
            submitButton.disabled = false;
        }
        log_message(`Client-side error displayed: ${userFriendlyMessage}`, 'make-market.log', 'make-market', 'ERROR');
        return;
    } else {
        userFriendlyMessage = errorMessage; // Fallback to raw message if no specific handling
    }

    resultDiv.innerHTML = `<p>${userFriendlyMessage}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear Notification</button>`;
    resultDiv.classList.add('active');
    const submitButton = document.querySelector('#makeMarketForm button');
    if (submitButton) {
        submitButton.disabled = false;
    }
    log_message(`Client-side error displayed: ${userFriendlyMessage}`, 'make-market.log', 'make-market', 'ERROR');
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

// Check for available wallets
async function checkWallets() {
    try {
        console.log('Calling /mm/check-wallets');
        log_message('Calling /mm/check-wallets', 'make-market.log', 'make-market', 'DEBUG');
        const response = await axios.get('/mm/check-wallets', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Auth-Token': authToken
            },
            withCredentials: true
        });
        if (response.status === 200 && response.data.status === 'success') {
            const walletCount = response.data.wallets.length;
            log_message(`Check wallets successful: found ${walletCount} active wallets`, 'make-market.log', 'make-market', 'INFO');
            console.log(`Check wallets successful: found ${walletCount} active wallets`);
            return walletCount > 0;
        }
        throw new Error(response.data.message || 'Failed to check wallets');
    } catch (error) {
        log_message(`Failed to check wallets: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Failed to check wallets:', error.message);
        return false;
    }
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
document.getElementById('makeMarketForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const resultDiv = document.getElementById('mm-result');
    const submitButton = document.querySelector('#makeMarketForm button');
    if (submitButton) {
        submitButton.disabled = true;
    }
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
        showError({ message: 'Failed to refresh CSRF token. Please reload the page and try again.' });
        if (submitButton) {
            submitButton.disabled = false;
        }
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
        showError({ message: 'Failed to fetch network configuration. Please reload the page.' });
        if (submitButton) {
            submitButton.disabled = false;
        }
        return;
    }

    const params = {
        processName: formData.get('processName')?.trim() || '',
        walletId: formData.get('walletId')?.trim() || '',
        tokenMint: formData.get('tokenMint')?.trim() || '',
        tradeDirection: formData.get('tradeDirection') || 'buy',
        solAmount: formData.get('tradeDirection') === 'sell' ? 0 : parseFloat(formData.get('solAmount')) || 0,
        tokenAmount: tokenAmountValue !== null && tokenAmountValue !== '' ? parseFloat(tokenAmountValue) : 0,
        slippage: parseFloat(formData.get('slippage')) || 0.5,
        delay: parseInt(formData.get('delay')) || 0,
        loopCount: parseInt(formData.get('loopCount')) || 1,
        batchSize: parseInt(formData.get('batchSize')) || 2,
        csrf_token: csrfToken,
        skipTokenCheck: formData.get('skipTokenCheck') === '1' ? 1 : 0,
        skipBalanceCheck: formData.get('skipBalanceCheck') === '1' ? 1 : 0
    };
    log_message(`Processed Form data: ${JSON.stringify(params)}`, 'make-market.log', 'make-market', 'DEBUG');
    console.log('Processed Form data:', params);

    // Client-side validation for required fields
    if (!params.processName) {
        log_message('Process name is empty', 'make-market.log', 'make-market', 'ERROR');
        showError({ message: 'Process name is required.' });
        console.error('Process name is empty');
        if (submitButton) {
            submitButton.disabled = false;
        }
        return;
    }
    if (!params.walletId) {
        log_message('Wallet selection is empty', 'make-market.log', 'make-market', 'ERROR');
        showError({ message: 'Please select a wallet.' });
        console.error('Wallet selection is empty');
        if (submitButton) {
            submitButton.disabled = false;
        }
        return;
    }
    if (!params.tokenMint) {
        log_message('Token mint is empty', 'make-market.log', 'make-market', 'ERROR');
        showError({ message: 'Token mint address is required.' });
        console.error('Token mint is empty');
        if (submitButton) {
            submitButton.disabled = false;
        }
        return;
    }
    if (!/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(params.tokenMint)) {
        log_message('Invalid token address', 'make-market.log', 'make-market', 'ERROR');
        showError({ message: 'Invalid token address. Please check again.' });
        console.error('Invalid token address');
        if (submitButton) {
            submitButton.disabled = false;
        }
        return;
    }
    if (params.tradeDirection === 'buy') {
        if (isNaN(params.solAmount) || params.solAmount <= 0) {
            log_message(`Invalid SOL amount for buy: ${params.solAmount}`, 'make-market.log', 'make-market', 'ERROR');
            showError({ message: 'SOL amount must be greater than 0 for buy transactions.' });
            console.error('Invalid SOL amount for buy');
            if (submitButton) {
                submitButton.disabled = false;
            }
            return;
        }
        if (params.tokenAmount !== 0) {
            log_message(`Invalid token amount for buy: ${params.tokenAmount}, must be exactly 0`, 'make-market.log', 'make-market', 'ERROR');
            showError({ message: 'Token amount must be exactly 0 for buy transactions.' });
            console.error('Invalid token amount for buy');
            if (submitButton) {
                submitButton.disabled = false;
            }
            return;
        }
    } else if (params.tradeDirection === 'sell') {
        if (isNaN(params.tokenAmount) || params.tokenAmount <= 0) {
            log_message(`Invalid token amount for sell: ${params.tokenAmount}, must be greater than 0`, 'make-market.log', 'make-market', 'ERROR');
            showError({ message: 'Token amount must be greater than 0 for sell transactions.' });
            console.error('Invalid token amount for sell');
            if (submitButton) {
                submitButton.disabled = false;
            }
            return;
        }
    } else if (params.tradeDirection === 'both') {
        if (isNaN(params.solAmount) || params.solAmount <= 0) {
            log_message(`Invalid SOL amount for both: ${params.solAmount}`, 'make-market.log', 'make-market', 'ERROR');
            showError({ message: 'SOL amount must be greater than 0 for both transactions.' });
            console.error('Invalid SOL amount for both');
            if (submitButton) {
                submitButton.disabled = false;
            }
            return;
        }
        if (isNaN(params.tokenAmount) || params.tokenAmount <= 0) {
            log_message(`Invalid token amount for both: ${params.tokenAmount}, must be greater than 0`, 'make-market.log', 'make-market', 'ERROR');
            showError({ message: 'Token amount must be greater than 0 for both transactions.' });
            console.error('Invalid token amount for both');
            if (submitButton) {
                submitButton.disabled = false;
            }
            return;
        }
    } else {
        log_message('Invalid trade direction', 'make-market.log', 'make-market', 'ERROR');
        showError({ message: 'Please select a valid trade direction (Buy, Sell, or Both).' });
        console.error('Invalid trade direction');
        if (submitButton) {
            submitButton.disabled = false;
        }
        return;
    }
    if (isNaN(params.slippage) || params.slippage < 0) {
        log_message(`Invalid slippage: ${params.slippage}`, 'make-market.log', 'make-market', 'ERROR');
        showError({ message: 'Slippage must be non-negative.' });
        console.error('Invalid slippage');
        if (submitButton) {
            submitButton.disabled = false;
        }
        return;
    }
    if (isNaN(params.loopCount) || params.loopCount < 1) {
        log_message(`Invalid loop count: ${params.loopCount}`, 'make-market.log', 'make-market', 'ERROR');
        showError({ message: 'Loop count must be at least 1.' });
        console.error('Invalid loop count');
        if (submitButton) {
            submitButton.disabled = false;
        }
        return;
    }
    if (isNaN(params.batchSize) || params.batchSize < 2 || params.batchSize > 10) {
        log_message(`Invalid batch size: ${params.batchSize}`, 'make-market.log', 'make-market', 'ERROR');
        showError({ message: 'Batch size must be between 2 and 10.' });
        console.error('Invalid batch size');
        if (submitButton) {
            submitButton.disabled = false;
        }
        return;
    }
    if (params.skipBalanceCheck || !isValidTradeDirection(params.tradeDirection, params.solAmount, params.tokenAmount)) {
        log_message(`Balance check skipped: skipBalanceCheck=${params.skipBalanceCheck}, validTradeDirection=${isValidTradeDirection(params.tradeDirection, params.solAmount, params.tokenAmount)}`, 'make-market.log', 'make-market', 'INFO');
    }

    try {
        // Submit form data
        const response = await axios.post('/mm/create-process', formData, {
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
            showError(response.data);
            if (submitButton) {
                submitButton.disabled = false;
            }
            return;
        }
        const result = response.data;
        if (result.status !== 'success') {
            log_message(`Form submission failed: ${result.message}, errorCode=${result.errorCode || 'none'}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Form submission failed:', result);
            showError(result);
            if (submitButton) {
                submitButton.disabled = false;
            }
            return;
        }
        log_message(`Form saved to database: transactionId=${result.transactionId}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Form saved to database: transactionId=', result.transactionId);
        const redirectUrl = result.redirect;
        if (!redirectUrl || !redirectUrl.includes('token=')) {
            log_message(`Invalid redirect URL: ${redirectUrl}, missing token parameter`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Invalid redirect URL:', redirectUrl);
            showError({ message: 'Invalid redirect URL. Please try again.' });
            if (submitButton) {
                submitButton.disabled = false;
            }
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
        showError(error.response?.data || { message: 'Error submitting form. Please try again.' });
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
});

// Dynamic input handling based on tradeDirection and wallet check
document.addEventListener('DOMContentLoaded', async () => {
    console.log('create.js loaded');
    log_message('create.js loaded', 'make-market.log', 'make-market', 'DEBUG');

    const tradeDirectionSelect = document.getElementById('tradeDirection');
    const solAmountInput = document.getElementById('solAmount');
    const tokenAmountInput = document.getElementById('tokenAmount');
    const solAmountLabel = document.querySelector('label[for="solAmount"]');
    const tokenAmountLabel = document.querySelector('label[for="tokenAmount"]');
    const submitButton = document.querySelector('#makeMarketForm button');

    // Check for available wallets
    if (submitButton) {
        const hasWallets = await checkWallets();
        if (!hasWallets) {
            submitButton.disabled = true;
            showError('No private keys available. Please add a private key first.');
        }
    }

    // Function to update field visibility and state
    function updateFields() {
        if (!tradeDirectionSelect) return;
        if (tradeDirectionSelect.value === 'buy') {
            tokenAmountInput.classList.add('hidden');
            tokenAmountLabel.classList.add('hidden');
            solAmountInput.classList.remove('hidden');
            solAmountLabel.classList.remove('hidden');
            tokenAmountInput.value = '0';
            tokenAmountInput.disabled = true;
            solAmountInput.disabled = false;
            solAmountInput.required = true;
            log_message('Token amount hidden and disabled, SOL amount shown and enabled for Buy direction', 'make-market.log', 'make-market', 'INFO');
        } else if (tradeDirectionSelect.value === 'sell') {
            solAmountInput.classList.add('hidden');
            solAmountLabel.classList.add('hidden');
            tokenAmountInput.classList.remove('hidden');
            tokenAmountLabel.classList.remove('hidden');
            solAmountInput.value = '0';
            solAmountInput.disabled = true;
            solAmountInput.required = false;
            tokenAmountInput.disabled = false;
            tokenAmountInput.required = true;
            log_message('SOL amount hidden and disabled, Token amount shown and enabled for Sell direction', 'make-market.log', 'make-market', 'INFO');
        } else if (tradeDirectionSelect.value === 'both') {
            solAmountInput.classList.remove('hidden');
            solAmountLabel.classList.remove('hidden');
            tokenAmountInput.classList.remove('hidden');
            tokenAmountLabel.classList.remove('hidden');
            solAmountInput.disabled = false;
            solAmountInput.required = true;
            tokenAmountInput.disabled = false;
            tokenAmountInput.required = true;
            log_message('SOL and Token amount inputs shown and enabled for Both direction', 'make-market.log', 'make-market', 'INFO');
        }
    }

    // Initialize fields on page load
    updateFields();

    // Update fields when trade direction changes
    if (tradeDirectionSelect) {
        tradeDirectionSelect.addEventListener('change', updateFields);
    }
});
