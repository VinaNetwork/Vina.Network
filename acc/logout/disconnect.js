// ============================================================================
// File: acc/logout/disconnect.js
// Description: JavaScript for handling user logout and Phantom wallet disconnection
// Created by: Vina Network
// ============================================================================

console.log('disconnect.js loaded');
log_message('disconnect.js loaded', 'accounts.log', 'accounts', 'DEBUG');

// authToken is passed from PHP
const authToken = window.authToken || null;

// Log message function
async function log_message(message, log_file = 'accounts.log', module = 'accounts', log_type = 'INFO') {
    if (!authToken) {
        console.error('Log failed: authToken is missing');
        return;
    }

    const sanitizedMessage = message.replace(/privateKey=[^\s]+/g, 'privateKey=[HIDDEN]');
    try {
        const response = await axios.post('/acc/write-logs', {
            message: sanitizedMessage,
            log_file,
            module,
            log_type,
            url: window.location.href,
            userAgent: navigator.userAgent,
            timestamp: new Date().toISOString()
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

// Function to show error messages
function showError(message) {
    console.log('Showing error:', message);
    log_message(`Showing error: ${message}`, 'accounts.log', 'accounts', 'ERROR');
    let walletInfo = document.getElementById('wallet-info');
    let statusSpan = document.getElementById('status');
    
    if (!walletInfo) {
        walletInfo = document.createElement('div');
        walletInfo.id = 'wallet-info';
        walletInfo.className = 'wallet-info'; 	// Add class for CSS styling
        document.querySelector('.acc-content').appendChild(walletInfo);
    }
    if (!statusSpan) {
        statusSpan = document.createElement('span');
        statusSpan.id = 'status';
        statusSpan.className = 'status-error'; 	// Add class for error styling
        walletInfo.appendChild(statusSpan);
    } else {
        statusSpan.className = 'status-error'; 	// Ensure error class is applied
    }
    
    statusSpan.textContent = message;
}

// Function to show success messages
function showSuccess(message) {
    console.log('Showing success:', message);
    log_message(`Showing success: ${message}`, 'accounts.log', 'accounts', 'INFO');
    let walletInfo = document.getElementById('wallet-info');
    let statusSpan = document.getElementById('status');
    
    if (!walletInfo) {
        walletInfo = document.createElement('div');
        walletInfo.id = 'wallet-info';
        walletInfo.className = 'wallet-info'; 		// Add class for CSS styling
        document.querySelector('.acc-content').appendChild(walletInfo);
    }
    if (!statusSpan) {
        statusSpan = document.createElement('span');
        statusSpan.id = 'status';
        statusSpan.className = 'status-success'; 	// Add class for success styling
        walletInfo.appendChild(statusSpan);
    } else {
        statusSpan.className = 'status-success'; 	// Ensure success class is applied
    }
    
    statusSpan.textContent = message;
}

// Disconnect wallet and redirect
document.addEventListener('DOMContentLoaded', async () => {
    if (typeof axios === 'undefined' || typeof solanaWeb3 === 'undefined') {
        console.error('Required libraries not loaded');
        await log_message('Required libraries not loaded (axios or solanaWeb3)', 'accounts.log', 'accounts', 'ERROR');
        showError('Failed to load required libraries, redirecting...');
        setTimeout(() => {
            window.location.href = '/acc/connect-p';
        }, 2000);
        return;
    }

    let walletInfo = document.getElementById('wallet-info');
    let statusSpan = document.getElementById('status');
    if (!walletInfo || !statusSpan) {
        console.error('DOM elements missing, creating dynamically');
        await log_message('DOM elements missing, creating dynamically', 'accounts.log', 'accounts', 'ERROR');
        walletInfo = document.createElement('div');
        walletInfo.id = 'wallet-info';
        walletInfo.className = 'wallet-info'; // Add class for CSS styling
        statusSpan = document.createElement('span');
        statusSpan.id = 'status';
        statusSpan.className = 'status-error'; // Default to error for DOM creation
        walletInfo.appendChild(statusSpan);
        document.querySelector('.acc-content').appendChild(walletInfo);
    }

    await log_message('Logout page loaded, attempting to disconnect wallet', 'accounts.log', 'accounts', 'DEBUG');

    try {
        if (window.solana && window.solana.isPhantom) {
            await log_message('Phantom wallet detected, attempting to disconnect', 'accounts.log', 'accounts', 'INFO');
            try {
                await window.solana.disconnect();
                await log_message('Phantom wallet disconnected successfully', 'accounts.log', 'accounts', 'INFO');
                showSuccess('Logout successful, redirecting...');
            } catch (disconnectError) {
                await log_message(`Failed to disconnect Phantom wallet: ${disconnectError.message}`, 'accounts.log', 'accounts', 'ERROR');
                showError(`Failed to disconnect wallet: ${disconnectError.message}`);
                setTimeout(() => {
                    window.location.href = '/acc/connect-p';
                }, 2000);
                return;
            }
        } else {
            await log_message('No Phantom wallet detected, skipping disconnect', 'accounts.log', 'accounts', 'INFO');
            showSuccess('Logout successful, redirecting...');
        }

        // Redirect to /acc/connect-p
        await log_message('Redirecting to /acc/connect-p after logout', 'accounts.log', 'accounts', 'INFO');
        setTimeout(() => {
            window.location.href = '/acc/connect-p';
        }, 1000);
    } catch (error) {
        await log_message(`Error during logout process: ${error.message}`, 'accounts.log', 'accounts', 'ERROR');
        showError(`Error during logout: ${error.message}`);
        setTimeout(() => {
            window.location.href = '/acc/connect-p';
        }, 2000);
    }
});
