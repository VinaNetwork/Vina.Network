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
        console.log('Client: Starting wallet connection');
        loading.style.display = 'block';

        // Kết nối ví
        await wallet.connect();
        const publicKey = wallet.publicKey.toString();
        console.log(`Client: Wallet connected, publicKey=${publicKey}`);
        document.getElementById('wallet-address').innerText = `Connected: ${publicKey}`;

        // Tạo thông điệp để ký
        const message = `Authenticate for Vina Network at ${new Date().toISOString()}`;
        console.log(`Client: Signing message: ${message}`);
        const encoder = new TextEncoder();
        const messageBytes = encoder.encode(message);
        const signature = await wallet.signMessage(messageBytes);
        const signatureBase64 = Buffer.from(signature).toString('base64');
        console.log(`Client: Signature created for publicKey=${publicKey}`);

        // Kiểm tra xem ví đã đăng ký chưa
        console.log('Client: Checking if user exists');
        const checkResponse = await fetch('/accounts/include/acc-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', publicKey, signature: signatureBase64, message })
        });
        const checkResult = await checkResponse.json();
        console.log(`Client: Check result: ${JSON.stringify(checkResult)}`);

        if (checkResult.message === 'User not found') {
            console.log('Client: User not found, proceeding to register');
            const registerResponse = await fetch('/accounts/include/acc-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'register', publicKey, signature: signatureBase64, message })
            });
            const registerResult = await registerResponse.json();
            console.log(`Client: Register result: ${JSON.stringify(registerResult)}`);
            if (registerResult.message === 'Registration successful') {
                console.log('Client: Registration successful, proceeding to login');
                const loginResponse = await fetch('/accounts/include/acc-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', publicKey, signature: signatureBase64, message })
                });
                const loginResult = await loginResponse.json();
                console.log(`Client: Login result: ${JSON.stringify(loginResult)}`);
                if (loginResult.token) {
                    localStorage.setItem('jwt', loginResult.token);
                    console.log('Client: JWT stored, redirecting to dashboard');
                    window.location.href = '/dashboard.php';
                } else {
                    console.error(`Client: Login failed: ${loginResult.message}`);
                    alert(loginResult.message);
                }
            } else {
                console.error(`Client: Registration failed: ${registerResult.message}`);
                alert(registerResult.message);
            }
        } else if (checkResult.message === 'Login successful' && checkResult.token) {
            console.log('Client: Login successful, redirecting to dashboard');
            localStorage.setItem('jwt', checkResult.token);
            window.location.href = '/dashboard.php';
        } else {
            console.error(`Client: Check failed: ${checkResult.message}`);
            alert(checkResult.message);
        }

        loading.style.display = 'none';
    } catch (error) {
        loading.style.display = 'none';
        console.error(`Client: Error: ${error.message}`);
        alert('Failed to connect wallet or authenticate');
    }
}

// Gắn sự kiện cho nút Connect Wallet
document.getElementById('connect-wallet').addEventListener('click', connectAndAuthenticate);
