document.getElementById('connect-wallet').addEventListener('click', async () => {
    const walletInfo = document.getElementById('wallet-info');
    const publicKeySpan = document.getElementById('public-key');
    const statusSpan = document.getElementById('status');

    try {
        // Kiểm tra ví Phantom
        if (window.solana && window.solana.isPhantom) {
            // Kết nối ví Phantom
            const response = await window.solana.connect();
            const publicKey = response.publicKey.toString();
            publicKeySpan.textContent = publicKey;
            walletInfo.style.display = 'block';
            statusSpan.textContent = 'Đã kết nối ví! Đang ký thông điệp...';

            // Ký thông điệp
            const message = 'Xác minh đăng nhập cho Vina Network';
            const encodedMessage = new TextEncoder().encode(message);
            const signature = await window.solana.signMessage(encodedMessage, 'utf8');
            const signatureBase64 = Buffer.from(signature.signature).toString('base64');

            // Gửi Public Key, Signature, và Message đến server
            const formData = new FormData();
            formData.append('public_key', publicKey);
            formData.append('signature', signatureBase64);
            formData.append('message', message);

            const responseServer = await fetch('index.php', {
                method: 'POST',
                body: formData
            });

            if (responseServer.ok) {
                // Trạng thái sẽ được cập nhật từ PHP
            } else {
                statusSpan.textContent = 'Lỗi khi gửi thông tin đến server!';
            }
        } else {
            statusSpan.textContent = 'Vui lòng cài đặt ví Phantom!';
            walletInfo.style.display = 'block';
        }
    } catch (error) {
        console.error('Lỗi khi kết nối hoặc ký:', error);
        statusSpan.textContent = 'Lỗi: ' + error.message;
        walletInfo.style.display = 'block';
    }
});
