// File: accounts/acc.js
async function connectWallet() {
    const status = document.getElementById('status');
    if (!window.solana) {
        status.innerText = 'Please install a Solana wallet (e.g., Phantom)';
        return;
    }

    try {
        // Kết nối ví
        await window.solana.connect();
        const publicKey = window.solana.publicKey.toString();
        status.innerText = `Connected: ${publicKey}`;

        // Tạo nonce ngẫu nhiên
        const nonce = Math.random().toString(36).substring(2, 15);
        const message = `Sign this message to authenticate: ${nonce}`;
        const encodedMessage = new TextEncoder().encode(message);
        const signature = await window.solana.signMessage(encodedMessage, 'utf8');

        // Mã hóa chữ ký thành base64
        const signatureBase64 = btoa(String.fromCharCode(...signature.signature));

        // Gửi chữ ký tới API xác minh
        const response = await fetch('/accounts/auth.js', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                publicKey: publicKey,
                signature: signatureBase64,
                message: message
            })
        });

        const result = await response.json();
        if (result.success) {
            // Gửi publicKey về PHP để lưu session
            await fetch('/accounts/set_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ publicKey: result.publicKey })
            });
            window.location.href = '/accounts/profile.php';
        } else {
            status.innerText = `Authentication failed: ${result.message}`;
        }
    } catch (error) {
        status.innerText = `Error: ${error.message}`;
    }
}

document.getElementById('connectWallet').addEventListener('click', connectWallet);
