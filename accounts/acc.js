document.getElementById('connect-wallet').addEventListener('click', async () => {
    const walletInfo = document.getElementById('wallet-info');
    const publicKeySpan = document.getElementById('public-key');
    const statusSpan = document.getElementById('status');

    try {
        if (window.solana && window.solana.isPhantom) {
            statusSpan.textContent = 'Đang kết nối ví...';
            const response = await window.solana.connect();
            const publicKey = response.publicKey.toString();
            publicKeySpan.textContent = publicKey;
            walletInfo.style.display = 'block';
            statusSpan.textContent = 'Đã kết nối ví! Đang ký thông điệp...';

            const timestamp = Date.now();
            const message = `Xác minh đăng nhập cho Vina Network at ${timestamp}`;
            const encodedMessage = new TextEncoder().encode(message);

            const signature = await window.solana.signMessage(encodedMessage); // Sửa: không cần 'utf8'
            const signatureBase64 = btoa(
                String.fromCharCode.apply(null, new Uint8Array(signature.signature))
            );

            const formData = new FormData();
            formData.append('public_key', publicKey);
            formData.append('signature', signatureBase64);
            formData.append('message', message);

            statusSpan.textContent = 'Đang gửi dữ liệu đến server...';
            const responseServer = await fetch('index.php', {
                method: 'POST',
                body: formData
            });

            if (responseServer.ok) {
                const text = await responseServer.text();
                console.log('Server response:', text);
            } else {
                statusSpan.textContent = `Lỗi khi gửi dữ liệu: ${responseServer.status} ${responseServer.statusText}`;
            }
        } else {
            statusSpan.textContent = 'Vui lòng cài đặt ví Phantom!';
            walletInfo.style.display = 'block';
        }
    } catch (error) {
        statusSpan.textContent = 'Lỗi: ' + error.message;
        walletInfo.style.display = 'block';
    }
});
