// ============================================================================
// File: make-market/mm.js
// Description: JavaScript file for UI interactions on Make Market page
// Created by: Vina Network
// ============================================================================

// Hàm log_message
function log_message(message, log_file = 'make-market.log', module = 'make-market', log_type = 'INFO') {
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

// Hàm làm mới lịch sử giao dịch với phân trang
async function refreshTransactionHistory(page = 1, per_page = 10) {
    const resultDiv = document.getElementById('mm-result');
    const historyDiv = document.getElementById('transaction-history');
    try {
        const response = await fetch(`/make-market/history.php?page=${page}&per_page=${per_page}`);
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        const data = await response.json();
        log_message(`Fetched transaction history: ${data.transactions.length} records, page: ${page}, per_page: ${per_page}`, 'make-market.log', 'make-market', 'INFO');

        if (data.transactions.length === 0) {
            historyDiv.innerHTML = '<p>Chưa có giao dịch nào.</p>';
            return;
        }

        let html = `
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên tiến trình</th>
                        <th>Public Key</th>
                        <th>Token Address</th>
                        <th>SOL Amount</th>
                        <th>Slippage (%)</th>
                        <th>Delay (s)</th>
                        <th>Vòng lặp</th>
                        <th>Trạng thái</th>
                        <th>Buy Tx</th>
                        <th>Sell Tx</th>
                        <th>Thời gian</th>
                        <th>Lý do lỗi</th>
                    </tr>
                </thead>
                <tbody>
        `;
        data.transactions.forEach(tx => {
            const shortPublicKey = tx.public_key.substring(0, 4) + '...' + tx.public_key.substring(tx.public_key.length - 4);
            const shortTokenMint = tx.token_mint.substring(0, 4) + '...' + tx.token_mint.substring(tx.token_mint.length - 4);
            const shortBuyTx = tx.buy_tx_id ? tx.buy_tx_id.substring(0, 4) + '...' : '-';
            const shortSellTx = tx.sell_tx_id ? tx.sell_tx_id.substring(0, 4) + '...' : '-';
            const errorMessage = tx.error || '-';
            html += `
                <tr>
                    <td>${tx.id}</td>
                    <td>${tx.process_name}</td>
                    <td><a href="https://solscan.io/address/${tx.public_key}" target="_blank">${shortPublicKey}</a></td>
                    <td><a href="https://solscan.io/token/${tx.token_mint}" target="_blank">${shortTokenMint}</a></td>
                    <td>${tx.sol_amount}</td>
                    <td>${tx.slippage}</td>
                    <td>${tx.delay_seconds}</td>
                    <td>${tx.loop_count}</td>
                    <td>${tx.status}</td>
                    <td>${tx.buy_tx_id ? `<a href="https://solscan.io/tx/${tx.buy_tx_id}" target="_blank">${shortBuyTx}</a>` : '-'}</td>
                    <td>${tx.sell_tx_id ? `<a href="https://solscan.io/tx/${tx.sell_tx_id}" target="_blank">${shortSellTx}</a>` : '-'}</td>
                    <td>${tx.created_at}</td>
                    <td>${errorMessage}</td>
                </tr>
            `;
        });
        html += '</tbody></table>';

        // Thêm điều hướng phân trang
        const { current_page, total_pages, total_transactions } = data.pagination;
        html += `
            <div class="pagination">
                <p>Trang ${current_page}/${total_pages} (${total_transactions} giao dịch)</p>
                <div class="pagination-buttons">
                    <button class="pagination-btn" ${current_page === 1 ? 'disabled' : ''} onclick="refreshTransactionHistory(${current_page - 1}, ${per_page})">Previous</button>
                    ${generatePageNumbers(current_page, total_pages, per_page)}
                    <button class="pagination-btn" ${current_page === total_pages ? 'disabled' : ''} onclick="refreshTransactionHistory(${current_page + 1}, ${per_page})">Next</button>
                </div>
            </div>
        `;
        historyDiv.innerHTML = html;
    } catch (err) {
        log_message(`Error refreshing transaction history: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
        historyDiv.innerHTML = '<p>Lỗi khi tải lịch sử giao dịch.</p>';
        resultDiv.innerHTML = `<p style="color: red;">Error: ${err.message}</p>`;
        resultDiv.classList.add('active');
        setTimeout(() => {
            resultDiv.classList.remove('active');
            resultDiv.innerHTML = '';
        }, 5000);
    }
}

// Hàm tạo các nút số trang
function generatePageNumbers(current_page, total_pages, per_page) {
    let html = '';
    const maxButtons = 5;
    let startPage = Math.max(1, current_page - Math.floor(maxButtons / 2));
    let endPage = Math.min(total_pages, startPage + maxButtons - 1);

    if (endPage - startPage + 1 < maxButtons) {
        startPage = Math.max(1, endPage - maxButtons + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="pagination-btn${i === current_page ? ' active' : ''}" onclick="refreshTransactionHistory(${i}, ${per_page})">${i}</button>`;
    }
    return html;
}

// Xử lý form submit
document.getElementById('makeMarketForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const resultDiv = document.getElementById('mm-result');
    const submitButton = document.querySelector('#makeMarketForm button');
    submitButton.disabled = true;
    resultDiv.innerHTML = '<div class="spinner">Loading...</div>';
    resultDiv.classList.add('active');
    log_message('Form submitted', 'make-market.log', 'make-market', 'INFO');

    const formData = new FormData(e.target);
    const params = {
        processName: formData.get('processName'),
        privateKey: formData.get('privateKey'),
        tokenMint: formData.get('tokenMint'),
        solAmount: parseFloat(formData.get('solAmount')),
        slippage: parseFloat(formData.get('slippage')),
        delay: parseInt(formData.get('delay')),
        loopCount: parseInt(formData.get('loopCount')),
        csrf_token: formData.get('csrf_token')
    };
    log_message(`Form data: processName=${params.processName}, tokenMint=${params.tokenMint}, solAmount=${params.solAmount}, slippage=${params.slippage}, delay=${params.delay}, loopCount=${params.loopCount}`, 'make-market.log', 'make-market', 'DEBUG');

    // Kiểm tra privateKey
    if (!params.privateKey || typeof params.privateKey !== 'string' || params.privateKey.length < 1) {
        log_message('privateKey is empty or invalid', 'make-market.log', 'make-market', 'ERROR');
        resultDiv.innerHTML = '<p style="color: red;">Error: privateKey is empty or invalid</p>';
        resultDiv.classList.add('active');
        submitButton.disabled = false;
        setTimeout(() => {
            resultDiv.classList.remove('active');
            resultDiv.innerHTML = '';
        }, 5000);
        return;
    }
    log_message(`privateKey length: ${params.privateKey.length}`, 'make-market.log', 'make-market', 'DEBUG');

    // Validate privateKey and derive publicKey
    let transactionPublicKey;
    try {
        if (typeof window.bs58 === 'undefined') {
            log_message('bs58 library is not loaded', 'make-market.log', 'make-market', 'ERROR');
            throw new Error('bs58 library is not loaded');
        }
        const decodedKey = window.bs58.decode(params.privateKey);
        log_message(`Decoded privateKey length: ${decodedKey.length}`, 'make-market.log', 'make-market', 'DEBUG');
        if (decodedKey.length !== 64) {
            log_message(`Invalid private key length: ${decodedKey.length}, expected 64 bytes`, 'make-market.log', 'make-market', 'ERROR');
            throw new Error(`Invalid private key length: ${decodedKey.length} bytes, expected 64 bytes`);
        }
        const keypair = solanaWeb3.Keypair.fromSecretKey(decodedKey);
        transactionPublicKey = keypair.publicKey.toBase58();
        if (!/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(transactionPublicKey)) {
            log_message(`Invalid public key format derived from private key`, 'make-market.log', 'make-market', 'ERROR');
            throw new Error('Invalid public key format');
        }
    } catch (error) {
        log_message(`Invalid private key format: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        resultDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
        resultDiv.classList.add('active');
        submitButton.disabled = false;
        setTimeout(() => {
            resultDiv.classList.remove('active');
            resultDiv.innerHTML = '';
        }, 5000);
        return;
    }

    // Thêm transactionPublicKey vào formData
    formData.set('transactionPublicKey', transactionPublicKey);
    document.getElementById('transactionPublicKey').value = transactionPublicKey;

    try {
        // Gửi form data qua AJAX
        const response = await fetch('/make-market/', {
            method: 'POST',
            body: formData
        });
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        const result = await response.json();
        if (result.status !== 'success') {
            log_message(`Form submission failed: ${result.message}`, 'make-market.log', 'make-market', 'ERROR');
            throw new Error(result.message);
        }
        log_message(`Form saved to database: transactionId=${result.transactionId}`, 'make-market.log', 'make-market', 'INFO');

        // Gọi makeMarket với transactionId
        await makeMarket(
            params.processName,
            params.privateKey,
            params.tokenMint,
            params.solAmount,
            params.slippage,
            params.delay,
            params.loopCount,
            result.transactionId
        );

        // Chỉ log thành công nếu không có lỗi
        log_message('makeMarket called successfully', 'make-market.log', 'make-market', 'INFO');
        await refreshTransactionHistory(1, 10);
        resultDiv.innerHTML = '<p style="color: green;">Transaction submitted successfully!</p>';
        resultDiv.classList.add('active');
        setTimeout(() => {
            resultDiv.classList.remove('active');
            resultDiv.innerHTML = '';
        }, 5000);
    } catch (error) {
        log_message(`Error calling makeMarket: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        resultDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
        resultDiv.classList.add('active');
        setTimeout(() => {
            resultDiv.classList.remove('active');
            resultDiv.innerHTML = '';
        }, 5000);
        submitButton.disabled = false;
    }
});

// Copy functionality for public_key
document.addEventListener('DOMContentLoaded', () => {
    console.log('mm.js loaded');
    log_message('mm.js loaded', 'make-market.log', 'make-market', 'DEBUG');
    log_message(`bs58 available: ${typeof window.bs58 !== 'undefined' ? 'Yes' : 'No'}`, 'make-market.log', 'make-market', 'DEBUG');

    // Làm mới lịch sử giao dịch khi load trang (trang 1)
    refreshTransactionHistory(1, 10);

    // Tìm và gắn sự kiện trực tiếp cho .copy-icon
    const copyIcons = document.querySelectorAll('.copy-icon');
    console.log('Found copy icons:', copyIcons.length, copyIcons);
    log_message(`Found ${copyIcons.length} copy icons`, 'make-market.log', 'make-market', 'DEBUG');
    if (copyIcons.length === 0) {
        console.error('No .copy-icon elements found in DOM');
        log_message('No .copy-icon elements found in DOM', 'make-market.log', 'make-market', 'ERROR');
        return;
    }

    copyIcons.forEach(icon => {
        console.log('Attaching click event to:', icon);
        log_message('Attaching click event to copy icon', 'make-market.log', 'make-market', 'DEBUG');
        icon.addEventListener('click', (e) => {
            console.log('Copy icon clicked:', icon);
            log_message('Copy icon clicked', 'make-market.log', 'make-market', 'INFO');

            // Check HTTPS
            if (!window.isSecureContext) {
                console.error('Copy blocked: Not in secure context');
                log_message('Copy blocked: Not in secure context', 'make-market.log', 'make-market', 'ERROR');
                alert('Unable to copy: This feature requires HTTPS');
                return;
            }

            // Get address from data-full
            const fullAddress = icon.getAttribute('data-full');
            if (!fullAddress) {
                console.error('Copy failed: data-full attribute not found or empty');
                log_message('Copy failed: data-full attribute not found or empty', 'make-market.log', 'make-market', 'ERROR');
                alert('Unable to copy address: Invalid address');
                return;
            }

            // Validate address format (Base58) to prevent XSS
            const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
            if (!base58Regex.test(fullAddress)) {
                console.error('Invalid address format:', fullAddress);
                log_message(`Invalid address format: ${fullAddress}`, 'make-market.log', 'make-market', 'ERROR');
                alert('Unable to copy: Invalid address format');
                return;
            }

            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' + fullAddress.substring(fullAddress.length - 4) : 'Invalid';
            console.log('Attempting to copy address:', shortAddress);
            log_message(`Attempting to copy address: ${shortAddress}`, 'make-market.log', 'make-market', 'DEBUG');

            // Try Clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                console.log('Using Clipboard API');
                log_message('Using Clipboard API', 'make-market.log', 'make-market', 'DEBUG');
                navigator.clipboard.writeText(fullAddress).then(() => {
                    showCopyFeedback(icon);
                }).catch(err => {
                    console.error('Clipboard API failed:', err);
                    log_message(`Clipboard API failed: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
                    fallbackCopy(fullAddress, icon);
                });
            } else {
                console.warn('Clipboard API unavailable, using fallback');
                log_message('Clipboard API unavailable, using fallback', 'make-market.log', 'make-market', 'DEBUG');
                fallbackCopy(fullAddress, icon);
            }
        });
    });

    function fallbackCopy(text, icon) {
        const shortText = text.length >= 8 ? text.substring(0, 4) + '...' + text.substring(text.length - 4) : 'Invalid';
        console.log('Using fallback copy for:', shortText);
        log_message(`Using fallback copy for: ${shortText}`, 'make-market.log', 'make-market', 'DEBUG');
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '0';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            const success = document.execCommand('copy');
            console.log('Fallback copy result:', success);
            log_message(`Fallback copy result: ${success}`, 'make-market.log', 'make-market', 'DEBUG');
            if (success) {
                showCopyFeedback(icon);
            } else {
                console.error('Fallback copy failed');
                log_message('Fallback copy failed', 'make-market.log', 'make-market', 'ERROR');
                alert('Unable to copy address: Copy error');
            }
        } catch (err) {
            console.error('Fallback copy error:', err);
            log_message(`Fallback copy error: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
            alert('Unable to copy address: ' + err.message);
        } finally {
            document.body.removeChild(textarea);
        }
    }

    function showCopyFeedback(icon) {
        console.log('Showing copy feedback');
        log_message('Showing copy feedback', 'make-market.log', 'make-market', 'DEBUG');
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
            log_message('Copy feedback removed', 'make-market.log', 'make-market', 'DEBUG');
        }, 2000);
        console.log('Copy successful');
        log_message('Copy successful', 'make-market.log', 'make-market', 'INFO');
    }
});
