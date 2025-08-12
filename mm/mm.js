// ============================================================================
// File: mm/mm.js
// Description: JavaScript file for form handling and validation on Make Market page
// Created by: Vina Network
// ============================================================================

// Copy functionality
document.addEventListener('DOMContentLoaded', () => {
    console.log('ui.js loaded');
    const copyIcons = document.querySelectorAll('.copy-icon');
    copyIcons.forEach(icon => {
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked');

            const fullAddress = icon.getAttribute('data-full');
            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' : 'Invalid';
            console.log(`Attempting to copy address: ${shortAddress}`);

            navigator.clipboard.writeText(fullAddress).then(() => {
                console.log('Copy successful');
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
                }, 2000);
            }).catch(err => {
                console.error('Clipboard API failed:', err.message);
                showError(`Unable to copy: ${err.message}`);
            });
        });
    });
});

// Log message function
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
    if (log_type === 'DEBUG' && (!window.ENVIRONMENT || window.ENVIRONMENT !== 'development')) {
        return;
    }
    fetch('/make-market/log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, log_file, module, log_type })
    }).then(response => {
        if (!response.ok) {
            console.error(`Log failed: HTTP ${response.status}`);
        }
    }).catch(err => console.error('Log error:', err));
}

// Show error message
function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    resultDiv.innerHTML = `<p>Error: ${message}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Clear notification</button>`;
    resultDiv.classList.add('active');
    document.querySelector('#makeMarketForm button').disabled = false;
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
    const tokenAmountValue = formData.get('tokenAmount');
    log_message(`Raw tokenAmount value: ${tokenAmountValue}`, 'make-market.log', 'make-market', 'DEBUG');
    const params = {
        processName: formData.get('processName'),
        privateKey: formData.get('privateKey'),
        tokenMint: formData.get('tokenMint'),
        tradeDirection: formData.get('tradeDirection'),
        solAmount: formData.get('tradeDirection') === 'sell' ? 0 : parseFloat(formData.get('solAmount')) || 0,
        tokenAmount: tokenAmountValue !== null && tokenAmountValue !== '' ? parseFloat(tokenAmountValue) : 0,
        slippage: parseFloat(formData.get('slippage')) || 0.5,
        delay: parseInt(formData.get('delay')) || 0,
        loopCount: parseInt(formData.get('loopCount')) || 1,
        batchSize: parseInt(formData.get('batchSize')) || 5,
        csrf_token: formData.get('csrf_token'),
        skipBalanceCheck: formData.get('skipBalanceCheck') === '1' ? 1 : 0
    };
    log_message(`Form data: ${JSON.stringify(params)}`, 'make-market.log', 'make-market', 'DEBUG');
    console.log('Form data:', params);

    // Basic validation
    if (!params.privateKey || typeof params.privateKey !== 'string' || !/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{64,128}$/.test(params.privateKey)) {
        log_message('Private key is empty or invalid format', 'make-market.log', 'make-market', 'ERROR');
        showError('Private key is empty or invalid format. Please check again.');
        console.error('Private key is empty or invalid format');
        return;
    }
    if (!params.tokenMint || !/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(params.tokenMint)) {
        log_message('Invalid token address', 'make-market.log', 'make-market', 'ERROR');
        showError('Invalid token address. Please check again.');
        console.error('Invalid token address');
        return;
    }
    if (params.tradeDirection === 'buy') {
        if (isNaN(params.solAmount) || params.solAmount <= 0) {
            log_message(`Invalid SOL amount for buy: ${params.solAmount}`, 'make-market.log', 'make-market', 'ERROR');
            showError('SOL amount must be greater than 0 for buy transactions.');
            console.error('Invalid SOL amount for buy');
            return;
        }
        if (params.tokenAmount !== 0) {
            log_message(`Invalid token amount for buy: ${params.tokenAmount}, must be exactly 0`, 'make-market.log', 'make-market', 'ERROR');
            showError('Token amount must be exactly 0 for buy transactions.');
            console.error('Invalid token amount for buy');
            return;
        }
    } else if (params.tradeDirection === 'sell') {
        if (isNaN(params.tokenAmount) || params.tokenAmount <= 0) {
            log_message(`Invalid token amount for sell: ${params.tokenAmount}, must be greater than 0`, 'make-market.log', 'make-market', 'ERROR');
            showError('Token amount must be greater than 0 for sell transactions.');
            console.error('Invalid token amount for sell');
            return;
        }
    } else if (params.tradeDirection === 'both') {
        if (isNaN(params.solAmount) || params.solAmount <= 0) {
            log_message(`Invalid SOL amount for both: ${params.solAmount}`, 'make-market.log', 'make-market', 'ERROR');
            showError('SOL amount must be greater than 0 for both transactions.');
            console.error('Invalid SOL amount for both');
            return;
        }
        if (isNaN(params.tokenAmount) || params.tokenAmount <= 0) {
            log_message(`Invalid token amount for both: ${params.tokenAmount}, must be greater than 0`, 'make-market.log', 'make-market', 'ERROR');
            showError('Token amount must be greater than 0 for both transactions.');
            console.error('Invalid token amount for both');
            return;
        }
    } else {
        log_message('Invalid trade direction', 'make-market.log', 'make-market', 'ERROR');
        showError('Please select a valid trade direction (Buy, Sell, or Both).');
        console.error('Invalid trade direction');
        return;
    }
    if (isNaN(params.slippage) || params.slippage < 0) {
        log_message(`Invalid slippage: ${params.slippage}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Slippage must be non-negative.');
        console.error('Invalid slippage');
        return;
    }
    if (isNaN(params.loopCount) || params.loopCount < 1) {
        log_message(`Invalid loop count: ${params.loopCount}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Loop count must be at least 1.');
        console.error('Invalid loop count');
        return;
    }
    if (isNaN(params.batchSize) || params.batchSize < 1 || params.batchSize > 10) {
        log_message(`Invalid batch size: ${params.batchSize}`, 'make-market.log', 'make-market', 'ERROR');
        showError('Batch size must be between 1 and 10.');
        console.error('Invalid batch size');
        return;
    }

    // Log if balance check is skipped due to invalid trade direction or user choice
    if (params.skipBalanceCheck || !isValidTradeDirection(params.tradeDirection, params.solAmount, params.tokenAmount)) {
        log_message(`Balance check skipped: skipBalanceCheck=${params.skipBalanceCheck}, validTradeDirection=${isValidTradeDirection(params.tradeDirection, params.solAmount, params.tokenAmount)}`, 'make-market.log', 'make-market', 'INFO');
    }

    try {
        // Submit form data
        const response = await fetch('/make-market/', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const responseText = await response.text();
        log_message(`Form submission response: HTTP ${response.status}, Response: ${responseText}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Form submission response: HTTP', response.status, 'Response:', responseText);
        if (!response.ok) {
            log_message(`Form submission failed: HTTP ${response.status}, Response: ${responseText}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Form submission failed: HTTP', response.status, 'Response:', responseText);
            showError('Form submission failed. Please try again.');
            return;
        }
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (error) {
            log_message(`Error parsing JSON response: ${error.message}, Response: ${responseText}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Error parsing JSON response:', error.message, 'Response:', responseText);
            showError('Invalid response from server. Please try again.');
            return;
        }
        if (result.status !== 'success') {
            log_message(`Form submission failed: ${result.message}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Form submission failed:', result.message);
            showError(result.message); // Display detailed error message from server
            return;
        }
        log_message(`Form saved to database: transactionId=${result.transactionId}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Form saved to database: transactionId=', result.transactionId);
        // Redirect to process page
        const redirectUrl = result.redirect || `/make-market/process/${result.transactionId}`;
        log_message(`Redirecting to ${redirectUrl}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Redirecting to', redirectUrl);
        setTimeout(() => {
            window.location.href = redirectUrl;
            console.log('Redirect executed to', redirectUrl);
        }, 100);
    } catch (error) {
        log_message(`Error submitting form: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Error submitting form:', error.message);
        showError(`Error submitting form: ${error.message}. Please try again.`);
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
