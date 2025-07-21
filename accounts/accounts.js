// accounts/accounts.js
document.addEventListener('DOMContentLoaded', () => {
    const connectButton = document.getElementById('connect-wallet');
    const walletAddress = document.getElementById('wallet-address');
    const loading = document.getElementById('loading');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const { Connection, PublicKey } = window.solanaWeb3;
    const { PhantomWalletAdapter, SolflareWalletAdapter } = window.solanaWalletAdapterWallets;
    const { WalletAdapterNetwork } = window.solanaWalletAdapterBase;
    const nacl = window.nacl;
    const Swal = window.Swal;

    // Cấu hình kết nối Solana (Devnet để khớp với auth.php)
    const network = WalletAdapterNetwork.Devnet;
    const connection = new Connection('https://api.devnet.solana.com', 'confirmed');
    const wallets = [new PhantomWalletAdapter(), new SolflareWalletAdapter()];
    let connectedWallet = null;

    // Hàm gửi log về server
    async function sendClientLog(message, logType = 'INFO') {
        try {
            console.log(`Client: Sending log: ${message}`);
            const response = await fetch('/accounts/include/log-client.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message, logType, csrf_token: csrfToken })
            });
            const result = await response.json();
            if (result.status !== 'success') {
                console.error(`Client: Failed to send log: ${result.message}`);
            }
        } catch (error) {
            console.error(`Client: Failed to send log: ${error.message}`);
        }
    }

    // Hàm ngắt kết nối ví
    async function disconnectWallet() {
        if (connectedWallet) {
            try {
                await connectedWallet.disconnect();
                walletAddress.innerText = 'Kết nối ví để đăng ký hoặc đăng nhập';
                connectButton.innerText = 'Connect Wallet';
                connectButton.classList.remove('disconnect');
                connectButton.classList.add('btn');
                connectButton.onclick = connectAndAuthenticate;
                connectedWallet = null;
                await sendClientLog('Wallet disconnected');
                Swal.fire({
                    title: 'Disconnected',
                    text: 'Wallet has been disconnected successfully.',
                    icon: 'info',
                    timer: 1500,
                    showConfirmButton: false
                });
            } catch (error) {
                console.error(`Client: Failed to disconnect wallet: ${error.message}`);
                await sendClientLog(`Failed to disconnect wallet: ${error.message}`, 'ERROR');
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to disconnect wallet.',
                    icon: 'error'
                });
            }
        }
    }

    // Hàm kết nối ví và xác thực
    async function connectAndAuthenticate() {
        if (!csrfToken) {
            console.error('Client: CSRF token not found');
            await sendClientLog('CSRF token not found', 'ERROR');
            Swal.fire({
                title: 'Error',
                text: 'CSRF token not found. Please refresh the page.',
                icon: 'error'
            });
            return;
        }

        loading.style.display = 'block';
        try {
            console.log('Client: Checking available wallets');
            await sendClientLog('Checking available wallets');
            let wallet = null;
            for (const w of wallets) {
                try {
                    await w.connect();
                    wallet = w;
                    break;
                } catch (e) {
                    console.log(`Client: Wallet ${w.name} not available: ${e.message}`);
                    await sendClientLog(`Wallet ${w.name} not available: ${e.message}`, 'INFO');
                }
            }

            if (!wallet) {
                throw new Error('No supported wallet found (Phantom or Solflare required)');
            }

            connectedWallet = wallet;
            const publicKey = wallet.publicKey.toString();
            console.log(`Client: Wallet connected, publicKey=${publicKey}`);
            await sendClientLog(`Wallet connected, publicKey=${publicKey}`);

            walletAddress.innerText = `Connected: ${publicKey}`;
            connectButton.innerText = 'Disconnect';
            connectButton.classList.remove('btn');
            connectButton.classList.add('disconnect');
            connectButton.onclick = disconnectWallet;

            // Thông báo kết nối thành công
            Swal.fire({
                title: 'Success',
                text: 'Wallet connected successfully!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });

            // Tạo thông điệp để ký
            const message = `Authenticate for Vina Network at ${new Date().toISOString()}`;
            console.log(`Client: Signing message: ${message}`);
            await sendClientLog(`Signing message: ${message}`);
            const encoder = new TextEncoder();
            const messageBytes = encoder.encode(message);
            const signature = await wallet.signMessage(messageBytes);
            const signatureBase64 = btoa(String.fromCharCode(...signature));
            console.log(`Client: Signature created for publicKey=${publicKey}`);
            await sendClientLog(`Signature created for publicKey=${publicKey}`);

            // Kiểm tra đăng nhập
            console.log('Client: Checking if user exists');
            await sendClientLog('Checking if user exists');
            const checkResponse = await fetch('/accounts/include/acc-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'login', publicKey, signature: signatureBase64, message, csrf_token: csrfToken })
            });
            const checkResult = await checkResponse.json();
            console.log(`Client: Check result: ${JSON.stringify(checkResult)}`);
            await sendClientLog(`Check result: ${JSON.stringify(checkResult)}`);

            if (checkResult.message === 'User not found') {
                console.log('Client: User not found, proceeding to register');
                await sendClientLog('User not found, proceeding to register');
                const registerResponse = await fetch('/accounts/include/acc-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'register', publicKey, signature: signatureBase64, message, csrf_token: csrfToken })
                });
                const registerResult = await registerResponse.json();
                console.log(`Client: Register result: ${JSON.stringify(registerResult)}`);
                await sendClientLog(`Register result: ${JSON.stringify(registerResult)}`);
                if (registerResult.message === 'Registration successful') {
                    console.log('Client: Registration successful, proceeding to login');
                    await sendClientLog('Registration successful, proceeding to login');
                    const loginResponse = await fetch('/accounts/include/acc-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'login', publicKey, signature: signatureBase64, message, csrf_token: csrfToken })
                    });
                    const loginResult = await loginResponse.json();
                    console.log(`Client: Login result: ${JSON.stringify(loginResult)}`);
                    await sendClientLog(`Login result: ${JSON.stringify(loginResult)}`);
                    if (loginResult.token) {
                        localStorage.setItem('jwt', loginResult.token);
                        console.log('Client: JWT stored, redirecting to dashboard');
                        await sendClientLog('JWT stored, redirecting to dashboard');
                        window.location.href = '/dashboard.php';
                    } else {
                        throw new Error(loginResult.message);
                    }
                } else {
                    throw new Error(registerResult.message);
                }
            } else if (checkResult.message === 'Login successful' && checkResult.token) {
                console.log('Client: Login successful, redirecting to dashboard');
                await sendClientLog('Login successful, redirecting to dashboard');
                localStorage.setItem('jwt', checkResult.token);
                window.location.href = '/dashboard.php';
            } else {
                throw new Error(checkResult.message);
            }
        } catch (error) {
            console.error(`Client: Error: ${error.message}`);
            await sendClientLog(`Error: ${error.message}`, 'ERROR');
            Swal.fire({
                title: 'Error',
                text: `Failed to connect wallet or authenticate: ${error.message}`,
                icon: 'error'
            });
        } finally {
            loading.style.display = 'none';
        }
    }
});
