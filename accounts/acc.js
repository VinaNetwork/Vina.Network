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
            const message = `Login verification for Vina Network at ${timestamp}`;
            const encodedMessage = new TextEncoder().encode(message);

            const signature = await window.solana.signMessage(encodedMessage, 'utf8');
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

            const responseText = await responseServer.text();

            if (responseServer.ok) {
                // Cập nhật kết quả từ response HTML (script chứa status message)
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = responseText;
                const scriptTags = tempDiv.querySelectorAll('script');
                for (const scriptTag of scriptTags) {
                    const content = scriptTag.textContent || scriptTag.innerText;
                    const match = content.match(/document\.getElementById\(['"]status['"]\)\.textContent\s*=\s*(.*);/);
                    if (match) {
                        try {
                            const msg = eval(match[1]); // parse nội dung trong json_encode(...)
                            statusSpan.textContent = msg;
                        } catch (e) {
                            statusSpan.textContent = 'Xác thực thành công, nhưng không đọc được phản hồi từ server.';
                        }
                        break;
                    }
                }
            } else {
                statusSpan.textContent = `Lỗi khi gửi dữ liệu: ${responseServer.status} ${responseServer.statusText}`;
                console.error('Phản hồi server:', responseText);
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
