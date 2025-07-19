// accounts.js
import { WalletAdapterNetwork } from '@solana/wallet-adapter-base';
import { Connection, PublicKey, Transaction, SystemProgram } from '@solana/web3.js';
import { PhantomWalletAdapter, SolflareWalletAdapter } from '@solana/wallet-adapter-wallets';
import nacl from 'tweetnacl';

// Cấu hình kết nối Solana
const network = WalletAdapterNetwork.Mainnet;
const connection = new Connection('https://api.mainnet-beta.solana.com', 'confirmed');
const wallets = [new PhantomWalletAdapter(), new SolflareWalletAdapter()];

// Hàm kết nối ví
async function connectWallet() {
    const wallet = wallets[0]; // Ví dụ: Phantom
    try {
        await wallet.connect();
        const publicKey = wallet.publicKey.toString();
        document.getElementById('wallet-address').innerText = `Connected: ${publicKey}`;
        return publicKey;
    } catch (error) {
        console.error('Wallet connection failed:', error);
        return null;
    }
}

// Hàm ký thông điệp để xác thực
async function signMessage(message) {
    const wallet = wallets[0];
    const encoder = new TextEncoder();
    const messageBytes = encoder.encode(message);
    try {
        const signature = await wallet.signMessage(messageBytes);
        return Buffer.from(signature).toString('base64');
    } catch (error) {
        console.error('Signing failed:', error);
        return null;
    }
}

// Đăng ký tài khoản
async function register() {
    const publicKey = await connectWallet();
    if (!publicKey) return;

    const message = `Register for Vina Network at ${new Date().toISOString()}`;
    const signature = await signMessage(message);
    if (!signature) return;

    // Gửi thông tin tới server
    const response = await fetch('/accounts/include/acc-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'register', publicKey, signature, message })
    });
    const result = await response.json();
    alert(result.message);
}

// Đăng nhập
async function login() {
    const publicKey = await connectWallet();
    if (!publicKey) return;

    const message = `Login to Vina Network at ${new Date().toISOString()}`;
    const signature = await signMessage(message);
    if (!signature) return;

    // Gửi thông tin tới server
    const response = await fetch('/accounts/include/acc-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'login', publicKey, signature, message })
    });
    const result = await response.json();
    if (result.token) {
        localStorage.setItem('jwt', result.token);
        window.location.href = '/dashboard.php';
    } else {
        alert(result.message);
    }
}

// Gắn sự kiện cho các nút
document.getElementById('connect-wallet').addEventListener('click', connectWallet);
document.getElementById('register-btn').addEventListener('click', register);
document.getElementById('login-btn').addEventListener('click', login);
