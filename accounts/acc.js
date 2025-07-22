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

            // Tạo thông điệp với timestamp
            const timestamp = Date.now();
            const message = `Xác minh đăng nhập cho Vina Network at ${timestamp}`;
            const encodedMessage = new TextEncoder().encode(message);
            const signature = await window.solana.signMessage(encodedMessage, 'utf8');

            // Mã hóa chữ ký thành base64 trong trình duyệt
            const signatureBase64 = btoa(
                String.fromCharCode.apply(null, new Uint8Array(signature.signature))
            );

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
