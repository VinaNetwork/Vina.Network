// ============================================================================
// File: make-market/mm.js
// Description: JavaScript file for UI interactions on Make Market page
// Created by: Vina Network
// ============================================================================

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
            console.error(`Ghi log thất bại: HTTP ${response.status}`);
        }
    }).catch(err => console.error('Lỗi ghi log:', err));
}

// Show error message
function showError(message) {
    const resultDiv = document.getElementById('mm-result');
    let enhancedMessage = message;
    if (message.includes('Số dư ví không đủ')) {
        enhancedMessage += ' <a href="https://www.binance.com/vi" target="_blank">Nạp SOL tại đây</a>';
    }
    resultDiv.innerHTML = `<p style="color: red;">Lỗi: ${enhancedMessage}</p><button class="cta-button" onclick="document.getElementById('mm-result').innerHTML='';document.getElementById('mm-result').classList.remove('active');">Xóa thông báo</button>`;
    resultDiv.classList.add('active');
    document.querySelector('#makeMarketForm button').disabled = false;
}

// Handle form submission
document.getElementById('makeMarketForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const resultDiv = document.getElementById('mm-result');
    const submitButton = document.querySelector('#makeMarketForm button');
    submitButton.disabled = true;
    resultDiv.innerHTML = '<div class="spinner">Đang xử lý...</div>';
    resultDiv.classList.add('active');
    log_message('Form đã được gửi', 'make-market.log', 'make-market', 'INFO');
    console.log('Form đã được gửi');

    const formData = new FormData(e.target);
    const params = {
        processName: formData.get('processName'),
        privateKey: formData.get('privateKey'),
        tokenMint: formData.get('tokenMint'),
        solAmount: parseFloat(formData.get('solAmount')),
        slippage: parseFloat(formData.get('slippage')),
        delay: parseInt(formData.get('delay')),
        loopCount: parseInt(formData.get('loopCount')),
        batchSize: parseInt(formData.get('batchSize')),
        transactionPublicKey: formData.get('transactionPublicKey'),
        csrf_token: formData.get('csrf_token')
    };
    log_message(`Dữ liệu form: ${JSON.stringify(params)}`, 'make-market.log', 'make-market', 'DEBUG');
    console.log('Dữ liệu form:', params);

    // Validate private key
    if (!params.privateKey || typeof params.privateKey !== 'string' || params.privateKey.length < 1) {
        log_message('Private key trống hoặc không hợp lệ', 'make-market.log', 'make-market', 'ERROR');
        showError('Private key trống hoặc không hợp lệ. Vui lòng kiểm tra lại.');
        console.error('Private key trống hoặc không hợp lệ');
        return;
    }
    log_message(`Độ dài private key: ${params.privateKey.length}`, 'make-market.log', 'make-market', 'DEBUG');
    console.log('Độ dài private key:', params.privateKey.length);

    // Derive public key
    let transactionPublicKey;
    try {
        const decodedKey = window.bs58.decode(params.privateKey);
        log_message(`Độ dài private key đã giải mã: ${decodedKey.length}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Độ dài private key đã giải mã:', decodedKey.length);
        if (decodedKey.length !== 64) {
            log_message(`Độ dài private key không hợp lệ: ${decodedKey.length}, yêu cầu 64 bytes`, 'make-market.log', 'make-market', 'ERROR');
            console.error(`Độ dài private key không hợp lệ: ${decodedKey.length}, yêu cầu 64 bytes`);
            showError('Độ dài private key không hợp lệ. Vui lòng kiểm tra lại.');
            return;
        }
        const keypair = window.solanaWeb3.Keypair.fromSecretKey(decodedKey);
        transactionPublicKey = keypair.publicKey.toBase58();
        if (!/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/.test(transactionPublicKey)) {
            log_message(`Định dạng public key không hợp lệ từ private key`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Định dạng public key không hợp lệ từ private key');
            showError('Định dạng public key không hợp lệ. Vui lòng kiểm tra private key.');
            return;
        }
        formData.set('transactionPublicKey', transactionPublicKey);
        document.getElementById('transactionPublicKey').value = transactionPublicKey;
        log_message(`Public key giao dịch đã suy ra: ${transactionPublicKey}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Public key giao dịch đã suy ra:', transactionPublicKey);
    } catch (error) {
        log_message(`Private key không hợp lệ: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Private key không hợp lệ:', error.message);
        showError(`Private key không hợp lệ: ${error.message}. Vui lòng kiểm tra lại.`);
        return;
    }

    try {
        // Submit form data
        const response = await fetch('/make-market/', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const responseText = await response.text();
        log_message(`Phản hồi gửi form: HTTP ${response.status}, Phản hồi: ${responseText}`, 'make-market.log', 'make-market', 'DEBUG');
        console.log('Phản hồi gửi form: HTTP', response.status, 'Phản hồi:', responseText);
        if (!response.ok) {
            log_message(`Gửi form thất bại: HTTP ${response.status}, Phản hồi: ${responseText}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Gửi form thất bại: HTTP', response.status, 'Phản hồi:', responseText);
            showError('Gửi form thất bại. Vui lòng thử lại.');
            return;
        }
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (error) {
            log_message(`Lỗi phân tích JSON phản hồi: ${error.message}, Phản hồi: ${responseText}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Lỗi phân tích JSON phản hồi:', error.message, 'Phản hồi:', responseText);
            showError('Phản hồi từ server không hợp lệ. Vui lòng thử lại.');
            return;
        }
        if (result.status !== 'success') {
            log_message(`Gửi form thất bại: ${result.message}`, 'make-market.log', 'make-market', 'ERROR');
            console.error('Gửi form thất bại:', result.message);
            showError(result.message); // Hiển thị thông báo lỗi chi tiết từ server
            return;
        }
        log_message(`Form đã được lưu vào cơ sở dữ liệu: transactionId=${result.transactionId}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Form đã được lưu vào cơ sở dữ liệu: transactionId=', result.transactionId);
        // Redirect to process page
        const redirectUrl = result.redirect || `/make-market/process/${result.transactionId}`;
        log_message(`Chuyển hướng đến ${redirectUrl}`, 'make-market.log', 'make-market', 'INFO');
        console.log('Chuyển hướng đến', redirectUrl);
        setTimeout(() => {
            window.location.href = redirectUrl;
            console.log('Thực hiện chuyển hướng đến', redirectUrl);
        }, 100);
    } catch (error) {
        log_message(`Lỗi khi gửi form: ${error.message}`, 'make-market.log', 'make-market', 'ERROR');
        console.error('Lỗi khi gửi form:', error.message);
        showError(`Lỗi khi gửi form: ${error.message}. Vui lòng thử lại.`);
    }
});

// Copy functionality for public_key
document.addEventListener('DOMContentLoaded', () => {
    console.log('mm.js được tải');
    log_message('mm.js được tải', 'make-market.log', 'make-market', 'DEBUG');

    const copyIcons = document.querySelectorAll('.copy-icon');
    log_message(`Tìm thấy ${copyIcons.length} biểu tượng sao chép`, 'make-market.log', 'make-market', 'DEBUG');
    if (copyIcons.length === 0) {
        log_message('Không tìm thấy phần tử .copy-icon trong DOM', 'make-market.log', 'make-market', 'ERROR');
        return;
    }

    copyIcons.forEach(icon => {
        log_message('Gắn sự kiện click vào biểu tượng sao chép', 'make-market.log', 'make-market', 'DEBUG');
        icon.addEventListener('click', (e) => {
            log_message('Biểu tượng sao chép được click', 'make-market.log', 'make-market', 'INFO');
            console.log('Biểu tượng sao chép được click');

            if (!window.isSecureContext) {
                log_message('Sao chép bị chặn: Không ở trong ngữ cảnh an toàn', 'make-market.log', 'make-market', 'ERROR');
                console.error('Sao chép bị chặn: Không ở trong ngữ cảnh an toàn');
                showError('Không thể sao chép: Tính năng này yêu cầu HTTPS');
                return;
            }

            const fullAddress = icon.getAttribute('data-full');
            if (!fullAddress) {
                log_message('Sao chép thất bại: Thuộc tính data-full không tìm thấy hoặc rỗng', 'make-market.log', 'make-market', 'ERROR');
                console.error('Sao chép thất bại: Thuộc tính data-full không tìm thấy hoặc rỗng');
                showError('Không thể sao chép: Địa chỉ không hợp lệ');
                return;
            }

            const base58Regex = /^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{32,44}$/;
            if (!base58Regex.test(fullAddress)) {
                log_message(`Định dạng địa chỉ không hợp lệ: ${fullAddress}`, 'make-market.log', 'make-market', 'ERROR');
                console.error(`Định dạng địa chỉ không hợp lệ: ${fullAddress}`);
                showError('Không thể sao chép: Định dạng địa chỉ không hợp lệ');
                return;
            }

            const shortAddress = fullAddress.length >= 8 ? fullAddress.substring(0, 4) + '...' + fullAddress.substring(fullAddress.length - 4) : 'Không hợp lệ';
            log_message(`Đang cố gắng sao chép địa chỉ: ${shortAddress}`, 'make-market.log', 'make-market', 'DEBUG');
            console.log(`Đang cố gắng sao chép địa chỉ: ${shortAddress}`);

            if (!navigator.clipboard) {
                log_message('API Clipboard không khả dụng', 'make-market.log', 'make-market', 'ERROR');
                console.error('API Clipboard không khả dụng');
                showError('Không thể sao chép: Trình duyệt không hỗ trợ tính năng này. Vui lòng sao chép thủ công.');
                return;
            }

            navigator.clipboard.writeText(fullAddress).then(() => {
                log_message('Sao chép thành công', 'make-market.log', 'make-market', 'INFO');
                console.log('Sao chép thành công');
                icon.classList.add('copied');
                const tooltip = document.createElement('span');
                tooltip.className = 'copy-tooltip';
                tooltip.textContent = 'Đã sao chép!';
                const parent = icon.parentNode;
                parent.style.position = 'relative';
                parent.appendChild(tooltip);
                setTimeout(() => {
                    icon.classList.remove('copied');
                    tooltip.remove();
                    log_message('Phản hồi sao chép đã xóa', 'make-market.log', 'make-market', 'DEBUG');
                    console.log('Phản hồi sao chép đã xóa');
                }, 2000);
            }).catch(err => {
                log_message(`API Clipboard thất bại: ${err.message}`, 'make-market.log', 'make-market', 'ERROR');
                console.error('API Clipboard thất bại:', err.message);
                showError(`Không thể sao chép: ${err.message}`);
            });
        });
    });
});
