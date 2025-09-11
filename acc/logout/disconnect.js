// ============================================================================
// File: acc/logout/disconnect.js
// Description: Script for managing disconnection
// Created by: Vina Network
// ============================================================================

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
                document.querySelector('.acc-content').appendChild(walletInfo);
            }
            if (!statusSpan) {
                statusSpan = document.createElement('span');
                statusSpan.id = 'status';
                walletInfo.appendChild(statusSpan);
            }
            
            statusSpan.textContent = message;
            statusSpan.style.color = 'red';
            walletInfo.style.display = 'block';
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
                document.querySelector('.acc-content').appendChild(walletInfo);
            }
            if (!statusSpan) {
                statusSpan = document.createElement('span');
                statusSpan.id = 'status';
                walletInfo.appendChild(statusSpan);
            }
            
            statusSpan.textContent = message;
            statusSpan.style.color = 'green';
            walletInfo.style.display = 'block';
        }

        // Disconnect wallet and redirect
        document.addEventListener('DOMContentLoaded', async () => {
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
                }, 3000);
            } catch (error) {
                await log_message(`Error during logout process: ${error.message}`, 'accounts.log', 'accounts', 'ERROR');
                showError(`Error during logout: ${error.message}`);
                setTimeout(() => {
                    window.location.href = '/acc/connect-p';
                }, 2000);
            }
        });
