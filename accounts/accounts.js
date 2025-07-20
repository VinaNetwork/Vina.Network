// accounts.js
import { WalletAdapterNetwork } from '@solana/wallet-adapter-base';
import { Connection, PublicKey } from '@solana/web3.js';
import { PhantomWalletAdapter, SolflareWalletAdapter } from '@solana/wallet-adapter-wallets';
import nacl from 'tweetnacl';

// Cấu hình kết nối Solana
const network = WalletAdapterNetwork.Mainnet;
const connection = new Connection('https://api.mainnet-beta.solana.com', 'confirmed');
const wallets = [new PhantomWalletAdapter(), new SolflareWalletAdapter()];

// Hàm kết nối ví và xác thực
async function connectAndAuthenticate() {
    const wallet = wallets[0]; // Ví dụ: Phantom
    const loading = document.getElementById('loading');
    try {
        // Hiển thị trạng thái loading
        loading.style.display = 'block';

        // Kết nối ví
        await wallet.connect();
        const publicKey = wallet.publicKey.toString();
        document.getElementById('wallet-address').innerText = `Connected: ${publicKey}`;

        // Tạo thông điệp để ký
        const message = `Authenticate for Vina Network at ${new Date().toISOString()}`;
        const encoder = new TextEncoder();
        const messageBytes = encoder.encode(message);
        const signature = await wallet.signMessage(messageBytes);
        const signatureBase64 = Buffer.from(signature).toString('base64');

        // Kiểm tra xem ví đã đăng ký chưa
        const checkResponse = await fetch('/accounts/include/acc-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', publicKey, signature: signatureBase64, message })
        });
        const checkResult = await checkResponse.json();

        if (checkResult.message === 'User not found') {
            // Nếu chưa đăng ký, gọi API register
            const registerResponse = await fetch('/accounts/include/acc-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'register', publicKey, signature: signatureBase64, message })
            });
            const registerResult = await registerResponse.json();
            if (registerResult.message === 'Registration successful') {
                // Sau khi đăng ký, gọi lại API login để lấy JWT
                const loginResponse = await fetch('/accounts/include/acc-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', publicKey, signature: signatureBase64, message })
                });
                const loginResult = await loginResponse.json();
                if (loginResult.token) {
                    localStorage.setItem('jwt', loginResult.token);
                    window.location.href = '/dashboard.php';
                } else {
                    alert(loginResult.message);
                }
            } else {
                alert(registerResult.message);
            }
        } else if (checkResult.message === 'Login successful' && checkResult.token) {
            // Nếu đã đăng ký, lưu JWT và chuyển hướng
            localStorage.setItem('jwt', checkResult.token);
            window.location.href = '/dashboard.php';
        } else {
            alert(checkResult.message);
        }

        // Ẩn trạng thái loading sau khi hoàn tất
        loading.style.display = 'none';
    } catch (error) {
        // Ẩn trạng thái loading nếu có lỗi
        loading.style.display = 'none';
        console.error('Error:', error);
        alert('Failed to connect wallet or authenticate');
    }
}

// Gắn sự kiện cho nút Connect Wallet
document.getElementById('connect-wallet').addEventListener('click', connectAndAuthenticate);
