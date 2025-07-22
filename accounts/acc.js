document.getElementById('connect-wallet').addEventListener('click', async () => {
    const walletInfo = document.getElementById('wallet-info');
    const publicKeySpan = document.getElementById('public-key');
    const statusSpan = document.getElementById('status');

    try {
        // Kiểm tra xem ví Phantom có tồn tại không
        if (window.solana && window.solana.isPhantom) {
            // Kết nối ví Phantom
            const response = await window.solana.connect();
            const publicKey = response.publicKey.toString();

            // Hiển thị thông tin ví
            publicKeySpan.textContent = publicKey;
            walletInfo.style.display = 'block';
            statusSpan.textContent = 'Đã kết nối ví! Đang xử lý...';

            // Gửi Public Key đến server để đăng ký/đăng nhập
            const formData = new FormData();
            formData.append('public_key', publicKey);

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
        console.error('Lỗi khi kết nối ví:', error);
        statusSpan.textContent = 'Lỗi: ' + error.message;
        walletInfo.style.display = 'block';
    }
});
